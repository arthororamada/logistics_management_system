@extends('layouts.app')

@section('title', 'Users')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Users']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">User management</p><h2>Users</h2><p>Create and maintain accounts that can access the application.</p></div>
        @if(auth()->user()->hasPermission('users.create'))<x-button href="{{ route('users.create') }}" variant="primary" icon="plus">Add User</x-button>@endif
    </section>

    <x-card title="User Accounts" subtitle="Search, filter, and manage authenticated users.">
        <form method="GET" action="{{ route('users.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, username, or location"></label>
            <select name="role" aria-label="Filter by role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected(($filters['role'] ?? '') == $role->id)>{{ $role->name }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('users.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table users-table">
                <thead><tr><th>User</th><th>Username</th><th>Location</th><th>Roles</th><th>Status</th><th>Created</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td><div class="role-name-cell"><span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><div><strong>{{ $user->name }}</strong>@if(auth()->id() === $user->id)<small>Current account</small>@endif</div></div></td>
                            <td><span class="username-label">{{ $user->username }}</span></td>
                            <td>@if($user->location)<strong>{{ $user->location->location_name }}</strong><small>{{ $user->location_code }}</small>@else<span>—</span>@endif</td>
                            <td><div class="role-tags">@forelse($user->roles as $role)<span>{{ $role->name }}</span>@empty<em>No role</em>@endforelse</div></td>
                            <td><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></td>
                            <td><span class="date-label">{{ $user->created_at?->format('M d, Y') }}</span></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('users.update'))<a href="{{ route('users.edit', $user) }}" class="table-action">Edit</a>@endif
                                @if(auth()->id() !== $user->id && auth()->user()->hasPermission('users.delete'))<form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Delete this user account? This action cannot be undone." data-confirm-title="Delete record?" data-confirm-button="Delete">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-table">No users match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="pagination-links">{{ $users->links() }}</div>@endif
    </x-card>
@endsection
