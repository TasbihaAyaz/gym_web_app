<aside class="sidebar">
  <div class="sidebar-logo">
    <a href="{{ route('dashboard') }}" class="logo-mark" title="Fit Generation">
      <img src="{{ asset('assets/img/logo.jpg') }}" alt="Fit Generation">
    </a>
    <div class="logo-text">
      <span class="logo-name">Fit Generation</span>
      <span class="logo-sub">GYM MANAGEMENT</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
      <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/></svg>
      <span>Dashboard</span>
    </a>

    @if(can_perm('members.view') || can_perm('trainers.view') || can_perm('classes.view') || can_perm('plans.view'))
    <div class="nav-section">Management</div>
    @endif
    @perm('members.view')
    <div class="nav-group {{ request()->routeIs('members.*') ? 'open has-active' : '' }}">
      <a class="nav-item {{ request()->routeIs('members.index', 'members.create', 'members.show', 'members.edit', 'members.pending-fees', 'members.export-pending-fees') && ! request()->routeIs('members.package-setup*', 'members.trainer-setup*') ? 'active' : '' }}" href="{{ route('members.index') }}">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Members</span>
      </a>
      <div class="nav-children">
        <a class="nav-child {{ request()->routeIs('members.index', 'members.create', 'members.show', 'members.edit', 'members.pending-fees', 'members.export-pending-fees') && ! request()->routeIs('members.package-setup*', 'members.trainer-setup*') ? 'active' : '' }}" href="{{ route('members.index') }}">
          <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
          <span>All Members</span>
        </a>
        <a class="nav-child {{ request()->routeIs('members.package-setup*') ? 'active' : '' }}" href="{{ route('members.package-setup') }}">
          <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
          <span>Member Package Setup</span>
        </a>
        <a class="nav-child {{ request()->routeIs('members.trainer-setup*') ? 'active' : '' }}" href="{{ route('members.trainer-setup') }}">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Member Trainer Setup</span>
        </a>
      </div>
    </div>
    @endperm
    @perm('trainers.view')
    <a class="nav-item {{ request()->routeIs('trainers.*') ? 'active' : '' }}" href="{{ route('trainers.index') }}">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Trainers</span>
    </a>
    @endperm
    @perm('classes.view')
    <a class="nav-item {{ request()->routeIs('classes.*') ? 'active' : '' }}" href="{{ route('classes.index') }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      <span>Classes</span>
    </a>
    @endperm
    @perm('plans.view')
    <a class="nav-item {{ request()->routeIs('plans.*') ? 'active' : '' }}" href="{{ route('plans.index') }}">
      <svg viewBox="0 0 24 24"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
      <span>Plans &amp; Packages</span>
    </a>
    @endperm

    @if(can_perm('payments.view') || can_perm('expenses.view') || can_perm('accounts.view'))
    <div class="nav-section">Finance</div>
    @endif
    @perm('payments.view')
    <a class="nav-item {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">
      <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
      <span>Fee Payments</span>
    </a>
    @endperm
    @perm('expenses.view')
    <a class="nav-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}">
      <svg viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
      <span>Expenses</span>
    </a>
    @endperm
    @perm('accounts.view')
    <a class="nav-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}">
      <svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M3 10h18"/><path d="m5 6 7-3 7 3"/><path d="M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
      <span>Accounts</span>
    </a>
    @endperm

    @if(can_perm('attendance.view') || auth()->user()?->canManageBiometric() || auth()->user()?->canAccessReports())
    <div class="nav-section">Operations</div>
    @endif
    @perm('attendance.view')
    <a class="nav-item {{ request()->routeIs('attendance.*') ? 'active' : '' }}" href="{{ route('attendance.index') }}">
      <svg viewBox="0 0 24 24"><path d="M9 11.5 11 13.5 15 9.5"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4"/></svg>
      <span>Attendance</span>
    </a>
    @endperm
    @if(auth()->user()?->canManageBiometric())
    <a class="nav-item {{ request()->routeIs('zkteco.*') ? 'active' : '' }}" href="{{ route('zkteco.index') }}">
      <svg viewBox="0 0 24 24"><path d="M12 11c1.66 0 3-1.34 3-3S13.66 5 12 5s-3 1.34-3 3 1.34 3 3 3z"/><path d="M19 11c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM5 11c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/><path d="M12 13c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/><path d="M19 13c-.29 0-.62.02-.97.05A4.1 4.1 0 0 1 21 17v2h3v-2c0-1.9-2.24-3.4-5-3.95zM5 13c-2.76.55-5 2.05-5 3.95v2h3v-2c.7-1.6 2.2-2.8 4.03-3.15A8.4 8.4 0 0 0 5 13z"/></svg>
      <span>Biometric Device</span>
    </a>
    @endif
    @if(auth()->user()?->canAccessReports())
    <a class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
      <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/></svg>
      <span>Reports</span>
    </a>
    @endif

    @if(can_perm('settings.view') || can_perm('users.view') || can_perm('roles.view'))
    <div class="nav-section">Settings</div>
    @endif
    @perm('settings.view')
    <a class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span>Settings</span>
    </a>
    @endperm
    @perm('users.view')
    <a class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
      <span>Users</span>
    </a>
    @endperm
    @perm('roles.view')
    <a class="nav-item {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
      <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Roles &amp; Permissions</span>
    </a>
    @endperm
  </nav>
</aside>
