<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $users = User::with('roles')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($builder) => $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%"));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['role'] ?? null, fn ($query, $roleId) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($roleId)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::orderBy('name')->get(), 'filters' => $filters]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User(), 'roles' => Role::orderByDesc('is_system')->orderBy('name')->get(), 'selectedRoles' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'is_active' => $validated['is_active'],
            ]);
            $user->roles()->sync($validated['roles']);
        });
        return redirect()->route('users.index')->with('success', 'User account created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user, 'roles' => Role::orderByDesc('is_system')->orderBy('name')->get(), 'selectedRoles' => $user->roles()->pluck('roles.id')]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $administratorRoleId = Role::where('slug', 'administrator')->value('id');

        if ($request->user()->is($user) && ! $validated['is_active']) {
            return back()->withInput()->with('error', 'You cannot deactivate your own account.');
        }
        if ($request->user()->is($user) && $user->hasRole('administrator') && ! in_array($administratorRoleId, $validated['roles'])) {
            return back()->withInput()->with('error', 'You cannot remove your own administrator role.');
        }

        DB::transaction(function () use ($validated, $user) {
            $attributes = ['name' => $validated['name'], 'username' => $validated['username'], 'is_active' => $validated['is_active']];
            if (! empty($validated['password'])) $attributes['password'] = $validated['password'];
            $user->update($attributes);
            $user->roles()->sync($validated['roles']);
        });
        return redirect()->route('users.index')->with('success', 'User account updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) return back()->with('error', 'You cannot delete your own account.');
        if ($user->hasRole('administrator') && Role::where('slug', 'administrator')->first()?->users()->count() <= 1) {
            return back()->with('error', 'The last administrator account cannot be deleted.');
        }
        $user->delete();
        return back()->with('success', 'User account deleted successfully.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'is_active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);
    }
}
