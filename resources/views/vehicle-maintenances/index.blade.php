@extends('layouts.app')

@section('title', 'Vehicle Maintenance')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Configuration'], ['label' => 'Vehicle Maintenance']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Configuration</p><h2>Vehicle Maintenance</h2><p>Define distance- and date-based maintenance policies for each vehicle classification.</p></div>
        <div class="heading-actions">
            @if(auth()->user()->hasPermission('vehicle-maintenances.create'))
                <a href="{{ route('vehicle-maintenances.import-template') }}" class="button button-outline">Download XLSX Template</a>
                <x-button href="{{ route('vehicle-maintenances.create') }}" variant="primary" icon="plus">Add Maintenance Policy</x-button>
            @endif
        </div>
    </section>

    <x-card title="Vehicle Maintenance Directory" subtitle="Search and filter the maintenance policies used by the fleet.">
        <form method="GET" action="{{ route('vehicle-maintenances.index') }}" class="filter-bar route-filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code or description"></label>
            <select name="vehicle_group" aria-label="Filter by vehicle group"><option value="">All vehicle groups</option>@foreach($vehicleGroups as $group)<option value="{{ $group->vehicle_group_description }}" @selected(($filters['vehicle_group'] ?? '') === $group->vehicle_group_description)>{{ $group->vehicle_group_description }}</option>@endforeach</select>
            <select name="vehicle_type" aria-label="Filter by vehicle type"><option value="">All vehicle types</option>@foreach($vehicleTypes as $type)<option value="{{ $type->vehicle_type }}" @selected(($filters['vehicle_type'] ?? '') === $type->vehicle_type)>{{ $type->vehicle_type }}</option>@endforeach</select>
            <select name="maintenance_type" aria-label="Filter by maintenance type"><option value="">All policy types</option><option value="distance_policy" @selected(($filters['maintenance_type'] ?? '') === 'distance_policy')>Distance policy</option><option value="date_policy" @selected(($filters['maintenance_type'] ?? '') === 'date_policy')>Date policy</option></select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('vehicle-maintenances.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table">
                <thead><tr><th>Code / Description</th><th>Vehicle</th><th>Policy</th><th>Threshold</th><th>Warning</th><th>Status</th><th>Updated</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        <tr>
                            <td><div class="role-name-cell"><span class="role-icon"><x-icon name="maintenance" :size="17" /></span><div><strong>{{ $maintenance->maintenance_code }}</strong><small>{{ $maintenance->maintenance_description }}</small></div></div></td>
                            <td>{{ $maintenance->vehicle_group }}<br><small>{{ $maintenance->vehicle_type }}</small></td>
                            <td>{{ $maintenance->maintenance_type === 'distance_policy' ? 'Distance policy' : 'Date policy' }}</td>
                            <td>{{ number_format($maintenance->threshold) }} {{ $maintenance->maintenance_type === 'distance_policy' ? 'km' : 'days' }}</td>
                            <td>{{ number_format($maintenance->threshold_warning) }} {{ $maintenance->maintenance_type === 'distance_policy' ? 'km' : 'days' }}</td>
                            <td><x-status-badge :status="$maintenance->is_active ? 'active' : 'inactive'" /></td>
                            <td><span class="date-label">{{ $maintenance->updated_at?->format('M d, Y') }}</span>@if($maintenance->updater)<br><small>{{ $maintenance->updater->name }}</small>@endif</td>
                            <td class="align-right"><div class="row-actions">
                                @if(auth()->user()->hasPermission('vehicle-maintenances.update'))<a href="{{ route('vehicle-maintenances.edit', $maintenance) }}" class="table-action">Edit</a>@endif
                                @if(auth()->user()->hasPermission('vehicle-maintenances.delete'))<form method="POST" action="{{ route('vehicle-maintenances.destroy', $maintenance) }}" onsubmit="return confirm('Delete this maintenance policy? This action cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-table">No vehicle maintenance policies match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($maintenances->hasPages())<div class="pagination-links">{{ $maintenances->links() }}</div>@endif
    </x-card>

    @if(auth()->user()->hasPermission('vehicle-maintenances.create'))
        <x-card title="Mass Upload Vehicle Maintenance" subtitle="Add new policies or update existing policies by maintenance code.">
            <form method="POST" action="{{ route('vehicle-maintenances.import') }}" enctype="multipart/form-data" class="vehicle-upload-bar">
                @csrf
                <label for="maintenance-upload">Maintenance data file</label>
                <input id="maintenance-upload" name="file" type="file" accept=".xlsx,.csv,.txt" required>
                <button type="submit" class="button button-outline">Upload Policies</button>
            </form>
            <p class="form-hint">Use the XLSX template and keep its headers unchanged. Policy type must be <strong>distance_policy</strong> or <strong>date_policy</strong>. Existing codes are updated atomically; if any row is invalid, nothing is saved.</p>
        </x-card>
    @endif
@endsection
