<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $query = Account::query()->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        $accounts = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => Account::count(),
            'active' => Account::where('is_active', true)->count(),
            'assets' => Account::where('type', 'asset')->sum('current_balance'),
            'income' => Account::where('type', 'income')->sum('current_balance'),
        ];

        return view('accounts.index', compact('accounts', 'stats'));
    }

    public function create(): View
    {
        return view('accounts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['current_balance'] = $data['opening_balance'];

        Account::create($data);

        return redirect()->route('accounts.index')->with('success', 'Account created successfully.');
    }

    public function show(Account $account): View
    {
        $account->load(['payments' => fn ($q) => $q->latest()->take(8), 'expenses' => fn ($q) => $q->latest()->take(8)]);

        return view('accounts.show', compact('account'));
    }

    public function edit(Account $account): View
    {
        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $this->validated($request, $account);

        // Keep current_balance unless opening balance changed and no activity yet
        if ((float) $account->opening_balance !== (float) $data['opening_balance']
            && $account->payments()->count() === 0
            && $account->expenses()->count() === 0) {
            $data['current_balance'] = $data['opening_balance'];
        }

        $account->update($data);

        return redirect()->route('accounts.index')->with('success', 'Account updated successfully.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->payments()->exists() || $account->expenses()->exists()) {
            return redirect()->route('accounts.index')
                ->with('error', 'Cannot delete account with linked payments or expenses.');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted successfully.');
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'unique:accounts,code,' . ($account?->id ?? 'NULL')],
            'type' => ['required', 'in:asset,liability,income,expense,equity'],
            'opening_balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
