<header class="site-topbar">
    <div class="topbar-left">
        <button class="mobile-menu-toggle" type="button" id="sidebarToggle" aria-label="Buka menu navigasi" aria-controls="appSidebar" aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
            <span class="eyebrow">WORKSPACE / HISTRANGERS</span>
            <h2 class="topbar-title">@yield('page-heading', 'Overview')</h2>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-state" title="Lingkungan development, bukan status koneksi pengguna"><i class="tiny-light"></i><span>Development mode</span></div>
        <a class="topbar-help" href="{{ route('rtc.page') }}" title="Buka video chat">Mulai chatting <span aria-hidden="true">↗</span></a>
        <span class="topbar-avatar" title="Guest" aria-label="Akun guest">G</span>
    </div>
</header>
