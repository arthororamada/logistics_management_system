@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Dashboard']];
    $vehicleTypes = [
        ['label' => 'Trucks', 'value' => 84, 'percent' => 100],
        ['label' => 'Vans', 'value' => 62, 'percent' => 74],
        ['label' => 'Pickups', 'value' => 48, 'percent' => 57],
        ['label' => 'Trailers', 'value' => 32, 'percent' => 38],
        ['label' => 'Service', 'value' => 14, 'percent' => 17],
        ['label' => 'Other', 'value' => 8, 'percent' => 10],
    ];
    $events = [
        ['month' => 'SEP', 'day' => '08', 'title' => 'Preventive Maintenance', 'meta' => '9:00 AM · Main Depot'],
        ['month' => 'SEP', 'day' => '12', 'title' => 'Driver Safety Briefing', 'meta' => '8:30 AM · Operations Center'],
        ['month' => 'SEP', 'day' => '16', 'title' => 'Fleet Inspection', 'meta' => 'All day · North Yard'],
        ['month' => 'SEP', 'day' => '22', 'title' => 'Registration Renewals', 'meta' => '10:00 AM · Admin Office'],
    ];
    $runningVehicles = (int) ($vehicleStatusCounts['running'] ?? 0);
    $minorRepairVehicles = (int) ($vehicleStatusCounts['minor repair'] ?? 0);
    $majorRepairVehicles = (int) ($vehicleStatusCounts['major repair'] ?? 0);
    $inactiveVehicles = (int) ($vehicleStatusCounts['inactive'] ?? 0);
    $otherVehicleStatuses = max(0, $totalVehicles - ($runningVehicles + $minorRepairVehicles + $majorRepairVehicles + $inactiveVehicles));
@endphp

@section('content')
    <section class="dashboard-intro">
        <div>
            <p class="eyebrow">Fleet overview</p>
            <h2>Welcome back, {{ auth()->user()?->name ?? 'Administrator' }}</h2>
            <p>Here’s an overview of your fleet operations today.</p>
        </div>
        <time datetime="{{ now()->toDateString() }}" class="current-date"><span>Today</span><strong>{{ now()->format('F j, Y') }}</strong></time>
    </section>

    <section class="stats-grid" aria-label="Fleet statistics">
        <x-stat-card title="Total Vehicles" :value="number_format($totalVehicles)" description="across all depots" trend="+6" icon="vehicles" tone="red" />
        <x-stat-card title="Running" :value="number_format($runningVehicles)" description="vehicles in operation" icon="vehicles" :color="$vehicleStatusColors['running'] ?? null" />
        <x-stat-card title="Minor Repair" :value="number_format($minorRepairVehicles)" description="vehicles under minor repair" icon="vehicles" :color="$vehicleStatusColors['minor repair'] ?? null" />
        <x-stat-card title="Major Repair" :value="number_format($majorRepairVehicles)" description="vehicles under major repair" icon="vehicles" tone="red" :color="$vehicleStatusColors['major repair'] ?? null" />
        <x-stat-card title="Inactive" :value="number_format($inactiveVehicles)" description="inactive vehicles" icon="vehicles" :color="$vehicleStatusColors['inactive'] ?? null" />
        <x-stat-card title="Other Statuses" :value="number_format($otherVehicleStatuses)" description="other vehicle statuses" icon="vehicles" />
    </section>

    <div class="dashboard-grid dashboard-grid-primary">
        <x-card title="Fleet Utilization" subtitle="Weekly vehicle utilization rate">
            <x-slot:actions><button class="text-button" type="button">This week <x-icon name="chevron-down" :size="14" /></button></x-slot:actions>
            <div class="chart-legend" aria-label="Chart legend"><span><i class="legend-present"></i>In service</span><span><i class="legend-absent"></i>Maintenance</span><span><i class="legend-late"></i>Available</span></div>
            <div class="attendance-chart" role="img" aria-label="Fleet utilization remained between 82 and 91 percent from Monday to Friday">
                <svg viewBox="0 0 620 235" preserveAspectRatio="none" aria-hidden="true">
                    <g class="chart-grid"><line x1="48" y1="20" x2="600" y2="20"/><line x1="48" y1="94" x2="600" y2="94"/><line x1="48" y1="168" x2="600" y2="168"/></g>
                    <g class="chart-labels"><text x="0" y="25">100%</text><text x="10" y="99">90%</text><text x="10" y="173">80%</text></g>
                    <path class="chart-area" d="M62 129 L194 80 L326 45 L458 70 L590 112 L590 168 L62 168 Z"/>
                    <polyline class="chart-line" points="62,129 194,80 326,45 458,70 590,112"/>
                    <g class="chart-points"><circle cx="62" cy="129" r="5"/><circle cx="194" cy="80" r="5"/><circle cx="326" cy="45" r="5"/><circle cx="458" cy="70" r="5"/><circle cx="590" cy="112" r="5"/></g>
                    <g class="chart-days"><text x="62" y="215">Mon</text><text x="194" y="215">Tue</text><text x="326" y="215">Wed</text><text x="458" y="215">Thu</text><text x="590" y="215">Fri</text></g>
                </svg>
            </div>
        </x-card>

        <x-card title="Vehicles by Type" subtitle="Current fleet distribution">
            <x-slot:actions><span class="card-total">248 vehicles</span></x-slot:actions>
            <div class="grade-bars">
                @foreach($vehicleTypes as $vehicleType)
                    <div class="grade-row"><span>{{ $vehicleType['label'] }}</span><div class="bar-track"><span class="bar-fill" style="--bar-width: {{ $vehicleType['percent'] }}%"></span></div><strong>{{ $vehicleType['value'] }}</strong></div>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="dashboard-grid dashboard-grid-secondary">
        <x-card title="Upcoming Schedule" subtitle="Maintenance and compliance dates">
            <x-slot:actions><button type="button" class="text-link">View schedule <x-icon name="arrow-right" :size="14" /></button></x-slot:actions>
            <div class="event-list">
                @foreach($events as $event)
                    <article class="event-item"><time class="event-date"><span>{{ $event['month'] }}</span><strong>{{ $event['day'] }}</strong></time><div><h3>{{ $event['title'] }}</h3><p>{{ $event['meta'] }}</p></div></article>
                @endforeach
            </div>
        </x-card>

        <x-card title="Recent Activity" subtitle="Latest updates across fleet operations">
            <div class="activity-timeline">
                <article><span class="timeline-marker"></span><div><h3>Vehicle added to fleet</h3><p>Truck FL-248</p><time>10 minutes ago</time></div></article>
                <article><span class="timeline-marker"></span><div><h3>Trip completed</h3><p>TR-1084 · Central Depot</p><time>25 minutes ago</time></div></article>
                <article><span class="timeline-marker"></span><div><h3>Maintenance completed</h3><p>Van FL-117 · Oil and filter service</p><time>1 hour ago</time></div></article>
                <article><span class="timeline-marker"></span><div><h3>Driver assigned</h3><p>Maria Santos · Truck FL-204</p><time>2 hours ago</time></div></article>
            </div>
        </x-card>
    </div>

    <x-card title="Quick Actions" subtitle="Frequently used fleet management tasks" class="quick-actions-card">
        <div class="quick-actions">
            <x-button variant="primary" icon="plus">Add Vehicle</x-button><x-button variant="outline" icon="plus">Add Driver</x-button><x-button variant="outline" icon="plus">Schedule Trip</x-button><x-button variant="outline" icon="plus">Log Maintenance</x-button>
        </div>
    </x-card>
@endsection
