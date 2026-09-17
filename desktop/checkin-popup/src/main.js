const { invoke } = window.__TAURI__.core;

const STORAGE_KEY = 'fg-desktop-checkin';
const READ_KEY = 'fg-desktop-read-id';
const POLL_MS = 800;
const SYNC_MS = 2000;
const OFFLINE_AFTER_FAILS = 4;

const els = {
  loginView: document.getElementById('view-login'),
  liveView: document.getElementById('view-live'),
  serverUrl: document.getElementById('server-url'),
  email: document.getElementById('email'),
  password: document.getElementById('password'),
  loginBtn: document.getElementById('btn-login'),
  loginError: document.getElementById('login-error'),
  gymName: document.getElementById('gym-name'),
  connPill: document.getElementById('conn-pill'),
  serverLabel: document.getElementById('server-label'),
  userLabel: document.getElementById('user-label'),
  lastLabel: document.getElementById('last-label'),
  logoutBtn: document.getElementById('btn-logout'),
  testSoundBtn: document.getElementById('btn-test-sound'),
};

let state = loadState();
let afterId = Number(localStorage.getItem(READ_KEY) || 0) || 0;
// Guard against corrupted READ_KEY from old Test Sound bug (Date.now())
if (afterId > 100000000) {
  afterId = 0;
  localStorage.setItem(READ_KEY, '0');
}
let pollTimer = null;
let syncTimer = null;
let pollBusy = false;
let syncBusy = false;
let soundDefaults = { checkin: '', expired: '' };
let failCount = 0;
let nextPollDelay = POLL_MS;
let lastShownId = 0;

function loadState() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {};
  } catch (_) {
    return {};
  }
}

