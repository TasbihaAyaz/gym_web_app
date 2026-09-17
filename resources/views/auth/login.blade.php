<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Fit Generation</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('assets/img/logo.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}?v=4">
</head>
<body class="auth-body">
    <div class="auth-bg" aria-hidden="true">
        <div class="auth-orb auth-orb-a"></div>
        <div class="auth-orb auth-orb-b"></div>
        <div class="auth-grid"></div>
        <div class="auth-glow"></div>
    </div>

    <main class="auth-shell">
        <section class="auth-brand panel-in">
            <div class="brand-mark">
                <img src="{{ asset('assets/img/logo.jpg') }}" alt="Fit Generation">
            </div>
            <p class="brand-eyebrow">Gym Management System</p>
            <h1 class="brand-title">Fit Generation</h1>
            <p class="brand-copy">Train harder. Manage smarter. One dashboard for members, classes, and growth.</p>
            <ul class="brand-points">
                <li><span></span>Member &amp; trainer control</li>
                <li><span></span>Fee payments &amp; reports</li>
                <li><span></span>Classes, attendance &amp; ops</li>
            </ul>
        </section>

        <section class="auth-card panel-in delay">
            <div class="auth-card-head">
                <h2>Welcome back</h2>
                <p>Sign in to continue to your gym dashboard</p>
            </div>

            @if(session('success'))
                <div class="auth-alert success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="auth-alert error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="auth-form" id="login-form">
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email', 'admin@fitgeneration.com') }}"
                            placeholder="you@fitgeneration.com"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="toggle-pass" id="toggle-pass" aria-label="Show password">
                            <svg class="eye-open" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19M1 1l22 22"/><path d="M14.12 14.12A3 3 0 0 1 9.88 9.88"/></svg>
                        </button>
                    </div>
                </div>

                <div class="auth-meta">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Remember me</span>
                    </label>
                    <span class="hint">Demo: password</span>
                </div>

                <button type="submit" class="auth-submit" id="login-btn">
                    <span class="btn-label">Sign In</span>
                    <span class="btn-loader" aria-hidden="true"></span>
                </button>
            </form>

            <div class="auth-foot">
                <span>Protected staff access only</span>
                @include('partials.credit')
            </div>
        </section>
    </main>

    <script>
    (function () {
        const toggle = document.getElementById('toggle-pass');
        const input = document.getElementById('password');
        toggle?.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.classList.toggle('showing', show);
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });

        document.getElementById('login-form')?.addEventListener('submit', function () {
            document.getElementById('login-btn')?.classList.add('loading');
        });
    })();
    </script>
</body>
</html>
