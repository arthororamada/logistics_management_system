<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get();
        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('roles.create', [
            'role' => new Role(),
            'permissionGroups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'selectedPermissions' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);
        DB::transaction(function () use ($validated) {
            $role = Role::create(['name' => $validated['name'], 'slug' => Str::slug($validated['name']), 'description' => $validated['description'] ?? null]);
            $role->permissions()->sync($validated['permissions'] ?? []);
        });
        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'selectedPermissions' => $role->permissions()->pluck('permissions.id'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validateRole($request, $role);
        DB::transaction(function () use ($validated, $role) {
            $role->update(['name' => $validated['name'], 'slug' => $role->is_system ? $role->slug : Str::slug($validated['name']), 'description' => $validated['description'] ?? null]);
            $role->permissions()->sync($validated['permissions'] ?? []);
        });
        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) return back()->with('error', 'System roles cannot be deleted.');
        if ($role->users()->exists()) return back()->with('error', 'Reassign users before deleting this role.');
        $role->delete();
        return back()->with('success', 'Role deleted successfully.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = Str::slug($validated['name']);
        $slugExists = Role::where('slug', $slug)->when($role, fn ($query) => $query->whereKeyNot($role->getKey()))->exists();
        if ($slug === '' || $slugExists) {
            throw ValidationException::withMessages(['name' => 'This role name conflicts with an existing role identifier.']);
        }

        return $validated;
    }
}
