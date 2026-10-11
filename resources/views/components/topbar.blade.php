@props(['title' => 'Dashboard'])
@php
    $user = auth()->user();
    $userName = $user?->name ?? 'Administrator';
    $username = $user?->username ?? 'administrator';
    $roleLabel = $user?->roles->pluck('name')->join(', ') ?: 'No role assigned';
    $maintenanceAlerts = collect();
    if ($user?->hasPermission('maintenances.view')) {
        $maintenanceAlerts = app(\App\Services\MaintenanceAlertService::class)->all();
    }
    $maintenanceAlertCount = $maintenanceAlerts->count();
@endphp

<header class="topbar">
    <div class="topbar-left">
        <button type="button" class="icon-button sidebar-toggle" data-sidebar-toggle aria-label="Toggle navigation" aria-expanded="true">
            <x-icon name="menu" :size="22" />
        </button>
        <h1>{{ $title }}</h1>
    </div>

    <div class="topbar-actions">
        <label class="topbar-search">
            <span class="sr-only">Search</span>
            <x-icon name="search" :size="18" />
            <input type="search" placeholder="Search" aria-label="Search">
            <kbd>Ctrl K</kbd>
        </label>

        <div class="dropdown" data-dropdown>
            <button type="button" class="icon-button notification-button" data-dropdown-toggle aria-label="Notifications{{ $maintenanceAlertCount ? ' — '.$maintenanceAlertCount.' active maintenance alerts' : '' }}" aria-expanded="false">
                <x-icon name="bell" :size="21" />@if($maintenanceAlertCount)<span class="notification-dot"></span>@endif
            </button>
            <div class="dropdown-menu notification-menu" data-dropdown-menu>
                <div class="dropdown-heading"><strong>Maintenance Alerts</strong><span>{{ $maintenanceAlertCount }} active</span></div>
                @forelse($maintenanceAlerts->take(5) as $alert)
                    <a href="{{ route('maintenances.show', $alert) }}">
                        <span class="activity-dot {{ $alert->current_status === 'warning' ? 'warning' : 'violation' }}"></span>
                        <span><strong>Maintenance {{ $alert->current_status }}</strong><small>{{ $alert->vehicle->plate_number }} · {{ $alert->policy->maintenance_code }}<br>{{ $alert->remaining() }}</small></span>
                    </a>
                @empty
                    <div class="notification-empty"><x-icon name="maintenance" :size="20" /><span><strong>No active alerts</strong><small>All vehicle maintenance is within its normal threshold.</small></span></div>
                @endforelse
                @if($user?->hasPermission('maintenances.view'))<a href="{{ route('notifications.index') }}" class="dropdown-footer">View all maintenance alerts</a>@endif
            </div>
        </div>

        <div class="dropdown" data-dropdown>
            <button type="button" class="user-menu-button" data-dropdown-toggle aria-expanded="false">
                <span class="avatar">{{ mb_strtoupper(mb_substr($userName, 0, 1)) }}</span>
                <span class="user-menu-copy"><strong>{{ $userName }}</strong><small>{{ $roleLabel }}</small></span>
                <x-icon name="chevron-down" :size="16" />
            </button>
            <div class="dropdown-menu user-dropdown" data-dropdown-menu>
                <div class="user-dropdown-header"><strong>{{ $userName }}</strong><span>Username: {{ $username }}</span></div>
                <a href="#"><x-icon name="user" :size="17" /> Profile</a>
                <a href="#"><x-icon name="settings" :size="17" /> Settings</a>
                <div class="dropdown-divider"></div>
                @if(Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" :size="17" /> Logout</button></form>
                @else
                    <button type="button"><x-icon name="logout" :size="17" /> Logout</button>
                @endif
            </div>
        </div>
    </div>
</header>
