<aside class="app-sidebar" id="appSidebar" aria-label="Navigasi utama">
    <a class="side-brand" href="{{ url('/') }}" aria-label="HiStrangers home">
        <span class="brand-glyph">✳</span>
        <span class="brand-type">hi<span>strangers</span><b>.</b><small>CONNECT BEYOND</small></span>
    </a>
    <div class="sidebar-scroll">
        <div class="side-nav-label">MENU UTAMA</div>
        <nav class="side-nav" aria-label="Halaman">
            <a class="side-nav-link {{ request()->routeIs('home', 'dashboard') ? 'is-active' : '' }}" href="{{ url('/') }}" @if(request()->routeIs('home', 'dashboard')) aria-current="page" @endif>
                <span class="side-nav-icon">▦</span><span>Dashboard</span>
            </a>
            <a class="side-nav-link {{ request()->is('random') ? 'is-active' : '' }}" href="{{ route('rtc.page') }}" @if(request()->is('random')) aria-current="page" @endif>
                <span class="side-nav-icon">◉</span><span>Random Video Chat</span>
            </a>
            <a class="side-nav-link {{ request()->is('screenshare') ? 'is-active' : '' }}" href="{{ url('/screenshare') }}" @if(request()->is('screenshare')) aria-current="page" @endif>
                <span class="side-nav-icon">▣</span><span>Screen Sharing</span>
            </a>
        </nav>
        <div class="side-nav-label side-nav-label-spaced">INFORMASI</div>
        <nav class="side-nav" aria-label="Informasi aplikasi">
            <a class="side-nav-link" href="{{ url('/') }}#how-it-works"><span class="side-nav-icon">◇</span><span>Cara Kerja</span></a>
            <a class="side-nav-link" href="{{ url('/') }}#compatibility"><span class="side-nav-icon">⊞</span><span>Kompatibilitas</span></a>
        </nav>
        <section class="sidebar-tip">
            <div class="sidebar-tip-icon">✦</div>
            <strong>Share your screen.</strong>
            <p>Bagikan layar kepada partner melalui WebRTC dengan persetujuan perangkat.</p>
            <a href="{{ route('rtc.page') }}">Coba sekarang <span aria-hidden="true">→</span></a>
        </section>
    </div>
    <div class="sidebar-profile"><span class="profile-image">G</span><span><strong>Guest Workspace</strong><small>HiStrangers · Local</small></span><span class="profile-more" aria-hidden="true">···</span></div>
</aside>
