<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): View
    {
        $this->ensureDefaultCategories();

        $query = $this->filteredQuery($request);
        $expenses = $query->paginate(10)->withQueryString();
        $managedCategories = ExpenseCategory::query()->orderBy('name')->get();
        $categoryNames = $managedCategories->pluck('name');

        $stats = [
            'total' => Expense::count(),
            'approved' => Expense::where('status', 'approved')->count(),
            'pending' => Expense::where('status', 'pending')->count(),
            'amount' => Expense::where('status', 'approved')->sum('amount'),
        ];

        return view('expenses.index', [
            'expenses' => $expenses,
            'stats' => $stats,
            'categories' => $categoryNames,
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'formCategories' => $categoryNames,
            'managedCategories' => $managedCategories,
            'openCreateModal' => $request->boolean('create') || old('_form') === 'expense_create',
            'openCategoryModal' => $request->boolean('categories')
                || old('_form') === 'expense_category'
                || session('open_expense_categories') === true,
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()?->canExportExpenses(), 403, 'You do not have permission to export expenses.');

        $expenses = $this->filteredQuery($request)
            ->with(['account', 'recorder'])
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get();

        $filename = 'expense-sheet-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($expenses) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens currency/text correctly
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Expense #',
                'Category',
                'Vendor',
                'Account',
                'Payment Method',
                'Expense Date',
                'Amount (' . currency_code() . ')',
                'Status',
                'Recorded By',
                'Has Attachment',
                'Description',
            ]);

            foreach ($expenses as $expense) {
                fputcsv($out, [
                    $expense->expense_number,
                    $expense->category,
                    $expense->vendor ?: '',
                    $expense->account?->name ?? '',
                    ucfirst(str_replace('_', ' ', $expense->payment_method)),
                    $expense->expense_date?->format('Y-m-d'),
                    number_format((float) $expense->amount, 2, '.', ''),
                    ucfirst($expense->status),
                    $expense->recorder?->name ?? '',
                    $expense->receipt ? 'Yes' : 'No',
                    $expense->description ?: '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('expenses.index', ['create' => 1]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['attachment'], $data['remove_attachment']);
        $data['title'] = $data['category'];

        DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'expense_number' => $this->generateNumber(),
                'account_id' => $data['account_id'] ?? null,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'payment_method' => $data['payment_method'],
                'vendor' => $data['vendor'] ?? null,
                'receipt' => $this->storeImage($request, 'attachment', 'expenses'),
                'status' => $data['status'],
                'recorded_by' => auth()->id(),
            ]);

            if ($expense->status === 'approved') {
                $this->adjustAccountBalance($expense->account_id, -(float) $expense->amount);
            }
        });

        return redirect()->route('expenses.index')->with('success', 'Expense recorded successfully.');
    }

    public function show(Expense $expense): View
    {
        $expense->load(['account', 'recorder']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        $this->ensureDefaultCategories();

        return view('expenses.edit', [
            'expense' => $expense,
            'accounts' => Account::where('is_active', true)->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['attachment'], $data['remove_attachment']);
        $data['title'] = $data['category'];

        $wasApproved = $expense->status === 'approved';
        $oldAmount = (float) $expense->amount;
        $oldAccountId = $expense->account_id;

        DB::transaction(function () use ($data, $request, $expense, $wasApproved, $oldAmount, $oldAccountId) {
            if ($wasApproved) {
                $this->adjustAccountBalance($oldAccountId, $oldAmount);
            }

            if ($request->boolean('remove_attachment') && $expense->receipt) {
                if (! str_starts_with((string) $expense->receipt, 'http')) {
                    Storage::disk('public')->delete($expense->receipt);
                }
                $data['receipt'] = null;
            } elseif ($request->hasFile('attachment')) {
                if ($expense->receipt && ! str_starts_with((string) $expense->receipt, 'http')) {
                    Storage::disk('public')->delete($expense->receipt);
                }
                $data['receipt'] = $request->file('attachment')->store('expenses', 'public');
            }

            $expense->update([
                'account_id' => $data['account_id'] ?? null,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'payment_method' => $data['payment_method'],
                'vendor' => $data['vendor'] ?? null,
                'status' => $data['status'],
                'receipt' => array_key_exists('receipt', $data) ? $data['receipt'] : $expense->receipt,
            ]);

            if ($expense->status === 'approved') {
                $this->adjustAccountBalance($expense->account_id, -(float) $expense->amount);
            }
        });

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        DB::transaction(function () use ($expense) {
            if ($expense->status === 'approved') {
                $this->adjustAccountBalance($expense->account_id, (float) $expense->amount);
            }

            if ($expense->receipt && ! str_starts_with((string) $expense->receipt, 'http')) {
                Storage::disk('public')->delete($expense->receipt);
            }

            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'Expense deleted successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
        ]);

        ExpenseCategory::create(['name' => trim($data['name'])]);

        return redirect()
            ->route('expenses.index', ['categories' => 1])
            ->with('success', 'Category added.')
            ->with('open_expense_categories', true);
    }

    public function updateCategory(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id),
            ],
        ]);

        $newName = trim($data['name']);
        $oldName = $expenseCategory->name;

        DB::transaction(function () use ($expenseCategory, $oldName, $newName) {
            $expenseCategory->update(['name' => $newName]);

            if ($oldName !== $newName) {
                Expense::query()
                    ->where('category', $oldName)
                    ->update([
                        'category' => $newName,
                        'title' => $newName,
                    ]);
            }
        });

        return redirect()
            ->route('expenses.index', ['categories' => 1])
            ->with('success', 'Category updated.')
            ->with('open_expense_categories', true);
    }

    public function destroyCategory(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $inUse = Expense::query()->where('category', $expenseCategory->name)->exists();
        if ($inUse) {
            return redirect()
                ->route('expenses.index', ['categories' => 1])
                ->with('error', 'Cannot delete category “'.$expenseCategory->name.'” because expenses use it.')
                ->with('open_expense_categories', true);
        }

        $expenseCategory->delete();

        return redirect()
            ->route('expenses.index', ['categories' => 1])
            ->with('success', 'Category deleted.')
            ->with('open_expense_categories', true);
    }

    private function filteredQuery(Request $request)
    {
        $query = Expense::query()->with('account')->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('expense_date', '<=', $to);
        }

        return $query;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'account_id' => ['nullable', 'exists:accounts,id'],
            'category' => ['required', 'string', 'max:100', 'exists:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,other'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:2048'],
            'remove_attachment' => ['nullable', 'boolean'],
        ], [
            'category.exists' => 'Select a valid expense category.',
            'attachment.max' => 'The attachment may not be greater than 2 MB.',
            'attachment.mimes' => 'Attachment must be an image (JPG, PNG, WEBP, GIF) or PDF.',
        ]);
    }

    private function adjustAccountBalance(?int $accountId, float $delta): void
    {
        if (! $accountId || $delta == 0.0) {
            return;
        }

        $account = Account::find($accountId);
        if ($account) {
            $account->update([
                'current_balance' => (float) $account->current_balance + $delta,
            ]);
        }
    }

    private function ensureDefaultCategories(): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        foreach ([
            'Utilities', 'Rent', 'Equipment', 'Maintenance', 'Salaries',
            'Marketing', 'Supplies', 'Insurance', 'Other',
        ] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function generateNumber(): string
    {
        do {
            $number = 'EXP-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Expense::withTrashed()->where('expense_number', $number)->exists());

        return $number;
    }
}
