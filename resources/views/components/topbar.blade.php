@props(['title' => 'Dashboard'])
@php
    $user = auth()->user();
    $userName = $user?->name ?? 'Administrator';
    $username = $user?->username ?? 'administrator';
    $roleLabel = $user?->roles->pluck('name')->join(', ') ?: 'No role assigned';
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
            <button type="button" class="icon-button notification-button" data-dropdown-toggle aria-label="Notifications" aria-expanded="false">
                <x-icon name="bell" :size="21" /><span class="notification-dot"></span>
            </button>
            <div class="dropdown-menu notification-menu" data-dropdown-menu>
                <div class="dropdown-heading"><strong>Notifications</strong><span>3 new</span></div>
                <a href="#"><span class="activity-dot"></span><span><strong>Maintenance reminder</strong><small>Truck FL-204 is due for service &middot; 8 min ago</small></span></a>
                <a href="#"><span class="activity-dot"></span><span><strong>Trip dispatched</strong><small>Trip TR-1087 left the main depot &middot; 24 min ago</small></span></a>
                <a href="#"><span class="activity-dot muted"></span><span><strong>License renewal</strong><small>Two driver licenses expire this month</small></span></a>
                <button type="button" class="dropdown-footer">Mark all as read</button>
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
