(function () {
  const root = document.getElementById('ci-live-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const syncUrl = root.dataset.syncUrl;
  const statusUrl = root.dataset.statusUrl;
  const csrf = root.dataset.csrf;
  const soundUrl = root.dataset.soundUrl || '';
  const expiredSoundUrl = root.dataset.expiredSoundUrl || '';
  const vapidKey = root.dataset.vapidKey || '';
  const pushUrl = root.dataset.pushUrl || '';
  const swUrl = root.dataset.swUrl || '';
  const swScope = root.dataset.swScope || './';
  const pollMs = Math.max(250, parseInt(root.dataset.pollMs || '300', 10) || 300);
  const syncMs = Math.max(1500, parseInt(root.dataset.syncMs || '2500', 10) || 2500);
  const statusMs = Math.max(3000, parseInt(root.dataset.statusMs || '5000', 10) || 5000);

  const audioEl = document.getElementById('ci-checkin-audio');
  const expiredAudioEl = document.getElementById('ci-expired-audio');
  const enableBtn = document.getElementById('ci-enable-push');
  const overlay = document.getElementById('ci-toast-root');
  const toast = document.getElementById('ci-toast');
  const closeBtn = document.getElementById('ci-toast-close');

  if (!overlay || !toast) return;

  const els = {
    liveStatus: document.getElementById('ci-live-status'),
    badge: document.getElementById('ci-badge'),
    name: document.getElementById('ci-name'),
    sub: document.getElementById('ci-sub'),
    code: document.getElementById('ci-code'),
    time: document.getElementById('ci-time'),
    avatar: document.getElementById('ci-avatar'),
    fee: document.getElementById('ci-fee'),
    package: document.getElementById('ci-package'),
    feeChip: document.getElementById('ci-fee-chip'),
    feeRange: document.getElementById('ci-fee-range'),
    pendingRow: document.getElementById('ci-pending-row'),
    pending: document.getElementById('ci-pending'),
    feeNote: document.getElementById('ci-fee-note'),
  };

  let afterId = 0;
  let ready = false;
  let pollBusy = false;
  let syncBusy = false;
  let activeAudio = null;
  let soundToken = 0;
  let soundWarmed = false;
  let pushReady = false;
  let presentingId = null;
  let hideToken = 0;

  const READ_KEY = 'fg-checkin-read-id';

  function getReadId() {
    try {
      return Number(localStorage.getItem(READ_KEY) || 0) || 0;
    } catch (_) {
      return 0;
    }
  }

  /** Persist last shown/acknowledged punch so other pages do not replay it. */
  function markRead(id) {
    const n = Number(id) || 0;
    if (!n) return;
    afterId = Math.max(afterId, n);
    try {
      if (n > getReadId()) localStorage.setItem(READ_KEY, String(n));
    } catch (_) {}
  }

  function canUseWebPush() {
    return !!(
      window.isSecureContext
      && 'serviceWorker' in navigator
      && 'PushManager' in window
      && 'Notification' in window
      && vapidKey
      && swUrl
      && pushUrl
    );
  }

  function setEnableUi(ok, label) {
    if (ok) soundWarmed = true;
    if (!enableBtn) return;
    enableBtn.hidden = !!ok;
    if (label) {
      const span = enableBtn.querySelector('span');
      if (span) span.textContent = label;
    }
  }

  function isExpiredFee(punch) {
    return !!(punch && punch.fee && punch.fee.status === 'expired');
  }

  function sameAudioFile(currentSrc, nextSrc) {
    if (!currentSrc || !nextSrc) return false;
    const a = String(currentSrc).split('?')[0];
    const b = String(nextSrc).split('?')[0];
    if (a === b) return true;
    try {
      const absA = new URL(a, window.location.href).pathname;
      const absB = new URL(b, window.location.href).pathname;
      return absA === absB;
    } catch (_) {
      return a.endsWith(b) || b.endsWith(a);
    }
  }

  function resolveSoundSrc(punch) {
    if (isExpiredFee(punch)) {
      if (expiredSoundUrl) return expiredSoundUrl;
      if (expiredAudioEl && expiredAudioEl.getAttribute('src')) {
        return expiredAudioEl.getAttribute('src');
      }
    }
    // Prefer the preloaded page sound URL (keeps autoplay unlock intact)
    if (soundUrl) return soundUrl;
    if (punch && punch.sound) return punch.sound;
    if (audioEl && audioEl.currentSrc) return audioEl.currentSrc;
    if (audioEl && audioEl.getAttribute('src')) return audioEl.getAttribute('src');
    return '';
  }

  function audioElementFor(punch) {
    return isExpiredFee(punch) ? (expiredAudioEl || audioEl) : audioEl;
  }

  function stopAllSounds() {
    soundToken += 1;
    [audioEl, expiredAudioEl, activeAudio].forEach((a) => {
      if (!a) return;
      try {
        a.onended = null;
        a.pause();
        try { a.currentTime = 0; } catch (_) {}
      } catch (_) {}
    });
    activeAudio = null;
  }

  function playCheckinSound(punch) {
    const src = resolveSoundSrc(punch);
    if (!src) {
      console.warn('[checkin] no sound src');
      setEnableUi(false, 'Enable check-in sound');
      return;
    }

    const el = audioElementFor(punch);
    const token = ++soundToken;

    const onOk = () => {
      if (token !== soundToken) return;
      soundWarmed = true;
      setEnableUi(true);
    };
    const onFail = (err) => {
      if (token !== soundToken) return;
      console.warn('[checkin] sound play blocked', err);
      soundWarmed = false;
      setEnableUi(false, 'Enable check-in sound');
    };

    const tryPlay = (node, reload) => {
      try {
        if (reload || !sameAudioFile(node.getAttribute('src') || node.currentSrc || '', src)) {
          node.src = src;
        }
        node.muted = false;
        node.volume = 1;
        try { node.currentTime = 0; } catch (_) {}
        const p = node.play();
        if (p && typeof p.then === 'function') {
          return p.then(onOk).catch(() => {
            if (token !== soundToken) return;
            const a = new Audio(src);
            a.volume = 1;
            activeAudio = a;
            return a.play().then(onOk).catch(onFail);
          });
        }
        onOk();
      } catch (err) {
        onFail(err);
      }
    };

    if (el) {
      tryPlay(el, false);
      return;
    }

    const a = new Audio(src);
    a.volume = 1;
    activeAudio = a;
    a.play().then(onOk).catch(onFail);
  }

  function unlockAudio(playSample) {
    if (!playSample && soundWarmed) return Promise.resolve(true);
    const src = soundUrl || resolveSoundSrc(null);
    if (!src) return Promise.resolve(false);

    return new Promise((resolve) => {
      try {
        const a = audioEl || new Audio(src);
        if (audioEl && !sameAudioFile(audioEl.getAttribute('src') || '', src)) {
          audioEl.src = src;
        }

        const finishOk = () => {
          soundWarmed = true;
          resolve(true);
        };
        const finishFail = () => resolve(false);

        if (playSample) {
          a.muted = false;
          a.volume = 1;
          try { a.currentTime = 0; } catch (_) {}
          const p = a.play();
          if (p && typeof p.then === 'function') {
            p.then(() => {
              a.pause();
              try { a.currentTime = 0; } catch (_) {}
              finishOk();
            }).catch(finishFail);
          } else {
            finishOk();
          }
          return;
        }

        // Silent unlock — must unmute afterward or later plays stay silent
        a.muted = true;
        const p = a.play();
        if (p && typeof p.then === 'function') {
          p.then(() => {
            a.pause();
            try { a.currentTime = 0; } catch (_) {}
            a.muted = false;
            finishOk();
          }).catch(() => {
            a.muted = false;
            finishFail();
          });
        } else {
          a.muted = false;
          finishOk();
        }
      } catch (_) {
        resolve(false);
      }
    });
  }

  function money(n) {
    const num = Number(n || 0);
    return 'Rs ' + num.toLocaleString(undefined, { maximumFractionDigits: 0 });
  }

  function feeNote(fee) {
    if (!fee || fee.status === 'none') return 'No package / fee period on file.';
    if (fee.status === 'expired') {
      const days = Math.abs(fee.days_left || 0);
      return days <= 1 ? 'Fee expired — renewal pending.' : `Fee expired ${days} days ago — pending renewal.`;
    }
    if (fee.status === 'expiring') {
      const days = fee.days_left ?? 0;
      return days === 0 ? 'Fee expires today — please renew.' : `Fee expires in ${days} day${days === 1 ? '' : 's'}.`;
    }
    if ((fee.pending || 0) > 0) {
      return `Outstanding balance ${money(fee.pending)}.`;
    }
    const days = fee.days_left ?? 0;
    return days ? `${days} days remaining on current package.` : 'Fee is currently active.';
  }

  function cssUrl(url) {
    if (!url || typeof url !== 'string') return '';
    if (/["')\\\s]/.test(url)) return '';
    return 'url("' + url.replace(/\\/g, '') + '")';
  }

  function fill(punch) {
    const member = punch.member || {};
    const fee = punch.fee || {};
    const status = fee.status || 'none';

    if (els.badge) {
      els.badge.textContent = punch.headline || 'Welcome';
      els.badge.classList.toggle('out', punch.type === 'check_out');
    }
    if (els.name) els.name.textContent = member.name || 'Member';
    if (els.sub) els.sub.textContent = punch.subline || '';
    if (els.code) {
      const bio = member.bio_id || '';
      els.code.textContent = bio ? ('Bio ID ' + bio) : 'Bio ID —';
    }
    if (els.time) els.time.textContent = punch.punched_at || '';

    if (els.avatar) {
      const bg = cssUrl(member.avatar);
      if (bg) {
        els.avatar.textContent = '';
        els.avatar.style.backgroundImage = bg;
      } else {
        els.avatar.style.backgroundImage = '';
        els.avatar.textContent = member.initials || 'FG';
      }
    }

    if (els.fee) els.fee.dataset.status = status;
    toast.dataset.fee = status;
    if (els.package) els.package.textContent = fee.plan || 'No package';
    if (els.feeChip) els.feeChip.textContent = fee.label || '—';
    if (els.feeRange) {
      els.feeRange.textContent = (fee.start && fee.end) ? (fee.start + ' → ' + fee.end) : '—';
    }

    const pending = Number(fee.pending || 0);
    if (els.pendingRow && els.pending) {
      if (status === 'expired' || pending > 0) {
        els.pendingRow.hidden = false;
        els.pending.textContent = pending > 0
          ? money(pending)
          : (fee.plan_price ? money(fee.plan_price) + ' (renew)' : 'Renewal due');
      } else {
        els.pendingRow.hidden = true;
      }
    }

    if (els.feeNote) els.feeNote.textContent = feeNote(fee);
  }

  function hideToast() {
    const token = ++hideToken;
    presentingId = null;
    toast.classList.add('is-out');
    setTimeout(() => {
      if (token !== hideToken) return;
      overlay.hidden = true;
      toast.classList.remove('is-out');
    }, 250);
  }

  function memberKey(punch) {
    if (!punch || !punch.member) return '';
    if (punch.member.id != null && punch.member.id !== '') return 'id:' + String(punch.member.id);
    if (punch.member.bio_id) return 'bio:' + String(punch.member.bio_id);
    if (punch.member.code) return 'code:' + String(punch.member.code);
    return 'name:' + String(punch.member.name || '');
  }

  /** Always show the in-page check-in slide (web push is additive). */
  function show(punch) {
    if (!punch || !punch.member) return;

    const id = Number(punch.id) || 0;
    if (!id || !memberKey(punch)) return;
    if (id <= getReadId()) {
      markRead(id);
      return;
    }
    if (presentingId === id) return;

    // Mark read immediately so a page change cannot replay this punch
    markRead(id);

    hideToken += 1;
    presentingId = id;

    try {
      fill(punch);
    } catch (err) {
      console.warn('[checkin] fill failed', err);
    }

    overlay.hidden = false;
    toast.classList.remove('is-out');
    toast.style.animation = 'none';
    void toast.offsetWidth;
    toast.style.animation = 'ci-card-in .28s cubic-bezier(.2, .85, .2, 1) forwards';

    // Stop prior clip, then play (keep same src so autoplay unlock survives)
    stopAllSounds();
    // Play on next tick so pause() from stopAllSounds has settled
    setTimeout(() => {
      if (presentingId !== id) return;
      playCheckinSound(punch);
    }, 0);
  }

  function applyPunches(punches) {
    if (!punches || !punches.length) return;

    const alreadyRead = getReadId();
    let latest = null;
    let maxId = afterId;

    for (let i = 0; i < punches.length; i++) {
      const punch = punches[i];
      const id = Number(punch && punch.id) || 0;
      if (!id || id <= afterId) continue;
      maxId = Math.max(maxId, id);
      if (id > alreadyRead && memberKey(punch)) latest = punch;
    }

    afterId = Math.max(afterId, maxId);

    if (latest) {
      show(latest);
    }

    // Persist full batch cursor so navigation cannot replay any of these ids
    if (maxId > getReadId()) {
      markRead(maxId);
    }
  }

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  async function saveSubscription(subscription) {
    const res = await fetch(pushUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify(subscription.toJSON()),
    });
    if (!res.ok) throw new Error('Could not save push subscription');
  }

  async function subscribePush() {
    if (!canUseWebPush()) return false;

    const permission = Notification.permission === 'granted'
      ? 'granted'
      : await Notification.requestPermission();

    if (permission !== 'granted') return false;

    const reg = await navigator.serviceWorker.register(swUrl, { scope: swScope });
    await navigator.serviceWorker.ready;

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey),
      });
    }

    await saveSubscription(sub);
    pushReady = true;
    setEnableUi(true);
    return true;
  }

  function punchFromPushPayload(payload) {
    if (!payload) return null;
    if (payload.data && payload.data.punch) return payload.data.punch;
    if (payload.punch) return payload.punch;
    return null;
  }

  function setStatus(connected, label, title) {
    const el = els.liveStatus;
    if (!el) return;
    el.classList.toggle('is-online', !!connected);
    el.classList.toggle('is-offline', !connected);
    el.title = title || '';
    const labelEl = el.querySelector('.ci-live-label');
    if (labelEl) {
      labelEl.textContent = label || (connected ? 'Biometric connected' : 'Biometric offline');
    }
  }

  async function refreshStatus() {
    if (!statusUrl) return;
    try {
      const res = await fetch(statusUrl, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        setStatus(false, 'Biometric offline', 'Status check failed');
        return;
      }
      const data = await res.json();
      setStatus(!!data.connected, data.status_label, data.status_title);
    } catch (_) {
      setStatus(false, 'Biometric offline', 'Network error');
    }
  }

  async function liveSync() {
    if (!ready || syncBusy || !syncUrl) return;
    syncBusy = true;
    try {
      const res = await fetch(syncUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      if (res.ok) {
        const data = await res.json();
        if (data.busy) {
          await refreshStatus();
        } else if (typeof data.connected !== 'undefined') {
          setStatus(data.connected, data.status_label, data.status_title);
        } else {
          setStatus(!!data.ok, data.ok ? 'Biometric connected' : 'Biometric offline');
        }
      } else {
        setStatus(false, 'Biometric offline', 'Live sync failed');
      }
    } catch (_) {
      setStatus(false, 'Biometric offline', 'Network error');
    } finally {
      syncBusy = false;
    }
  }

  async function poll() {
    if (!ready || pollBusy) return;
    pollBusy = true;
    try {
      const res = await fetch(pollUrl + '?after_id=' + afterId + '&limit=40', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;

      const data = await res.json();
      if (!data) return;

      let punches = Array.isArray(data.punches) ? data.punches : [];
      if (!punches.length && data.punch) punches = [data.punch];

      applyPunches(punches);

      const serverAfter = Number(data.after_id);
      if (Number.isFinite(serverAfter) && serverAfter > afterId) {
        afterId = serverAfter;
      }
      // Keep local read cursor >= poll cursor when nothing new was displayable
      if (afterId > getReadId()) {
        markRead(afterId);
      }
    } catch (err) {
      console.warn('[checkin] poll failed', err);
    } finally {
      pollBusy = false;
    }
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
      if (!event.data || event.data.type !== 'checkin-push') return;
      const punch = punchFromPushPayload(event.data.payload);
      if (punch) show(punch);
    });
  }

  closeBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    stopAllSounds();
    hideToast();
  });

  enableBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    try {
      if (canUseWebPush()) {
        await subscribePush();
      }
    } catch (err) {
      console.warn('[checkin] push subscribe failed', err);
    }
    const soundOk = await unlockAudio(true);
    setEnableUi(soundOk, soundOk ? null : 'Enable check-in sound');
    if (soundOk && presentingId) {
      // Replay sound for the currently visible toast
      playCheckinSound({
        fee: { status: toast?.dataset?.fee || 'none' },
        sound: soundUrl,
      });
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) hideToast();
  });

  const silentUnlock = () => {
    if (soundWarmed) return;
    unlockAudio(false).then((ok) => {
      if (ok) setEnableUi(true);
    });
  };
  document.addEventListener('pointerdown', silentUnlock, { capture: true });
  document.addEventListener('keydown', silentUnlock, { capture: true });

  (async function boot() {
    if (enableBtn) {
      enableBtn.hidden = true;
    }

    if (audioEl && soundUrl) {
      if (!sameAudioFile(audioEl.getAttribute('src') || '', soundUrl)) {
        audioEl.src = soundUrl;
      }
      try { audioEl.load(); } catch (_) {}
    }
    if (expiredAudioEl && expiredSoundUrl) {
      if (!sameAudioFile(expiredAudioEl.getAttribute('src') || '', expiredSoundUrl)) {
        expiredAudioEl.src = expiredSoundUrl;
      }
      try { expiredAudioEl.load(); } catch (_) {}
    }

    unlockAudio(false).then((ok) => {
      if (!ok && enableBtn) {
        setEnableUi(false, 'Enable check-in sound');
      }
    });

    try {
      const res = await fetch(pollUrl + '?bootstrap=1', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.ok) {
        const data = await res.json();
        const serverLast = Number(data.last_id || 0) || 0;
        const readId = getReadId();

        if (readId > 0) {
          // Resume from what this browser already showed (do not replay on navigation)
          afterId = readId;
        } else {
          // First visit: skip history, then remember that cursor
          afterId = serverLast;
          markRead(serverLast);
        }
      } else {
        afterId = Math.max(afterId, getReadId());
      }
    } catch (_) {
      afterId = Math.max(afterId, getReadId());
    }

    ready = true;
    refreshStatus();
    liveSync();
    poll();
    setInterval(poll, pollMs);
    setInterval(liveSync, syncMs);
    setInterval(refreshStatus, statusMs);

    if (canUseWebPush()) {
      if (Notification.permission === 'granted') {
        try {
          await subscribePush();
        } catch (err) {
          console.warn('[checkin] auto subscribe failed', err);
        }
      }
    }

    if (!soundWarmed && enableBtn) {
      setEnableUi(false, 'Enable check-in sound');
    }
  })();
})();
