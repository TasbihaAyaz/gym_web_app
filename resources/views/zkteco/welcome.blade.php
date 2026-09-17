<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Display — {{ $gymName }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('assets/img/logo.jpg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/checkin-welcome.css') }}?v=6">
</head>
<body
    data-poll-url="{{ $pollUrl }}"
    data-display-seconds="{{ $displaySeconds }}"
    data-sync-url="{{ route('zkteco.live-sync') }}"
    data-csrf="{{ csrf_token() }}"
>
    <div class="cw-bg" aria-hidden="true">
        <div class="cw-orb cw-orb-a"></div>
        <div class="cw-orb cw-orb-b"></div>
        <div class="cw-grid"></div>
    </div>

    <header class="cw-top">
        <div class="cw-brand">
            <span class="cw-mark"><img src="{{ asset('assets/img/logo.jpg') }}" alt="{{ $gymName }}"></span>
            <div>
                <strong>{{ $gymName }}</strong>
                <small>Biometric Check-in</small>
            </div>
        </div>
        <div class="cw-clock" id="cw-clock">--:--</div>
    </header>

    <main class="cw-idle" id="cw-idle">
        <div class="cw-pulse-ring"></div>
        <div class="cw-fingerprint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                <path d="M12 11c0-1.5-1.2-2.5-2.5-2.5S7 9.5 7 11"/>
                <path d="M12 11v3.5"/>
                <path d="M12 7.5c0-2.5-2-4.5-4.5-4.5S3 5 3 7.5"/>
                <path d="M17 11c0-2.8-2.2-5-5-5"/>
                <path d="M7 14.5c0 2.5 2 4.5 4.5 4.5.8 0 1.5-.2 2.2-.5"/>
                <path d="M17 14.2c-.4 2.6-2.6 4.8-5.3 4.8"/>
                <path d="M19.5 11c0 4.1-3.1 7.5-7.2 7.9"/>
                <path d="M4.5 11c0 1.8.6 3.4 1.6 4.7"/>
            </svg>
        </div>
        <h1>Ready to check in</h1>
        <p>Place your thumb on the ZKTeco K50 scanner</p>
    </main>

    <div class="cw-overlay" id="cw-overlay" hidden>
        <div class="cw-sheet" id="cw-sheet" role="dialog" aria-live="polite">
            <div class="cw-timer-wrap">
                <svg class="cw-timer" viewBox="0 0 120 120" aria-hidden="true">
                    <circle class="cw-timer-track" cx="60" cy="60" r="52"/>
                    <circle class="cw-timer-progress" id="cw-timer-progress" cx="60" cy="60" r="52"/>
                </svg>
                <div class="cw-timer-num" id="cw-timer-num">10</div>
            </div>

            <div class="cw-badge" id="cw-badge">Welcome</div>
            <div class="cw-avatar" id="cw-avatar">FG</div>
            <h2 class="cw-name" id="cw-name">Member</h2>
            <p class="cw-sub" id="cw-sub">Checked in successfully</p>
            <div class="cw-meta">
                <span class="cw-code" id="cw-code">Bio ID —</span>
                <span class="dot"></span>
                <span id="cw-time">--:--</span>
            </div>

            <div class="cw-fee" id="cw-fee" data-status="none">
                <div class="cw-fee-top">
                    <span class="cw-fee-label" id="cw-fee-label">Fee Period</span>
                    <span class="cw-fee-chip" id="cw-fee-chip">—</span>
                </div>
                <div class="cw-fee-dates">
                    <div>
                        <small>From</small>
                        <strong id="cw-fee-start">—</strong>
                    </div>
                    <div class="cw-fee-arrow" aria-hidden="true">→</div>
                    <div>
                        <small>To</small>
                        <strong id="cw-fee-end">—</strong>
                    </div>
                </div>
                <p class="cw-fee-note" id="cw-fee-note"></p>
            </div>
        </div>
    </div>

    @include('partials.credit')

    <script src="{{ asset('assets/js/checkin-welcome.js') }}?v=4"></script>
</body>
</html>
