(function () {
  const body = document.body;
  const pollUrl = body.dataset.pollUrl;
  const displaySeconds = Math.max(3, parseInt(body.dataset.displaySeconds || '10', 10));

  const idle = document.getElementById('cw-idle');
  const overlay = document.getElementById('cw-overlay');
  const sheet = document.getElementById('cw-sheet');
  const clockEl = document.getElementById('cw-clock');
  const timerProgress = document.getElementById('cw-timer-progress');
  const timerNum = document.getElementById('cw-timer-num');

  const CIRC = 2 * Math.PI * 52;
  timerProgress.style.strokeDasharray = String(CIRC);
  timerProgress.style.strokeDashoffset = '0';

  let afterId = 0;
  let queue = [];
  let showing = false;
  let timerId = null;
  let rafId = null;
  let pollBusy = false;
  let ready = false;

  function tickClock() {
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  }

  tickClock();
  setInterval(tickClock, 1000);

  function feeNote(fee) {
    if (!fee || fee.status === 'none') {
      return 'No active fee period on file.';
    }
    if (fee.status === 'expired') {
      const days = Math.abs(fee.days_left || 0);
      return days <= 1 ? 'Membership fee expired yesterday.' : `Membership fee expired ${days} days ago.`;
    }
    if (fee.status === 'expiring') {
      const days = fee.days_left ?? 0;
      return days === 0 ? 'Fee expires today — please renew.' : `Fee expires in ${days} day${days === 1 ? '' : 's'}.`;
    }
    const days = fee.days_left ?? 0;
    return days ? `${days} days remaining on current plan.` : 'Fee is currently active.';
  }

  function fillSheet(punch) {
    const badge = document.getElementById('cw-badge');
    const avatar = document.getElementById('cw-avatar');
    const name = document.getElementById('cw-name');
    const sub = document.getElementById('cw-sub');
    const code = document.getElementById('cw-code');
    const time = document.getElementById('cw-time');
    const feeBox = document.getElementById('cw-fee');
    const feeLabel = document.getElementById('cw-fee-label');
    const feeChip = document.getElementById('cw-fee-chip');
    const feeStart = document.getElementById('cw-fee-start');
    const feeEnd = document.getElementById('cw-fee-end');
    const feeNoteEl = document.getElementById('cw-fee-note');

    badge.textContent = punch.headline;
    badge.classList.toggle('is-out', punch.type === 'check_out');

    name.textContent = punch.member.name;
    sub.textContent = punch.subline;
    code.textContent = punch.member.bio_id
      ? ('Bio ID ' + punch.member.bio_id)
      : 'Bio ID —';
    time.textContent = punch.punched_at;

    if (punch.member.avatar) {
      avatar.textContent = '';
      avatar.style.backgroundImage = `url(${punch.member.avatar})`;
    } else {
      avatar.style.backgroundImage = '';
      avatar.textContent = punch.member.initials || 'FG';
    }

    const fee = punch.fee || {};
    feeBox.dataset.status = fee.status || 'none';
    feeLabel.textContent = fee.plan ? fee.plan : 'Fee Period';
    feeChip.textContent = fee.label || '—';
    feeStart.textContent = fee.start || '—';
    feeEnd.textContent = fee.end || '—';
    feeNoteEl.textContent = feeNote(fee);

    if (fee.status === 'expired') {
      timerProgress.style.stroke = 'var(--cw-red)';
    } else if (fee.status === 'expiring') {
      timerProgress.style.stroke = 'var(--cw-amber)';
    } else {
      timerProgress.style.stroke = 'var(--cw-accent)';
    }
  }

  function clearTimers() {
    if (timerId) {
      clearTimeout(timerId);
      timerId = null;
    }
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
  }

  function hideOverlay() {
    clearTimers();
    sheet.classList.add('is-out');
    setTimeout(() => {
      overlay.hidden = true;
      sheet.classList.remove('is-out');
      idle.classList.remove('is-dim');
      showing = false;
      if (queue.length) {
        showPunch(queue.shift());
      }
    }, 320);
  }

  function startCountdown() {
    clearTimers();
    const totalMs = displaySeconds * 1000;
    const started = performance.now();
    timerProgress.style.strokeDashoffset = '0';
    timerNum.textContent = String(displaySeconds);

    function frame(now) {
      const elapsed = now - started;
      const remain = Math.max(0, totalMs - elapsed);
      const progress = remain / totalMs;
      timerProgress.style.strokeDashoffset = String(CIRC * (1 - progress));
      timerNum.textContent = String(Math.max(1, Math.ceil(remain / 1000)));

      if (remain > 0) {
        rafId = requestAnimationFrame(frame);
      } else {
        timerNum.textContent = '0';
      }
    }

    rafId = requestAnimationFrame(frame);
    timerId = setTimeout(hideOverlay, totalMs);
  }

  function showPunch(punch) {
    if (showing) {
      queue.push(punch);
      // Cap queue so we don't stack forever
      if (queue.length > 5) queue = queue.slice(-5);
      return;
    }

    showing = true;
    afterId = Math.max(afterId, punch.id);

    fillSheet(punch);
    idle.classList.add('is-dim');
    overlay.hidden = false;

    sheet.style.animation = 'none';
    void sheet.offsetWidth;
    sheet.style.animation = '';

    startCountdown();
  }

  async function poll() {
    if (!ready || pollBusy) return;
    pollBusy = true;
    try {
      const url = `${pollUrl}?after_id=${encodeURIComponent(afterId)}`;
      const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.punch) {
        showPunch(data.punch);
      }
    } catch (e) {
      // keep polling quietly
    } finally {
      pollBusy = false;
    }
  }

  async function liveSync() {
    const syncUrl = body.dataset.syncUrl;
    const csrf = body.dataset.csrf;
    if (!syncUrl || !csrf) return;
    try {
      await fetch(syncUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
    } catch (_) {}
  }

  overlay.addEventListener('click', () => {
    if (!overlay.hidden) hideOverlay();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.hidden) hideOverlay();
  });

  (async function bootstrap() {
    try {
      const res = await fetch(`${pollUrl}?bootstrap=1`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.ok) {
        const data = await res.json();
        afterId = Number(data.last_id || 0);
      }
    } catch (_) {}

    ready = true;
    liveSync();
    poll();
    setInterval(poll, 500);
    setInterval(liveSync, 2500);
  })();
})();
