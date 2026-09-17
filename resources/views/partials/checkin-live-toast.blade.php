{{-- Live biometric check-in alerts (web push + toast fallback) --}}
@php
    $checkinSound = voice_asset('fit-gen.mp3');
    $expiredSound = voice_asset('fee-expired.mp3');
    $swUrl = parse_url(route('webpush.sw'), PHP_URL_PATH) ?: '/sw.js';
    $swScope = rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/') . '/';
    $pushUrl = parse_url(route('push.store'), PHP_URL_PATH) ?: '/push-subscriptions';
    $pushDeleteUrl = parse_url(route('push.destroy'), PHP_URL_PATH) ?: '/push-subscriptions';
@endphp
<div
  id="ci-live-root"
  data-poll-url="{{ route('zkteco.poll-checkin') }}"
  data-sync-url="{{ route('zkteco.live-sync') }}"
  data-status-url="{{ route('zkteco.status') }}"
  data-csrf="{{ csrf_token() }}"
  data-sound-url="{{ $checkinSound }}"
  data-expired-sound-url="{{ $expiredSound }}"
  data-vapid-key="{{ config('webpush.vapid.public_key') }}"
  data-push-url="{{ $pushUrl }}"
  data-push-delete-url="{{ $pushDeleteUrl }}"
  data-sw-url="{{ $swUrl }}"
  data-sw-scope="{{ $swScope }}"
  data-poll-ms="300"
  data-sync-ms="2500"
  data-status-ms="5000"
  hidden
></div>

{{-- Keep audio outside hidden toast so the browser can load it --}}
<audio id="ci-checkin-audio" preload="auto" playsinline src="{{ $checkinSound }}"></audio>
<audio id="ci-expired-audio" preload="auto" playsinline src="{{ $expiredSound }}"></audio>

{{-- Shown until the browser allows check-in alerts (push or sound) --}}
<button type="button" id="ci-enable-push" class="ci-enable-push" hidden title="Click once to enable check-in alerts">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  <span>Enable check-in alerts</span>
</button>

<aside class="ci-toast-root" id="ci-toast-root" hidden aria-live="polite">
  <div class="ci-toast" id="ci-toast" role="status">
    <button type="button" class="ci-toast-close" id="ci-toast-close" title="Close" aria-label="Close">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <div class="ci-toast-top">
      <div class="ci-toast-avatar" id="ci-avatar">FG</div>
      <div class="ci-toast-copy">
        <div class="ci-toast-badge" id="ci-badge">Welcome</div>
        <div class="ci-toast-name" id="ci-name">Member</div>
        <div class="ci-toast-sub" id="ci-sub">Checked in successfully</div>
        <div class="ci-toast-meta">
          <span class="ci-toast-code" id="ci-code">Bio ID —</span>
          <span aria-hidden="true">·</span>
          <span id="ci-time">--:--</span>
        </div>
      </div>
    </div>
    <div class="ci-toast-body" id="ci-fee" data-status="none">
      <div class="ci-toast-row">
        <span class="ci-toast-k">Package</span>
        <strong id="ci-package">—</strong>
      </div>
      <div class="ci-toast-row">
        <span class="ci-toast-k">Fee status</span>
        <span class="ci-toast-fee-chip" id="ci-fee-chip">—</span>
      </div>
      <div class="ci-toast-row">
        <span class="ci-toast-k">Valid</span>
        <span id="ci-fee-range">—</span>
      </div>
      <div class="ci-toast-row" id="ci-pending-row" hidden>
        <span class="ci-toast-k">Fee pending</span>
        <strong id="ci-pending" class="ci-pending-amt">—</strong>
      </div>
      <p class="ci-toast-note" id="ci-fee-note"></p>
    </div>
  </div>
</aside>
