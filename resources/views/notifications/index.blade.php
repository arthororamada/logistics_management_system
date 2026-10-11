@extends('layouts.app')

@section('title', 'Notifications')

@php($breadcrumbs = [['label' => 'Home', 'url' => route('dashboard')], ['label' => 'Fleet Operations'], ['label' => 'Notifications']])

@section('content')
    <section class="page-heading">
        <div><p class="eyebrow">Fleet Operations</p><h2>Maintenance Notifications</h2><p>Live warning and violation alerts calculated from vehicle odometers and maintenance due dates.</p></div>
        <a href="{{ route('maintenances.index') }}" class="button button-outline">Open Maintenance</a>
    </section>

    <div class="maintenance-summary-grid notification-summary-grid">
        <a href="{{ route('notifications.index', ['status' => 'warning']) }}" class="maintenance-summary maintenance-summary-warning"><span>Warnings</span><strong>{{ $counts['warning'] }}</strong><small>Maintenance threshold approaching</small></a>
        <a href="{{ route('notifications.index', ['status' => 'violation']) }}" class="maintenance-summary maintenance-summary-violation"><span>Violations</span><strong>{{ $counts['violation'] }}</strong><small>Maintenance due or overdue</small></a>
    </div>

    <x-card title="Active Maintenance Alerts" subtitle="Alerts clear automatically after the corresponding maintenance is renewed.">
        <form method="GET" action="{{ route('notifications.index') }}" class="filter-bar">
            <label class="filter-search"><x-icon name="search" :size="17" /><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search plate, body number, or policy"></label>
            <select name="status" aria-label="Filter notification status"><option value="">Warnings and violations</option><option value="warning" @selected(($filters['status'] ?? '') === 'warning')>Warning only</option><option value="violation" @selected(($filters['status'] ?? '') === 'violation')>Violation only</option></select>
            <button type="submit" class="button button-outline">Filter</button>
            @if(array_filter($filters))<a href="{{ route('notifications.index') }}" class="clear-filter">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="access-table notification-table">
                <thead><tr><th>Alert</th><th>Vehicle</th><th>Maintenance Policy</th><th>Current Reading</th><th>Warning At</th><th>Due At</th><th class="align-right">Actions</th></tr></thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr class="notification-row notification-row-{{ $notification->current_status }}">
                            <td><span class="status-badge status-{{ $notification->current_status }}">{{ ucfirst($notification->current_status) }}</span><small>{{ $notification->remaining() }}</small></td>
                            <td><strong>{{ $notification->vehicle->plate_number }}</strong><small>{{ $notification->vehicle->body_number }}</small><small>{{ $notification->vehicle->vehicle_group }} / {{ $notification->vehicle->vehicle_type }}</small></td>
                            <td><strong>{{ $notification->policy->maintenance_code }}</strong><small>{{ $notification->policy->maintenance_description }}</small></td>
                            @if($notification->policy_type === 'distance_policy')
                                <td>{{ number_format((float) $notification->vehicle->odometer, 2) }} km</td>
                                <td>{{ number_format((float) $notification->warning_odometer, 2) }} km</td>
                                <td>{{ number_format((float) $notification->next_due_odometer, 2) }} km</td>
                            @else
                                <td>{{ now()->format('M d, Y') }}</td>
                                <td>{{ $notification->warning_date?->format('M d, Y') }}</td>
                                <td>{{ $notification->next_due_date?->format('M d, Y') }}</td>
                            @endif
                            <td class="align-right"><a href="{{ route('maintenances.show', $notification) }}" class="table-action">View / Renew</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-table"><strong>No active maintenance alerts.</strong><small>All assigned vehicle maintenance is currently within its normal threshold.</small></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($notifications->hasPages())<div class="pagination-links">{{ $notifications->links() }}</div>@endif
    </x-card>
@endsection
