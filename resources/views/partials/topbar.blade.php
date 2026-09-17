@php
    $currentUser = auth()->user();
@endphp
<header class="topbar">
  <form method="GET" action="{{ route('members.index') }}" class="search-box" id="top-search-form">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search members..." />
    <span class="kbd">Ctrl + K</span>
  </form>
  <div class="topbar-right">
    <button type="button" class="theme-toggle" id="theme-toggle" title="Toggle light / dark theme" aria-label="Toggle theme">
      <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>
      </svg>
      <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/>
      </svg>
    </button>

    <a href="{{ route('payments.create') }}" class="icon-btn" title="Collect fee payment">
      <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
    </a>

    <div class="profile-menu dropdown">
      <button type="button" class="profile profile-trigger" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
        @if($currentUser?->avatar_url)
          <img src="{{ $currentUser->avatar_url }}" alt="avatar" />
        @else
          <div class="avatar-initials sm topbar-av">{{ $currentUser?->initials ?? 'AD' }}</div>
        @endif
        <div class="profile-info">
          <span class="profile-name">{{ $currentUser?->name ?? 'User' }}</span>
          <span class="profile-role">{{ $currentUser?->role?->name ?? 'Staff' }}</span>
        </div>
        <svg class="chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <div class="dropdown-menu profile-dropdown">
        @if($currentUser)
          <a href="{{ route('users.show', $currentUser) }}">My Profile</a>
          <a href="{{ route('settings.index') }}">Settings</a>
        @endif
        <form method="POST" action="{{ route('logout') }}" class="logout-form">
          @csrf
          <button type="submit" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            Logout
          </button>
        </form>
      </div>
    </div>
  </div>
</header>
