@extends('layouts.app')

@section('title', 'Maintenance')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Maintenance']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Fleet Operations</p><h2>Maintenance</h2><p>Monitor vehicle maintenance thresholds, warnings, violations, and renewals.</p></div>
        @if(auth()->user()->hasPermission('maintenances.create'))<x-button href="{{ route('maintenances.create') }}" variant="primary" icon="plus">Create Vehicle Maintenance</x-button>@endif
    </section>

    <div class="maintenance-summary-grid">
        <a href="{{ route('maintenances.index', ['status' => 'normal']) }}" class="maintenance-summary maintenance-summary-normal"><span>Normal</span><strong>{{ $statusCounts['normal'] }}</strong><small>Before warning threshold</small></a>
        <a href="{{ route('maintenances.index', ['status' => 'warning']) }}" class="maintenance-summary maintenance-summary-warning"><span>Warning</span><strong>{{ $statusCounts['warning'] }}</strong><small>Maintenance approaching</small></a>
        <a href="{{ route('maintenances.index', ['status' => 'violation']) }}" class="maintenance-summary maintenance-summary-violation"><span>Violation</span><strong>{{ $statusCounts['violation'] }}</strong><small>Threshold reached or exceeded</small></a>
    </div>

    <x-card title="Vehicle Maintenance Directory" subtitle="Statuses are recalculated from the latest vehicle odometer and current date.">
        <form method="GET" action="{{ route('maintenances.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Plate, body number, code, or description"></label>
            <select name="policy_type" aria-label="Filter by policy type"><option value="">All policy types</option><option value="distance_policy" @selected(($filters['policy_type'] ?? '') === 'distance_policy')>Distance policy</option><option value="date_policy" @selected(($filters['policy_type'] ?? '') === 'date_policy')>Date policy</option></select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="normal" @selected(($filters['status'] ?? '') === 'normal')>Normal</option><option value="warning" @selected(($filters['status'] ?? '') === 'warning')>Warning</option><option value="violation" @selected(($filters['status'] ?? '') === 'violation')>Violation</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('maintenances.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table maintenance-table">
                <thead><tr><th>Vehicle</th><th>Maintenance Policy</th><th>Last Service</th><th>Warning At</th><th>Due At</th><th>Status</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        @php($status = $maintenance->current_status)
                        <tr>
                            <td><strong>{{ $maintenance->vehicle->plate_number }}</strong><small>{{ $maintenance->vehicle->body_number }} · {{ $maintenance->vehicle->vehicle_group }} / {{ $maintenance->vehicle->vehicle_type }}</small><small>Current: {{ number_format((float) $maintenance->vehicle->odometer, 2) }} km</small></td>
                            <td><strong>{{ $maintenance->policy->maintenance_code }}</strong><small>{{ $maintenance->policy->maintenance_description }}</small><small>{{ $maintenance->policy_type === 'distance_policy' ? 'Distance policy' : 'Date policy' }}</small></td>
                            <td>{{ $maintenance->last_service_date->format('M d, Y') }}<small>{{ number_format((float) $maintenance->last_service_odometer, 2) }} km</small></td>
                            @if($maintenance->policy_type === 'distance_policy')
                                <td>{{ number_format((float) $maintenance->warning_odometer, 2) }} km</td>
                                <td>{{ number_format((float) $maintenance->next_due_odometer, 2) }} km</td>
                            @else
                                <td>{{ $maintenance->warning_date?->format('M d, Y') }}</td>
                                <td>{{ $maintenance->next_due_date?->format('M d, Y') }}</td>
                            @endif
                            <td><span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span><small>{{ $maintenance->remaining() }}</small></td>
                            <td class="align-right"><a href="{{ route('maintenances.show', $maintenance) }}" class="table-action">View / Renew</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-table">No vehicle maintenance cycles match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($maintenances->hasPages())<div class="pagination-links">{{ $maintenances->links() }}</div>@endif
    </x-card>
@endsection
