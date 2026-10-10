@extends('layouts.app')

@section('title', 'Vehicle Makes')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Makes']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Configuration</p><h2>Vehicle Makes</h2><p>Maintain the manufacturers used across fleet operations.</p></div>
        @if(auth()->user()->hasPermission('vehicle-makes.create'))<x-button href="{{ route('vehicle-makes.create') }}" variant="primary" icon="plus">Add Vehicle Make</x-button>@endif
    </section>

    <x-card title="Vehicle Make Directory" subtitle="Search, filter, and manage vehicle make records.">
        <form method="GET" action="{{ route('vehicle-makes.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search vehicle make"></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('vehicle-makes.index') }}" class="clear-filter">Clear</a>@endif
        </form>
        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Vehicle Make</th><th>Status</th><th>Created</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($vehicleMakes as $vehicleMake)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="vehicles" :size="17" /></span><div><strong>{{ $vehicleMake->vehicle_make }}</strong></div></div></td>
                            <td><x-status-badge :status="$vehicleMake->is_active ? 'active' : 'inactive'" /></td>
                            <td><span class="date-label">{{ $vehicleMake->created_at?->format('M d, Y') }}</span></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('vehicle-makes.update'))<a href="{{ route('vehicle-makes.edit', $vehicleMake) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('vehicle-makes.delete'))<form method="POST" action="{{ route('vehicle-makes.destroy', $vehicleMake) }}" onsubmit="return confirm('Delete this vehicle make? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-table">No vehicle makes match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vehicleMakes->hasPages())<div class="pagination-links">{{ $vehicleMakes->links() }}</div>@endif
    </x-card>
@endsection
