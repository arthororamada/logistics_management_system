@extends('layouts.app')

@section('title', 'Warehouses')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Warehouses']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Configuration</p><h2>Warehouses</h2><p>Maintain warehouse locations used across fleet and logistics operations.</p></div>
        @if(auth()->user()->hasPermission('warehouses.create'))<x-button href="{{ route('warehouses.create') }}" variant="primary" icon="plus">Add Warehouse</x-button>@endif
    </section>

    <x-card title="Warehouse Directory" subtitle="Search, filter, and manage warehouse records.">
        <form method="GET" action="{{ route('warehouses.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code, description, or location"></label>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('warehouses.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table warehouse-table">
                <thead><tr><th>Warehouse</th><th>Location</th><th>Complete Address</th><th>Coordinates</th><th>Status</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($warehouses as $warehouse)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="warehouse" :size="17" /></span><div><strong>{{ $warehouse->warehouse_description }}</strong><small class="warehouse-code">{{ $warehouse->warehouse_code }}</small></div></div></td>
                            <td><strong>{{ $warehouse->city }}, {{ $warehouse->province }}</strong><small>{{ $warehouse->country }}</small></td>
                            <td><span class="warehouse-address">{{ $warehouse->complete_address }}</span></td>
                            <td><span class="coordinate-label">{{ $warehouse->latitude }}, {{ $warehouse->longitude }}</span></td>
                            <td><x-status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" /></td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('warehouses.update'))<a href="{{ route('warehouses.edit', $warehouse) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('warehouses.delete'))<form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return confirm('Delete this warehouse? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-table">No warehouses match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($warehouses->hasPages())<div class="pagination-links">{{ $warehouses->links() }}</div>@endif
    </x-card>
@endsection
