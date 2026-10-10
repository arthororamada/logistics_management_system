@extends('layouts.app')

@section('title', 'Vehicle Types')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Types']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Configuration</p><h2>Vehicle Types</h2><p>Maintain the vehicle classifications used across fleet operations.</p></div>
        @if(auth()->user()->hasPermission('vehicle-types.create'))<x-button href="{{ route('vehicle-types.create') }}" variant="primary" icon="plus">Add Vehicle Type</x-button>@endif
    </section>

    <x-card title="Vehicle Type Directory" subtitle="Search, filter, and manage vehicle type records.">
        <form method="GET" action="{{ route('vehicle-types.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search vehicle type"></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('vehicle-types.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Vehicle Type</th><th>Status</th><th>Created</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($vehicleTypes as $vehicleType)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="vehicles" :size="17" /></span><div><strong>{{ $vehicleType->vehicle_type }}</strong></div></div></td>
                            <td><x-status-badge :status="$vehicleType->is_active ? 'active' : 'inactive'" /></td>
                            <td><span class="date-label">{{ $vehicleType->created_at?->format('M d, Y') }}</span></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('vehicle-types.update'))<a href="{{ route('vehicle-types.edit', $vehicleType) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('vehicle-types.delete'))<form method="POST" action="{{ route('vehicle-types.destroy', $vehicleType) }}" onsubmit="return confirm('Delete this vehicle type? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-table">No vehicle types match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vehicleTypes->hasPages())<div class="pagination-links">{{ $vehicleTypes->links() }}</div>@endif
    </x-card>
@endsection
