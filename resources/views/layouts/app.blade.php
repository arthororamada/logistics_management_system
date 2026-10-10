<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Fleet Management System</title>
    <meta name="description" content="Fleet management system">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell" data-app-shell>
        <x-sidebar />
        <div class="sidebar-overlay" data-sidebar-overlay aria-hidden="true"></div>

        <div class="app-main">
            <x-topbar :title="trim($__env->yieldContent('title', 'Dashboard'))" />
            <x-breadcrumb :items="$breadcrumbs ?? [['label' => 'Home'], ['label' => trim($__env->yieldContent('title', 'Dashboard'))]]" />

            <main class="page-content" id="main-content">
                @if(session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
