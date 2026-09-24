const { listen, emit } = window.__TAURI__.event;
const { invoke } = window.__TAURI__.core;

const els = {
  root: document.getElementById('toast-root'),
  toast: document.getElementById('toast'),
  close: document.getElementById('toast-close'),
  avatar: document.getElementById('toast-avatar'),
  badge: document.getElementById('toast-badge'),
  name: document.getElementById('toast-name'),
  sub: document.getElementById('toast-sub'),
  code: document.getElementById('toast-code'),
  time: document.getElementById('toast-time'),
  fee: document.getElementById('toast-fee'),
  pkg: document.getElementById('toast-package'),
  feeChip: document.getElementById('toast-fee-chip'),
  feeRangeRow: document.getElementById('toast-fee-range-row'),
  feeRange: document.getElementById('toast-fee-range'),
  pendingRow: document.getElementById('toast-pending-row'),
  pending: document.getElementById('toast-pending'),
  note: document.getElementById('toast-note'),
  audioCheckin: document.getElementById('audio-checkin'),
  audioExpired: document.getElementById('audio-expired'),
};

let hideToken = 0;
let presentingId = null;
let soundDefaults = { checkin: '', expired: '' };

function money(n) {
  return 'Rs ' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function feePeriodDate(fee) {
  const start = fee?.start;
  const end = fee?.end;
  if (!start || !end || start === '—' || end === '—') return '';
  return `${start} → ${end}`;
}

function feeNote(fee) {
  if (!fee || fee.status === 'none') return 'No package / fee on file.';
  if (fee.status === 'expired') return 'Fee expired — renewal pending.';
  if (fee.status === 'expiring') return 'Fee expiring soon — please renew.';
  if ((fee.pending || 0) > 0) return `Outstanding balance ${money(fee.pending)}.`;
  // Active with no dates on the row — still show Fee Period (white via CSS class)
  if (!feePeriodDate(fee)) return 'Fee Period';
  return '';
}

function packageLabel(fee) {
  const name = fee.plan || 'No package';
  const paid = Number(fee.amount_paid);
  if (Number.isFinite(paid) && paid > 0) {
    return `${name} · ${money(paid)}`;
  }
  return name;
}

function isExpired(punch) {
  return !!(punch && punch.fee && punch.fee.status === 'expired');
}

function resolveSound(punch) {
  if (isExpired(punch)) {
    return soundDefaults.expired || punch.sound || soundDefaults.checkin || '';
  }
  return soundDefaults.checkin || punch.sound || '';
}

function stopSounds() {
  [els.audioCheckin, els.audioExpired].forEach((a) => {
    try {
      a.pause();
      a.currentTime = 0;
    } catch (_) {}
  });
}

function playSound(punch) {
  const src = resolveSound(punch);
  if (!src) {
    console.warn('[toast] no sound src', punch?.fee?.status, soundDefaults);
    return;
  }
  const el = isExpired(punch) ? els.audioExpired : els.audioCheckin;
  try {
    el.src = src;
    el.muted = false;
    el.volume = 1;
    const kick = () => {
      try { el.currentTime = 0; } catch (_) {}
      const p = el.play();
      if (p && typeof p.then === 'function') {
        p.catch(() => {
          const a = new Audio(src);
          a.volume = 1;
          a.play().catch((err) => console.warn('[toast] audio blocked', err));
        });
      }
    };
    if (el.readyState >= 2) {
      kick();
    } else {
      el.oncanplay = () => {
        el.oncanplay = null;
        kick();
      };
      el.load();
      setTimeout(kick, 250);
    }
  } catch (err) {
    console.warn('[toast] play failed', err);
  }
}

function fill(punch) {
  const member = punch.member || {};
  const fee = punch.fee || {};
  const status = fee.status || 'none';

  els.badge.textContent = punch.headline || 'Welcome';
  els.name.textContent = member.name || 'Member';
  els.sub.textContent = punch.subline || '';
  els.code.textContent = member.bio_id ? `Bio ID ${member.bio_id}` : 'Bio ID —';
  els.time.textContent = punch.punched_at || '';

  if (member.avatar) {
    els.avatar.textContent = '';
    els.avatar.style.backgroundImage = `url("${member.avatar.replace(/"/g, '')}")`;
  } else {
    els.avatar.style.backgroundImage = '';
    els.avatar.textContent = member.initials || 'FG';
  }

  els.fee.dataset.status = status;
  els.pkg.textContent = packageLabel(fee);
  els.feeChip.textContent = fee.label || '—';

  const period = feePeriodDate(fee);
  const pending = Number(fee.pending || 0);
  const paid = Number(fee.amount_paid || 0);
  const isActive = status === 'active' && pending <= 0;

  // Active members: show Fee Period dates in white (replaces "Fee is currently active.")
  if (els.feeRangeRow && els.feeRange) {
    if (isActive && period) {
      els.feeRangeRow.hidden = false;
      els.feeRange.textContent = period;
    } else if (period && status !== 'none') {
      els.feeRangeRow.hidden = false;
      els.feeRange.textContent = period;
    } else {
      els.feeRangeRow.hidden = true;
      els.feeRange.textContent = '—';
    }
  }

  if (status === 'expired' || pending > 0) {
    els.pendingRow.hidden = false;
    els.pending.textContent = pending > 0
      ? money(pending)
      : (paid > 0 ? `${money(paid)} (renew)` : 'Renewal due');
  } else {
    els.pendingRow.hidden = true;
  }

  els.note.textContent = feeNote(fee);
  els.note.classList.toggle('is-fee-period', isActive && !period);
}

async function hideToast() {
  const token = ++hideToken;
  presentingId = null;
  stopSounds();
  els.toast.classList.add('is-out');
  setTimeout(async () => {
    if (token !== hideToken) return;
    els.root.hidden = true;
    els.toast.classList.remove('is-out');
    try {
      await invoke('hide_checkin_toast');
    } catch (_) {}
    try {
      await emit('checkin-closed');
    } catch (_) {}
  }, 220);
}

function showToast(punch, sounds) {
  if (!punch || !punch.member) return;
  const id = Number(punch.id) || Date.now();

  if (sounds) {
    soundDefaults.checkin = sounds.checkin || soundDefaults.checkin;
    soundDefaults.expired = sounds.expired || soundDefaults.expired;
  }
  if (punch.sound && isExpired(punch) && !soundDefaults.expired) {
    soundDefaults.expired = punch.sound;
  }
  if (punch.sound && !isExpired(punch) && !soundDefaults.checkin) {
    soundDefaults.checkin = punch.sound;
  }

  // Cancel any pending close; keep window open until next check-in (or manual close)
  hideToken += 1;
  clearTimeout(showToast._timer);
  presentingId = id;
  fill(punch);
  els.root.hidden = false;
  els.toast.classList.remove('is-out');
  void els.toast.offsetWidth;
  els.toast.style.animation = 'none';
  void els.toast.offsetWidth;
  els.toast.style.animation = '';

  stopSounds();
  playSound(punch);
  setTimeout(() => {
    if (presentingId === id) playSound(punch);
  }, 180);
}

window.__showCheckin = function (payload) {
  const data = payload || {};
  showToast(data.punch, data.sounds);
};

els.close.addEventListener('click', () => hideToast());

listen('checkin-show', (event) => {
  const payload = event.payload || {};
  showToast(payload.punch, payload.sounds);
}).catch(() => {});

listen('checkin-sounds', (event) => {
  const sounds = event.payload || {};
  soundDefaults.checkin = sounds.checkin || soundDefaults.checkin;
  soundDefaults.expired = sounds.expired || soundDefaults.expired;
}).catch(() => {});
