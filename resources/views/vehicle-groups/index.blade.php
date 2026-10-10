@extends('layouts.app')

@section('title', 'Vehicle Groups')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Groups']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Configuration</p><h2>Vehicle Groups</h2><p>Organize fleet vehicles into reusable operational groups.</p></div>
        @if(auth()->user()->hasPermission('vehicle-groups.create'))<x-button href="{{ route('vehicle-groups.create') }}" variant="primary" icon="plus">Add Vehicle Group</x-button>@endif
    </section>

    <x-card title="Vehicle Group Directory" subtitle="Search, filter, and manage vehicle group records.">
        <form method="GET" action="{{ route('vehicle-groups.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code or description"></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('vehicle-groups.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Group Code</th><th>Vehicle Group Description</th><th>Status</th><th>Created</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($vehicleGroups as $vehicleGroup)
                        <tr>
                            <td><span class="username-label">{{ $vehicleGroup->group_code }}</span></td>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="vehicle-group" :size="17" /></span><div><strong>{{ $vehicleGroup->vehicle_group_description }}</strong></div></div></td>
                            <td><x-status-badge :status="$vehicleGroup->is_active ? 'active' : 'inactive'" /></td>
                            <td><span class="date-label">{{ $vehicleGroup->created_at?->format('M d, Y') }}</span></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('vehicle-groups.update'))<a href="{{ route('vehicle-groups.edit', $vehicleGroup) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('vehicle-groups.delete'))<form method="POST" action="{{ route('vehicle-groups.destroy', $vehicleGroup) }}" onsubmit="return confirm('Delete this vehicle group? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-table">No vehicle groups match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vehicleGroups->hasPages())<div class="pagination-links">{{ $vehicleGroups->links() }}</div>@endif
    </x-card>
@endsection