function saveState(patch) {
  state = { ...state, ...patch };
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function normalizeBase(url) {
  return String(url || '').trim().replace(/\/+$/, '');
}

function setConn(online, label) {
  els.connPill.classList.toggle('online', !!online);
  els.connPill.classList.toggle('offline', !online);
  els.connPill.querySelector('span').textContent = label || (online ? 'Live' : 'Offline');
}

function setLastEvent(punch) {
  if (!punch || !punch.member) {
    els.lastLabel.textContent = 'Waiting for next check-in…';
    return;
  }
  const name = punch.member.name || 'Member';
  const time = punch.punched_at || '';
  els.lastLabel.textContent = time ? `${name} · ${time}` : name;
}

function showError(msg) {
  els.loginError.hidden = !msg;
  els.loginError.textContent = msg || '';
}

function showLogin() {
  els.loginView.hidden = false;
  els.liveView.hidden = true;
  stopPolling();
  setConn(false, 'Offline');
  invoke('hide_checkin_toast').catch(() => {});
}

function showLive() {
  els.loginView.hidden = true;
  els.liveView.hidden = false;
  els.serverLabel.textContent = state.baseUrl || '—';
  els.userLabel.textContent = state.userName || state.email || '—';
  els.gymName.textContent = state.appName || 'Fit Generation';
}

function markRead(id) {
  const n = Number(id) || 0;
  // Never persist demo / timestamp IDs
  if (!n || n > 100000000) return;
  afterId = Math.max(afterId, n);
  if (n > Number(localStorage.getItem(READ_KEY) || 0)) {
    localStorage.setItem(READ_KEY, String(n));
  }
}

async function showOverlayToast(punch, { persist = true } = {}) {
  const id = Number(punch?.id) || 0;
  if (!id || !punch?.member) return;
  if (persist && id === lastShownId) return;

  if (persist) {
    const readId = Number(localStorage.getItem(READ_KEY) || 0) || 0;
    if (id < readId) return;
  }

  setLastEvent(punch);

  try {
    await invoke('show_checkin_toast', {
      punch,
      sounds: {
        checkin: soundDefaults.checkin || punch.sound || null,
        expired: soundDefaults.expired || null,
      },
    });
    if (persist) {
      lastShownId = id;
      markRead(id);
    }
  } catch (err) {
    console.warn('[checkin] overlay failed', err);
  }
}

function applyPunches(punches) {
  if (!Array.isArray(punches) || !punches.length) return;

  let latest = null;
  let maxId = afterId;

  for (const punch of punches) {
    const id = Number(punch?.id) || 0;
    if (!id) continue;
    if (id > maxId) maxId = id;
    if (id > afterId && punch.member) latest = punch;
  }

  if (maxId > afterId) afterId = maxId;
  if (latest) showOverlayToast(latest);
}

function scheduleNextPoll() {
  if (pollTimer) {
    clearTimeout(pollTimer);
    pollTimer = null;
  }
  if (!state.token || !state.baseUrl) return;
  pollTimer = setTimeout(() => {
    pollOnce(false).finally(() => scheduleNextPoll());
  }, nextPollDelay);
}

function scheduleNextSync() {
  if (syncTimer) {
    clearTimeout(syncTimer);
    syncTimer = null;
  }
  if (!state.token || !state.baseUrl) return;
  syncTimer = setTimeout(() => {
    liveSyncOnce().finally(() => scheduleNextSync());
  }, SYNC_MS);
}

async function liveSyncOnce() {
  if (syncBusy || !state.token || !state.baseUrl) return;
  syncBusy = true;
  try {
    const data = await invoke('desktop_live_sync', {
      baseUrl: state.baseUrl,
      token: state.token,
      afterId,
    });

    if (data?.sounds) {
      soundDefaults.checkin = data.sounds.checkin || soundDefaults.checkin;
      soundDefaults.expired = data.sounds.expired || soundDefaults.expired;
    }

    // Show immediately from sync (do not depend on a separate poll)
    if (Array.isArray(data?.punches) && data.punches.length) {
      applyPunches(data.punches);
    } else if (data?.punch) {
      applyPunches([data.punch]);
    }

    if (data?.after_id != null) {
      afterId = Math.max(afterId, Number(data.after_id) || 0);
    }

    if (data?.connected || data?.ok) {
      setConn(true, 'Live');
    }
  } catch (err) {
    const msg = String(err || '').toLowerCase();
    if (msg.includes('unauthorized') || msg.includes('unauthenticated')) {
      saveState({ token: '' });
      showError('Session expired. Sign in again.');
      showLogin();
    }
  } finally {
    syncBusy = false;
  }
}

async function pollOnce(bootstrap = false) {
  if (pollBusy || !state.token || !state.baseUrl) return;
  pollBusy = true;
  try {
    const data = await invoke('desktop_poll', {
      baseUrl: state.baseUrl,
      token: state.token,
      afterId,
      bootstrap,
    });

    failCount = 0;
    nextPollDelay = POLL_MS;

    if (data?.sounds) {
      soundDefaults.checkin = data.sounds.checkin || soundDefaults.checkin;
      soundDefaults.expired = data.sounds.expired || soundDefaults.expired;
    }

    if (bootstrap) {
      afterId = Number(data?.last_id || data?.after_id || afterId) || afterId;
      if (afterId < 100000000) markRead(afterId);
      setLastEvent(data?.latest_punch || null);
      setConn(true, 'Live');
      return;
    }

    applyPunches(data?.punches || []);
    if (data?.after_id != null) afterId = Math.max(afterId, Number(data.after_id) || 0);
    setConn(true, 'Live');
  } catch (err) {
    const msg = String(err || '');
    const lower = msg.toLowerCase();

    if (lower.includes('unauthorized') || lower.includes('unauthenticated')) {
      saveState({ token: '' });
      showError('Session expired. Sign in again.');
      showLogin();
      return;
    }

    failCount += 1;
    if (lower.includes('too many') || lower.includes('429')) {
      nextPollDelay = Math.min(5000, POLL_MS * Math.max(2, failCount));
      setConn(true, 'Slow');
    } else if (failCount >= OFFLINE_AFTER_FAILS) {
      nextPollDelay = Math.min(4000, POLL_MS * 2);
      setConn(false, 'Reconnecting…');
    } else {
      nextPollDelay = Math.min(2500, POLL_MS + failCount * 200);
      setConn(true, 'Live');
    }
  } finally {
    pollBusy = false;
  }
}

function stopPolling() {
  if (pollTimer) {
    clearTimeout(pollTimer);
    pollTimer = null;
  }
  if (syncTimer) {
    clearTimeout(syncTimer);
    syncTimer = null;
  }
}

function startPolling() {
  stopPolling();
  failCount = 0;
  nextPollDelay = POLL_MS;
  setConn(true, 'Connecting…');
  setLastEvent(null);
  pollOnce(true).finally(() => {
    scheduleNextPoll();
    scheduleNextSync();
  });
}

async function connect() {
  showError('');
  const baseUrl = normalizeBase(els.serverUrl.value);
  const email = els.email.value.trim();
  const password = els.password.value;

  if (!baseUrl || !email || !password) {
    showError('Enter website URL, email, and password.');
    return;
  }

  els.loginBtn.disabled = true;
  els.loginBtn.textContent = 'Connecting…';
  try {
    const data = await invoke('desktop_login', {
      baseUrl,
      email,
      password,
      deviceName: 'Fit Gen Check-in Desktop',
    });

    saveState({
      baseUrl,
      email,
      token: data.token,
      userName: data.user?.name || email,
      appName: data.app?.name || 'Fit Generation',
    });

    els.password.value = '';
    showLive();
    startPolling();
  } catch (err) {
    showError(String(err || 'Login failed'));
    showLogin();
  } finally {
    els.loginBtn.disabled = false;
    els.loginBtn.textContent = 'Connect & start';
  }
}

async function disconnect() {
  try {
    if (state.token && state.baseUrl) {
      await invoke('desktop_logout', { baseUrl: state.baseUrl, token: state.token });
    }
  } catch (_) {}
  saveState({ token: '' });
  showLogin();
}

async function restoreSession() {
  els.serverUrl.value = state.baseUrl || 'http://localhost/fit-generation';
  els.email.value = state.email || '';

  if (!state.token || !state.baseUrl) {
    showLogin();
    return;
  }

  try {
    const me = await invoke('desktop_me', {
      baseUrl: state.baseUrl,
      token: state.token,
    });
    saveState({
      userName: me.user?.name || state.userName,
      appName: me.app?.name || state.appName,
    });
    showLive();
    startPolling();
  } catch (_) {
    saveState({ token: '' });
    showLogin();
  }
}

async function testSound() {
  const demo = {
    id: -1,
    headline: 'Welcome',
    subline: 'Test check-in sound',
    punched_at: new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }),
    member: {
      id: 0,
      name: 'Test Member',
      bio_id: '000',
      initials: 'TM',
    },
    fee: {
      status: 'active',
      plan: 'GYM ACCESS',
      label: 'Fee Active',
      start: '—',
      end: '—',
      pending: 0,
    },
    sound: soundDefaults.checkin,
  };
  await showOverlayToast(demo, { persist: false });
}

els.loginBtn.addEventListener('click', connect);
els.logoutBtn.addEventListener('click', disconnect);
els.testSoundBtn.addEventListener('click', testSound);

['email', 'password', 'server-url'].forEach((id) => {
  document.getElementById(id).addEventListener('keydown', (e) => {
    if (e.key === 'Enter') connect();
  });
});

restoreSession();
