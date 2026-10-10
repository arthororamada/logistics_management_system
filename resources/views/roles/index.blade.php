@extends('layouts.app')

@section('title', 'Roles')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Roles']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Access control</p><h2>Roles</h2><p>Define responsibilities and control access across the application.</p></div>
        @if(auth()->user()->hasPermission('roles.create'))<x-button href="{{ route('roles.create') }}" variant="primary" icon="plus">Create Role</x-button>@endif
    </section>

    <section class="access-summary-grid">
        <article><span><x-icon name="shield" /></span><div><strong>{{ $roles->count() }}</strong><p>Total roles</p></div></article>
        <article><span><x-icon name="key" /></span><div><strong>{{ $roles->sum('permissions_count') }}</strong><p>Permission assignments</p></div></article>
        <article><span><x-icon name="students" /></span><div><strong>{{ $roles->sum('users_count') }}</strong><p>Assigned users</p></div></article>
    </section>

    <x-card title="Application Roles" subtitle="System roles are protected from deletion." collapsible>
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Role</th><th>Type</th><th>Users</th><th>Permissions</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="shield" :size="17" /></span><div><strong>{{ $role->name }}</strong><small>{{ $role->description ?: 'No description provided.' }}</small></div></div></td>
                            <td><x-status-badge :status="$role->is_system ? 'system' : 'custom'" /></td>
                            <td><span class="count-label">{{ $role->users_count }}</span></td>
                            <td><span class="count-label">{{ $role->permissions_count }}</span></td>
                            <td class="align-right">
                                <div class="row-actions">
                                    @if(auth()->user()->hasPermission('roles.update'))<a href="{{ route('roles.edit', $role) }}" class="table-action">Edit</a>@endif
                                    @if(!$role->is_system && auth()->user()->hasPermission('roles.delete'))
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete this role? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No roles have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
