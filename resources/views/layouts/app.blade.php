<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Fit Generation</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('assets/img/logo.jpg') }}">
    <script>
      (function () {
        try {
          var saved = localStorage.getItem('fg-theme');
          var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
          document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {
          document.documentElement.setAttribute('data-theme', 'light');
        }
      })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('assets/css/modules.css') }}?v=11">
    @if(config('checkin.browser_alerts'))
        <link rel="stylesheet" href="{{ asset('assets/css/checkin-toast.css') }}?v=18">
    @endif
    @stack('styles')
</head>
<body>
@include('partials.sidebar')

<div class="main">
    @include('partials.topbar')

    <main class="content">
        @if(session('success'))
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                <span>{{ session('success') }}</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                <span>{{ session('error') }}</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.credit')
</div>

<script src="{{ asset('assets/js/layout.js') }}?v=4"></script>
<script src="{{ asset('assets/js/searchable-select.js') }}?v=1"></script>
@if(config('checkin.browser_alerts'))
    @include('partials.checkin-live-toast')
    <script src="{{ asset('assets/js/checkin-toast.js') }}?v=27"></script>
@endif
@stack('scripts')
</body>
</html>
