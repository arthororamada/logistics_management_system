@props(['name', 'size' => 20])

@php
    $paths = [
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'teachers' => '<path d="m3 10 9-5 9 5-9 5-9-5Z"/><path d="M7 12.2V17c3 2 7 2 10 0v-4.8M21 10v6"/>',
        'classes' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'subjects' => '<path d="M3 7h18M6 3h12a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3Z"/><path d="M8 11h8M8 15h5"/>',
        'attendance' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18m-12 5 2 2 4-4"/>',
        'grades' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'announcement' => '<path d="m3 11 18-5v12L3 13v-2Z"/><path d="M11.6 15.4 13 21H7l-1.5-7"/>',
        'reports' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.09 14H3v-4h.09A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.01A1.7 1.7 0 0 0 10 3.09V3h4v.09A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.01A1.7 1.7 0 0 0 20.91 10H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r=".5" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r=".5" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r=".5" fill="currentColor" stroke="none"/>',
        'upload' => '<path d="M12 16V4m-5 5 5-5 5 5M5 20h14"/>',
        'arrow-right' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'school' => '<path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-6h6v6M3 10l9-7 9 7"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8m-2 2 2 2m-5 1 2 2"/>',
        'vehicles' => '<path d="M3 17V8l2-3h12l4 6v6"/><path d="M3 11h18M7 11l2-6M17 11l-2-6"/><circle cx="6.5" cy="17" r="2"/><circle cx="17.5" cy="17" r="2"/>',
        'drivers' => '<circle cx="12" cy="7" r="4"/><path d="M5 21a7 7 0 0 1 14 0M8 15l4 3 4-3"/>',
        'trips' => '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="6" r="3"/><path d="M8.5 16.5c3-1 4.5-3 3-5s0-4 4-4"/>',
        'maintenance' => '<path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 8.4 7.2 6.1 4.9a4 4 0 0 0 5 5L4 17a2.1 2.1 0 0 0 3 3l7.1-7.1a4 4 0 0 0 5-5L16.8 10l-3.6-3.6 1.5-1.5"/>',
        'warehouse' => '<path d="M3 21V8l9-5 9 5v13M3 9l9 5 9-5M8 21v-8h8v8M8 17h8"/>',
        'vehicle-group' => '<path d="M3 16V9l2-3h8l3 5v5"/><circle cx="6" cy="16" r="2"/><circle cx="13" cy="16" r="2"/><path d="M14 7h4l3 4v5M16 16h3"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'icon', 'width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    {!! $paths[$name] ?? $paths['dashboard'] !!}
</svg>
