<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TrainerController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): View
    {
        $query = Trainer::query()->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('trainer_code', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $trainers = $query->withCount('gymClasses')->paginate(10)->withQueryString();

        $stats = [
            'total' => Trainer::count(),
            'active' => Trainer::where('status', 'active')->count(),
            'inactive' => Trainer::where('status', 'inactive')->count(),
            'classes' => Trainer::withCount('gymClasses')->get()->sum('gym_classes_count'),
        ];

        return view('trainers.index', compact('trainers', 'stats'));
    }

    public function create(): View
    {
        return view('trainers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['avatar'], $data['remove_avatar']);
        $data['trainer_code'] = $this->generateCode();
        $data['hire_date'] = $data['hire_date'] ?? now()->toDateString();
        $data['avatar'] = $this->storeImage($request, 'avatar', 'trainers');

        Trainer::create($data);

        return redirect()->route('trainers.index')->with('success', 'Trainer created successfully.');
    }

    public function show(Trainer $trainer): View
    {
        $trainer->load([
            'gymClasses',
            'members' => fn ($q) => $q->with('activeSubscription.plan')
                ->orderBy('first_name')
                ->orderBy('last_name'),
        ]);

        return view('trainers.show', compact('trainer'));
    }

    public function edit(Trainer $trainer): View
    {
        return view('trainers.edit', compact('trainer'));
    }

    public function update(Request $request, Trainer $trainer): RedirectResponse
    {
        $data = $this->validated($request, $trainer);
        unset($data['avatar'], $data['remove_avatar']);

        if ($this->clearImageIfRequested($request, 'avatar', $trainer)) {
            $data['avatar'] = null;
        } else {
            $data['avatar'] = $this->storeImage($request, 'avatar', 'trainers', $trainer);
        }

        $trainer->update($data);

        return redirect()->route('trainers.index')->with('success', 'Trainer updated successfully.');
    }

    public function destroy(Trainer $trainer): RedirectResponse
    {
        if ($trainer->avatar && ! str_starts_with($trainer->avatar, 'http')) {
            Storage::disk('public')->delete($trainer->avatar);
        }

        $trainer->delete();

        return redirect()->route('trainers.index')->with('success', 'Trainer deleted successfully.');
    }

    private function validated(Request $request, ?Trainer $trainer = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:trainers,email,' . ($trainer?->id ?? 'NULL')],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'TRN-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Trainer::where('trainer_code', $code)->exists());

        return $code;
    }
}
