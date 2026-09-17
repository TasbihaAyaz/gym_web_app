<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MembershipPlanController extends Controller
{
    public function index(Request $request): View
    {
        $query = MembershipPlan::query()->withCount('subscriptions')->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($tier = $request->get('tier')) {
            $query->where('tier', $tier);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        $plans = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => MembershipPlan::count(),
            'active' => MembershipPlan::where('is_active', true)->count(),
            'basic' => MembershipPlan::where('tier', 'basic')->count(),
            'premium' => MembershipPlan::where('tier', 'premium')->count(),
        ];

        return view('plans.index', compact('plans', 'stats'));
    }

    public function create(): View
    {
        return view('plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['features'] = $this->parseFeatures($request->input('features_text'));

        MembershipPlan::create($data);

        return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
    }

    public function show(MembershipPlan $plan): View
    {
        $plan->loadCount('subscriptions');

        return view('plans.show', compact('plan'));
    }

    public function edit(MembershipPlan $plan): View
    {
        return view('plans.edit', compact('plan'));
    }

    public function update(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $data = $this->validated($request);

        if ($plan->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $plan->id);
        }

        $data['features'] = $this->parseFeatures($request->input('features_text'));

        $plan->update($data);

        return redirect()->route('plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(MembershipPlan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan deleted successfully.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tier' => ['required', 'in:basic,standard,premium'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
            'features_text' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        unset($data['features_text']);

        return $data;
    }

    private function parseFeatures(?string $text): array
    {
        if (!$text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 1;

        while (
            MembershipPlan::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
