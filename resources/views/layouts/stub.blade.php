<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Fit Generation' }} — Gym Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>
        .stub-page { padding: 40px; }
        .stub-page h1 { font-size: 22px; margin-bottom: 8px; }
        .stub-page p { color: var(--text-dim); margin-bottom: 20px; }
        .stub-back {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 10px;
            background: linear-gradient(135deg, #2038e0, #1829b8);
            color: #fff; font-weight: 600; font-size: 13px;
        }
    </style>
</head>
<body>
<div class="main" style="margin-left:0;">
    <main class="content stub-page">
        <h1>{{ $title ?? 'Module' }}</h1>
        <p>{{ $message ?? 'This module is scaffolded. CRUD UI will be built next.' }}</p>
        <a class="stub-back" href="{{ route('dashboard') }}">← Back to Dashboard</a>
        @yield('content')
    </main>
</div>
</body>
</html>
