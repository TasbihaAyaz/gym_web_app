<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Role::query()->withCount(['users', 'permissions'])->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->get('is_active') === '1');
        }

        $roles = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => Role::count(),
            'active' => Role::where('is_active', true)->count(),
            'permissions' => Permission::count(),
            'assigned' => Role::has('users')->count(),
        ];

        return view('roles.index', compact('roles', 'stats'));
    }

    public function create(): View
    {
        return view('roles.create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request) {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'],
            ]);

            $role->permissions()->sync($request->input('permissions', []));
        });

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        $role->load(['permissions', 'users']);

        return view('roles.show', [
            'role' => $role,
            'permissionGroups' => $role->permissions->groupBy('module'),
        ]);
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'selected' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);

        DB::transaction(function () use ($data, $request, $role) {
            $role->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'],
            ]);

            $role->permissions()->sync($request->input('permissions', []));
        });

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->slug === 'admin') {
            return redirect()->route('roles.index')->with('error', 'The Admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'Cannot delete a role assigned to users.');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:roles,slug,' . ($role?->id ?? 'NULL')],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');

        // Ensure unique slug after auto-generation
        $base = $data['slug'] ?: 'role';
        $slug = $base;
        $i = 1;
        while (
            Role::where('slug', $slug)
                ->when($role, fn ($q) => $q->where('id', '!=', $role->id))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        $data['slug'] = $slug;

        return $data;
    }

    private function permissionGroups()
    {
        return Permission::query()
            ->whereNotIn('module', ['equipment', 'maintenance', 'invoices'])
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
    }
}
