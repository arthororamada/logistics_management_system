<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In &middot; Fleet Management System</title>
    <meta name="description" content="Secure sign in for the fleet management system">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/branding/lms-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <x-notifications />
    <main class="auth-page">
        <section class="auth-identity" aria-label="Fleet management system">
            <div class="auth-identity-content">
                <img src="{{ asset('images/branding/lms-logo.png') }}" alt="Logistics Management System logo" class="auth-logo">
                <div class="auth-identity-copy">
                    <p class="auth-eyebrow">Administration portal</p>
                    <h1>E-Fleet Management System</h1>
                    <p>One secure workspace for managing your fleet operations.</p>
                </div>
                <div class="auth-feature-list" aria-label="System highlights">
                    <div><span><x-icon name="vehicles" :size="20" /></span><p><strong>Centralized fleet control</strong><small>Vehicle and driver information in one organized workspace.</small></p></div>
                    <div><span><x-icon name="maintenance" :size="20" /></span><p><strong>Reliable daily oversight</strong><small>Clear visibility across trips and maintenance.</small></p></div>
                    <div><span><x-icon name="reports" :size="20" /></span><p><strong>Actionable reporting</strong><small>Make informed decisions with confidence.</small></p></div>
                </div>
            </div>
            <p class="auth-identity-footer">Authorized personnel only</p>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <div class="auth-mobile-logo"><img src="{{ asset('images/branding/lms-logo.png') }}" alt="Logistics Management System logo"></div>
                <header class="auth-form-header">
                    <p class="auth-eyebrow">Welcome back</p>
                    <h2>Sign in to your account</h2>
                    <p>Enter your credentials to access the administration portal.</p>
                </header>

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-control @error('username') has-error @enderror">
                            <x-icon name="user" :size="18" />
                            <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autofocus required placeholder="Enter your username">
                        </div>
                        @error('username')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <div class="label-row"><label for="password">Password</label></div>
                        <div class="input-control @error('password') has-error @enderror">
                            <x-icon name="settings" :size="18" />
                            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        @error('password')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <label class="remember-control">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Keep me signed in</span>
                    </label>

                    <button type="submit" class="auth-submit">Sign In <x-icon name="arrow-right" :size="18" /></button>
                </form>

                <p class="auth-help">Having trouble signing in? Contact your system administrator.</p>
            </div>
            <footer class="auth-copyright">&copy; {{ date('Y') }} Logistics Management System. All rights reserved.</footer>
        </section>
    </main>
</body>
</html>
