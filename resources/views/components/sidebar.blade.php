@php
    $sections = [
        'Fleet Operations' => [
            ['label' => 'Personnel', 'icon' => 'users', 'route' => 'personnel.index', 'permission' => 'personnel.view'],
            ['label' => 'Vehicles', 'icon' => 'vehicles', 'route' => 'vehicles.index', 'permission' => 'vehicles.view'],
            ['label' => 'Drivers', 'icon' => 'drivers'],
            ['label' => 'Trips', 'icon' => 'trips'],
            ['label' => 'Maintenance', 'icon' => 'maintenance'],
        ],
        'Document Manifests' => [
            ['label' => 'Pickup', 'icon' => 'reports', 'route' => 'pickup.index', 'permission' => 'pickup.view'],
            ['label' => 'Inbound', 'icon' => 'reports', 'route' => 'inbound.index', 'permission' => 'inbound.view'],
            ['label' => 'Transfer', 'icon' => 'reports', 'route' => 'transfer.index', 'permission' => 'transfer.view'],
            ['label' => 'Delivery', 'icon' => 'reports', 'route' => 'delivery.index', 'permission' => 'delivery.view'],
            ['label' => 'Return', 'icon' => 'reports', 'route' => 'return.index', 'permission' => 'return.view'],
        ],
        'Trip Maintenance' => [
            ['label' => 'Clients', 'icon' => 'user', 'route' => 'clients.index', 'permission' => 'clients.view'],
            ['label' => 'Routes', 'icon' => 'trips', 'route' => 'routes.index', 'permission' => 'routes.view'],
            ['label' => 'Trip Types', 'icon' => 'trips', 'route' => 'trip-types.index', 'permission' => 'trip-types.view'],
            ['label' => 'Other Costs', 'icon' => 'reports', 'route' => 'other-costs.index', 'permission' => 'other-costs.view'],
            ['label' => 'Fuel Suppliers', 'icon' => 'reports', 'route' => 'fuel-suppliers.index', 'permission' => 'fuel-suppliers.view'],
            ['label' => 'Shipping Lines', 'icon' => 'trips', 'route' => 'shipping-lines.index', 'permission' => 'shipping-lines.view'],
            ['label' => 'Trip Payments', 'icon' => 'reports', 'route' => 'trip-payments.index', 'permission' => 'trip-payments.view'],
        ],
        'Tariffs' => [
            ['label' => 'Shipping Rates', 'icon' => 'reports', 'route' => 'shipping-rates.index', 'permission' => 'shipping-rates.view'],
            ['label' => 'Trip Rates', 'icon' => 'reports', 'route' => 'trip-rates.index', 'permission' => 'trip-rates.view'],
        ],
        'Reports' => [
            ['label' => 'Reports', 'icon' => 'reports'],
        ],
        'Configuration' => [
            ['label' => 'Personnel Types', 'icon' => 'users', 'route' => 'personnel-types.index', 'permission' => 'personnel-types.view'],
            ['label' => 'Warehouses', 'icon' => 'warehouse', 'route' => 'warehouses.index', 'permission' => 'warehouses.view'],
            ['label' => 'Vehicle Groups', 'icon' => 'vehicle-group', 'route' => 'vehicle-groups.index', 'permission' => 'vehicle-groups.view'],
            ['label' => 'Vehicle Types', 'icon' => 'vehicles', 'route' => 'vehicle-types.index', 'permission' => 'vehicle-types.view'],
            ['label' => 'Vehicle Makes', 'icon' => 'vehicles', 'route' => 'vehicle-makes.index', 'permission' => 'vehicle-makes.view'],
            ['label' => 'Vehicle Statuses', 'icon' => 'vehicles', 'route' => 'vehicle-statuses.index', 'permission' => 'vehicle-statuses.view'],
        ],
        'System' => [
            ['label' => 'Users', 'icon' => 'user', 'route' => 'users.index', 'permission' => 'users.view'],
            ['label' => 'Roles', 'icon' => 'shield', 'route' => 'roles.index', 'permission' => 'roles.view'],
            ['label' => 'Permissions', 'icon' => 'key', 'route' => 'permissions.index', 'permission' => 'permissions.view'],
            ['label' => 'Settings', 'icon' => 'settings'],
        ],
    ];
    $user = auth()->user();
    $userName = $user?->name ?? 'Administrator';
    $userRole = $user?->roles->pluck('name')->join(', ') ?: 'No role assigned';
@endphp

<aside class="sidebar" data-sidebar aria-label="Primary navigation">
    <div class="sidebar-brand">
        <img
            class="fleet-logo"
            src="{{ asset('storage/images/fleet/acc-v.png') }}"
            alt="Fleet management logo"
        >
        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation"><x-icon name="close" /></button>
    </div>

    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" data-tooltip="Dashboard">
            <x-icon name="dashboard" /><span class="nav-label">Dashboard</span>
        </a>

        @foreach($sections as $section => $items)
            <div class="nav-section">
                <p class="nav-section-label">{{ $section }}</p>
                @foreach($items as $item)
                    @if(!isset($item['permission']) || $user?->hasPermission($item['permission']))
                    <a href="{{ isset($item['route']) ? route($item['route']) : '#' }}" class="nav-item {{ isset($item['route']) && request()->routeIs(Str::before($item['route'], '.').'.*') ? 'is-active' : '' }}" data-tooltip="{{ $item['label'] }}">
                        <x-icon :name="$item['icon']" /><span class="nav-label">{{ $item['label'] }}</span>
                    </a>
                    @endif
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="avatar avatar-small">{{ mb_strtoupper(mb_substr($userName, 0, 1)) }}</span>
            <div class="sidebar-user-copy"><strong>{{ $userName }}</strong><span>{{ $userRole }}</span></div>
        </div>
        @if(Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item logout-button"><x-icon name="logout" /><span class="nav-label">Logout</span></button>
            </form>
        @else
            <button type="button" class="nav-item logout-button"><x-icon name="logout" /><span class="nav-label">Logout</span></button>
        @endif
    </div>
</aside>
