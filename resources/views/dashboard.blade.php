@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Dashboard']];
    $maximumStatus = max(1, $vehicleStatuses->max('total') ?? 0);
@endphp

@section('content')
    <section class="dashboard-intro">
        <div>
            <p class="eyebrow">Fleet overview</p>
            <h2>Welcome back, {{ auth()->user()?->name ?? 'Administrator' }}</h2>
            <p>Current fleet, maintenance, dispatch, and trip analytics.</p>
        </div>
        <time datetime="{{ now()->toDateString() }}" class="current-date"><span>Today</span><strong>{{ now()->format('F j, Y') }}</strong></time>
    </section>

    <section class="stats-grid" aria-label="Operations statistics">
        <x-stat-card title="Total Vehicles" :value="number_format($totalVehicles)" description="across all vehicle statuses" icon="vehicles" />
        <x-stat-card title="Maintenance Violations" :value="number_format($maintenanceVehicles['violation'])" description="vehicles with overdue policies" icon="vehicles" tone="red" />
        <x-stat-card title="Maintenance Warnings" :value="number_format($maintenanceVehicles['warning'])" description="vehicles nearing policy limits" icon="vehicles" color="#9A5B0A" />
        <x-stat-card title="Dispatched Vehicles" :value="number_format($dispatchedVehicles)" description="distinct vehicles on dispatched trips" icon="vehicles" />
        <x-stat-card title="Manifests for Dispatch" :value="number_format($pendingManifestTotal)" description="document manifests awaiting dispatch" icon="dashboard" />
    </section>

    <div class="dashboard-grid">
        <x-card title="Vehicles by Status" subtitle="Number of vehicles in each vehicle status">
            <div class="grade-bars analytics-bars">
                @forelse($vehicleStatuses as $status)
                    <div class="grade-row">
                        <span>{{ $status['label'] }}</span>
                        <div class="bar-track"><span class="bar-fill" style="--bar-width: {{ $status['total'] * 100 / $maximumStatus }}%; --bar-color: {{ $status['color'] ?: '#0B1F3A' }}"></span></div>
                        <strong>{{ number_format($status['total']) }}</strong>
                    </div>
                @empty
                    <p class="analytics-note">No vehicle statuses available.</p>
                @endforelse
            </div>
        </x-card>
        <x-card title="Maintenance Policies" subtitle="Distinct vehicles with active policy alerts">
            <div class="grade-bars analytics-bars">
                @foreach(['violation' => 'Violation', 'warning' => 'Warning'] as $status => $label)
                    <div class="grade-row">
                        <span>{{ $label }}</span>
                        <div class="bar-track"><span class="bar-fill" style="--bar-width: {{ $maintenanceVehicles[$status] * 100 / max(1, $totalVehicles) }}%; --bar-color: {{ $status === 'violation' ? '#8B1E2D' : '#9A5B0A' }}"></span></div>
                        <strong>{{ number_format($maintenanceVehicles[$status]) }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="analytics-note">A vehicle with both warning and violation policies appears in both counts. Multiple policies of the same severity count once.</p>
        </x-card>
    </div>

    <div class="dashboard-grid dashboard-grid-secondary">
        <x-card title="Vehicles by Current Trip Status" subtitle="Distinct assigned vehicles for each current trip status">
            <div class="grade-bars analytics-bars">
                @foreach(collect(['Open', 'Dispatched', 'Completed', 'Cancelled'])->merge($tripVehicleCounts->keys())->unique() as $status)
                    @php($count = (int) ($tripVehicleCounts[$status] ?? 0))
                    <div class="grade-row">
                        <span>{{ $status }}</span>
                        <div class="bar-track"><span class="bar-fill" style="--bar-width: {{ $count * 100 / max(1, $tripVehicleCounts->max() ?? 0) }}%; --bar-color: #0B1F3A"></span></div>
                        <strong>{{ number_format($count) }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="analytics-note">A vehicle may appear under several statuses across its trips. Dispatched vehicles are counted only from trips currently marked Dispatched.</p>
        </x-card>
        <x-card title="Document Manifests for Dispatch" subtitle="Created manifests with no dispatch details">
            <div class="grade-bars analytics-bars">
                @foreach($pendingManifests as $module => $count)
                    <div class="grade-row">
                        <span>{{ $module }}</span>
                        <div class="bar-track"><span class="bar-fill" style="--bar-width: {{ $count * 100 / max(1, max($pendingManifests)) }}%; --bar-color: #8B1E2D"></span></div>
                        <strong>{{ number_format($count) }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="analytics-note">Inbound accepts packages directly and does not create document manifests.</p>
        </x-card>
    </div>

    <form method="GET" action="{{ route('dashboard') }}" class="analytics-filters">
        <div><label for="analytics-month">Daily chart month</label><input id="analytics-month" type="month" name="month" value="{{ $selectedMonth }}" required></div>
        <div><label for="analytics-year">Monthly chart year</label><input id="analytics-year" type="number" name="year" value="{{ $selectedYear }}" min="2000" max="2100" required></div>
        <x-button type="submit" variant="primary">Update charts</x-button>
    </form>
    <div class="dashboard-grid analytics-trip-grid">
        <x-card title="Completed Trips Daily" :subtitle="\Carbon\Carbon::createFromFormat('!Y-m', $selectedMonth)->format('F Y').' · by completion date'">
            <x-trip-line-chart :points="$dailyTrips" label="Daily completed trips" />
        </x-card>
        <x-card title="Completed Trips Monthly" :subtitle="$selectedYear.' · by completion date'">
            <x-trip-line-chart :points="$monthlyTrips" label="Monthly completed trips" />
        </x-card>
    </div>
@endsection
