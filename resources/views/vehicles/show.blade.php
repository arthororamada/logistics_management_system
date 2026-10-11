@extends('layouts.app')

@section('title', 'Vehicle Details')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Vehicles', 'url' => route('vehicles.index')], ['label' => $vehicle->plate_number]])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Fleet Operations</p><h2>{{ $vehicle->plate_number }}</h2><p>{{ $vehicle->vehicle_make }} {{ $vehicle->year_model }} · {{ $vehicle->body_number }}</p></div>
        <div class="heading-actions">
            <a class="button button-outline" href="{{ route('vehicles.index') }}">Back to Vehicles</a>
            @if(auth()->user()->hasPermission('maintenances.create'))<a class="button button-outline" href="{{ route('maintenances.create', ['vehicle_id' => $vehicle->id]) }}">Add Maintenance</a>@endif
            @if(auth()->user()->hasPermission('vehicles.update'))<a class="button button-primary" href="{{ route('vehicles.edit', $vehicle) }}">Edit Vehicle</a>@endif
        </div>
    </section>

    <div class="stats-grid vehicle-overview-stats">
        <div class="stat-card"><div class="stat-card-top"><span class="stat-title">Current Odometer</span><span class="stat-icon"><x-icon name="vehicles" :size="19" /></span></div><strong class="stat-value">{{ number_format((float) $vehicle->odometer, 2) }}</strong><span class="stat-meta">Kilometers</span></div>
        <div class="stat-card"><div class="stat-card-top"><span class="stat-title">Vehicle Status</span><span class="stat-icon"><x-icon name="vehicles" :size="19" /></span></div><strong class="vehicle-stat-text">{{ $vehicle->vehicle_status }}</strong><span class="stat-meta">{{ $vehicle->warehouse_code }}</span></div>
        <div class="stat-card"><div class="stat-card-top"><span class="stat-title">Maintenance Warning</span><span class="stat-icon"><x-icon name="maintenance" :size="19" /></span></div><strong class="stat-value">{{ $maintenanceCounts['warning'] }}</strong><span class="stat-meta">Approaching threshold</span></div>
        <div class="stat-card"><div class="stat-card-top"><span class="stat-title">Maintenance Violation</span><span class="stat-icon stat-icon-red"><x-icon name="maintenance" :size="19" /></span></div><strong class="stat-value">{{ $maintenanceCounts['violation'] }}</strong><span class="stat-meta">Due or overdue</span></div>
    </div>

    <div class="vehicle-show-grid">
        <x-card title="Vehicle Identity" subtitle="Fleet classification and identifying information.">
            <div class="detail-grid vehicle-detail-list">
                <div><span class="stat-title">Plate Number</span><strong>{{ $vehicle->plate_number }}</strong></div>
                <div><span class="stat-title">Body Number</span><strong>{{ $vehicle->body_number }}</strong></div>
                <div><span class="stat-title">Warehouse</span><strong>{{ $vehicle->warehouse_code }}</strong></div>
                <div><span class="stat-title">Vehicle Group</span><strong>{{ $vehicle->vehicle_group }}</strong></div>
                <div><span class="stat-title">Vehicle Type</span><strong>{{ $vehicle->vehicle_type }}</strong></div>
                <div><span class="stat-title">Make and Model</span><strong>{{ $vehicle->vehicle_make }} {{ $vehicle->year_model }}</strong></div>
                <div><span class="stat-title">Vehicle Status</span><strong>{{ $vehicle->vehicle_status }}</strong></div>
                <div><span class="stat-title">Date Acquired</span><strong>{{ $vehicle->date_acquired?->format('M d, Y') ?? '—' }}</strong></div>
            </div>
        </x-card>

        <x-card title="Engine and Capacity" subtitle="Technical specifications and operating measurements.">
            <div class="detail-grid vehicle-detail-list">
                <div><span class="stat-title">Engine Number</span><strong>{{ $vehicle->engine_number ?: '—' }}</strong></div>
                <div><span class="stat-title">Chassis Number</span><strong>{{ $vehicle->chassi_number ?: '—' }}</strong></div>
                <div><span class="stat-title">Engine Type</span><strong>{{ $vehicle->engine_type ?: '—' }}</strong></div>
                <div><span class="stat-title">Transmission</span><strong>{{ $vehicle->transmission ?: '—' }}</strong></div>
                <div><span class="stat-title">Fuel Type</span><strong>{{ $vehicle->fuel_type ?: '—' }}</strong></div>
                <div><span class="stat-title">Fuel Capacity</span><strong>{{ number_format((float) $vehicle->fuel_capacity, 2) }}</strong></div>
                <div><span class="stat-title">Fuel Usage / km</span><strong>{{ number_format((float) $vehicle->fuel_usage_per_kilometer, 3) }}</strong></div>
                <div><span class="stat-title">Cargo Capacity</span><strong>{{ number_format((float) $vehicle->cargo_capacity, 2) }}</strong></div>
                <div><span class="stat-title">Battery Size</span><strong>{{ $vehicle->battery_size ?: '—' }}</strong></div>
                <div><span class="stat-title">Tire Size</span><strong>{{ $vehicle->tire_size ?: '—' }}</strong></div>
            </div>
        </x-card>

        <x-card title="Registration and Usage" subtitle="Registration references, service life, and notes." class="vehicle-registration-card">
            <div class="detail-grid vehicle-detail-list">
                <div><span class="stat-title">Registration Number</span><strong>{{ $vehicle->registration_number ?: '—' }}</strong></div>
                <div><span class="stat-title">MV File Number</span><strong>{{ $vehicle->mv_file_number ?: '—' }}</strong></div>
                <div><span class="stat-title">Sunsetting Year</span><strong>{{ $vehicle->sunsetting_year ?: '—' }}</strong></div>
                <div><span class="stat-title">Odometer</span><strong>{{ number_format((float) $vehicle->odometer, 2) }} km</strong></div>
                <div class="vehicle-remarks-detail"><span class="stat-title">Remarks</span><strong>{{ $vehicle->remarks ?: '—' }}</strong></div>
            </div>
        </x-card>
    </div>

    <x-card title="Maintenance Data" subtitle="Live maintenance status based on this vehicle's current odometer and calendar thresholds.">
        <div class="maintenance-summary-grid vehicle-maintenance-summary">
            <div class="maintenance-summary maintenance-summary-normal"><span>Normal</span><strong>{{ $maintenanceCounts['normal'] }}</strong><small>Before warning threshold</small></div>
            <div class="maintenance-summary maintenance-summary-warning"><span>Warning</span><strong>{{ $maintenanceCounts['warning'] }}</strong><small>Maintenance approaching</small></div>
            <div class="maintenance-summary maintenance-summary-violation"><span>Violation</span><strong>{{ $maintenanceCounts['violation'] }}</strong><small>Threshold reached or exceeded</small></div>
        </div>

        <div class="table-wrap">
            <table class="access-table vehicle-maintenance-table">
                <thead><tr><th>Maintenance Policy</th><th>Policy Type</th><th>Last Service</th><th>Warning At</th><th>Due At</th><th>Status</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        @php($status = $maintenance->current_status)
                        <tr>
                            <td><strong>{{ $maintenance->policy->maintenance_code }}</strong><small>{{ $maintenance->policy->maintenance_description }}</small></td>
                            <td>{{ $maintenance->policy_type === 'distance_policy' ? 'Distance policy' : 'Date policy' }}</td>
                            <td>{{ $maintenance->last_service_date->format('M d, Y') }}<small>{{ number_format((float) $maintenance->last_service_odometer, 2) }} km</small></td>
                            @if($maintenance->policy_type === 'distance_policy')
                                <td>{{ number_format((float) $maintenance->warning_odometer, 2) }} km</td>
                                <td>{{ number_format((float) $maintenance->next_due_odometer, 2) }} km</td>
                            @else
                                <td>{{ $maintenance->warning_date?->format('M d, Y') }}</td>
                                <td>{{ $maintenance->next_due_date?->format('M d, Y') }}</td>
                            @endif
                            <td><span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span><small>{{ $maintenance->remaining() }}</small></td>
                            <td class="align-right"><a class="table-action" href="{{ route('maintenances.show', $maintenance) }}">View / Renew</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-table"><strong>No maintenance assigned to this vehicle.</strong>@if(auth()->user()->hasPermission('maintenances.create'))<small><a href="{{ route('maintenances.create', ['vehicle_id' => $vehicle->id]) }}">Create the first maintenance cycle</a></small>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
