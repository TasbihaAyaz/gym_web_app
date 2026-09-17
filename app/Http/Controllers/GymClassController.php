<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Models\ClassSchedule;
use App\Models\GymClass;
use App\Models\Trainer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GymClassController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request): View
    {
        $query = GymClass::query()->with('trainer')->withCount('enrollments')->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($trainerId = $request->get('trainer_id')) {
            $query->where('trainer_id', $trainerId);
        }

        $classes = $query->paginate(10)->withQueryString();
        $trainers = Trainer::where('status', 'active')->orderBy('first_name')->get();

        $stats = [
            'total' => GymClass::count(),
            'active' => GymClass::where('status', 'active')->count(),
            'inactive' => GymClass::where('status', 'inactive')->count(),
            'enrollments' => GymClass::withCount('enrollments')->get()->sum('enrollments_count'),
        ];

        return view('classes.index', compact('classes', 'trainers', 'stats'));
    }

    public function create(): View
    {
        $trainers = Trainer::where('status', 'active')->orderBy('first_name')->get();

        return view('classes.create', compact('trainers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $schedules = $data['schedules'] ?? [];
        unset($data['schedules'], $data['image'], $data['remove_image']);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $this->storeImage($request, 'image', 'classes');

        DB::transaction(function () use ($data, $schedules) {
            $class = GymClass::create($data);
            $this->syncSchedules($class, $schedules);
        });

        return redirect()->route('classes.index')->with('success', 'Class created successfully.');
    }

    public function show(GymClass $gym_class): View
    {
        $gym_class->load(['trainer', 'schedules', 'enrollments.member']);

        return view('classes.show', ['class' => $gym_class]);
    }

    public function edit(GymClass $gym_class): View
    {
        $gym_class->load('schedules');
        $trainers = Trainer::where('status', 'active')->orderBy('first_name')->get();

        return view('classes.edit', ['class' => $gym_class, 'trainers' => $trainers]);
    }

    public function update(Request $request, GymClass $gym_class): RedirectResponse
    {
        $data = $this->validated($request);
        $schedules = $data['schedules'] ?? [];
        unset($data['schedules'], $data['image'], $data['remove_image']);

        if ($gym_class->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $gym_class->id);
        }

        if ($this->clearImageIfRequested($request, 'image', $gym_class)) {
            $data['image'] = null;
        } else {
            $data['image'] = $this->storeImage($request, 'image', 'classes', $gym_class);
        }

        DB::transaction(function () use ($gym_class, $data, $schedules) {
            $gym_class->update($data);
            $this->syncSchedules($gym_class, $schedules);
        });

        return redirect()->route('classes.index')->with('success', 'Class updated successfully.');
    }

    public function destroy(GymClass $gym_class): RedirectResponse
    {
        if ($gym_class->image && ! str_starts_with($gym_class->image, 'http')) {
            Storage::disk('public')->delete($gym_class->image);
        }

        $gym_class->delete();

        return redirect()->route('classes.index')->with('success', 'Class deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trainer_id' => ['nullable', 'exists:trainers,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:300'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.id' => ['nullable', 'integer'],
            'schedules.*.schedule_date' => ['nullable', 'date'],
            'schedules.*.start_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'schedules.*.end_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'schedules.*.room' => ['nullable', 'string', 'max:100'],
            'schedules.*.status' => ['nullable', 'in:scheduled,completed,cancelled'],
        ]);
    }

    private function syncSchedules(GymClass $class, array $schedules): void
    {
        $keptIds = [];

        foreach ($schedules as $row) {
            if (empty($row['schedule_date']) || empty($row['start_time']) || empty($row['end_time'])) {
                continue;
            }

            $payload = [
                'schedule_date' => $row['schedule_date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'room' => $row['room'] ?? null,
                'status' => $row['status'] ?? 'scheduled',
            ];

            if (! empty($row['id'])) {
                $schedule = ClassSchedule::query()
                    ->where('gym_class_id', $class->id)
                    ->where('id', $row['id'])
                    ->first();

                if ($schedule) {
                    $schedule->update($payload);
                    $keptIds[] = $schedule->id;
                    continue;
                }
            }

            $created = $class->schedules()->create($payload);
            $keptIds[] = $created->id;
        }

        $class->schedules()->whereNotIn('id', $keptIds)->delete();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'class';
        $slug = $base;
        $i = 1;

        while (
            GymClass::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
