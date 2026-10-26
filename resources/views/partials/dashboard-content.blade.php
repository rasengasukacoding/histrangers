<div class="dashboard-header">
    <div><span class="eyebrow"><span class="eyebrow-star">✦</span> YOUR REAL-TIME WORKSPACE</span><h1>Welcome to <em>HiStrangers.</em></h1><p>Terhubung dengan orang baru, ngobrol langsung, atau bagikan layar antardevice. Semua dimulai dari browser.</p></div>
    <div class="dashboard-date"><span>✳</span><span>{{ now()->translatedFormat('d F Y') }}<small>Workspace overview</small></span></div>
</div>
<section class="hero-card" aria-labelledby="hero-heading">
    <div class="hero-halo hero-halo-one"></div><div class="hero-halo hero-halo-two"></div>
    <div class="hero-copy">
        <div class="hero-kicker"><span class="hero-spark">✦</span> CONNECT IN REAL TIME <span class="kicker-line"></span></div>
        <h2 id="hero-heading">Meet the world.<br><em>One connection</em> at a time.</h2>
        <p>Video chat acak satu lawan satu dengan audio, chat, dan kemampuan screen sharing langsung menggunakan WebRTC.</p>
        <div class="hero-actions">
            <a class="ui-button ui-button-primary" href="{{ route('rtc.page') }}"><span>◉</span> Mulai Video Chat <span aria-hidden="true">↗</span></a>
            <a class="ui-button ui-button-ghost" href="{{ url('/screenshare') }}">Mode Screen Share <span aria-hidden="true">→</span></a>
        </div>
        <div class="hero-footnote"><span class="hero-check">✓</span> No registration for random chat <span class="hero-vertical"></span><span class="hero-check">✓</span> P2P media when possible</div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="hero-orbit-ring"></div>
        <div class="mock-video mock-video-back"><div class="mock-video-top"><i></i> SCREEN SHARE</div><div class="mock-screen"><span>⌘</span><div><i></i><i></i><i></i></div></div></div>
        <div class="mock-video mock-video-front"><div class="mock-video-top"><i></i> RANDOM VIDEO CHAT</div><div class="mock-person"><div class="person-head"></div><div class="person-body"></div></div><div class="mock-call-controls"><span>♪</span><span>▣</span><span>⌁</span></div></div>
        <div class="mock-floating">✦ <span>Connected</span></div>
    </div>
</section>

<section class="quick-grid" aria-label="Akses cepat">
    <a href="{{ route('rtc.page') }}" class="quick-card"><span class="quick-icon quick-purple">◉</span><div><h3>Random Video Chat</h3><p>Temukan pasangan acak dan mulai percakapan real-time.</p><span class="quick-link">Mulai bertemu <b>↗</b></span></div></a>
    <a href="{{ url('/screenshare') }}" class="quick-card"><span class="quick-icon quick-cyan">▣</span><div><h3>Screen Sharing</h3><p>Buka halaman room manual untuk bertukar layar.</p><span class="quick-link">Buka halaman <b>↗</b></span></div></a>
    <a href="#how-it-works" class="quick-card"><span class="quick-icon quick-amber">◇</span><div><h3>Cara Kerja WebRTC</h3><p>Pahami signaling dan koneksi video antardevice.</p><span class="quick-link">Pelajari alurnya <b>↓</b></span></div></a>
</section>

<div class="section-line" id="how-it-works"><div><span class="eyebrow">HOW IT WORKS</span><h2>Satu klik, satu koneksi.</h2></div><span class="section-caption">3 langkah sederhana</span></div>
<section class="steps-grid" aria-label="Langkah penggunaan">
    <article class="step-card"><span class="step-number">01</span><div class="step-symbol">⌁</div><h3>Mulai</h3><p>Tekan Mulai Bertemu dan izinkan perangkat bila ingin menggunakan kamera atau mikrofon.</p></article>
    <article class="step-card"><span class="step-number">02</span><div class="step-symbol">◇</div><h3>Terhubung</h3><p>Laravel mencari pasangan dan bertukar sinyal; WebRTC membangun koneksi media.</p></article>
    <article class="step-card"><span class="step-number">03</span><div class="step-symbol">▣</div><h3>Berinteraksi</h3><p>Ngobrol, kirim pesan, bagikan layar, atau pilih Next untuk pasangan lain.</p></article>
</section>

<section class="compat-card" id="compatibility">
    <div><span class="eyebrow">DEVICE READINESS</span><h2>Browser & perangkat</h2><p>Aplikasi memerlukan konteks aman (HTTPS, atau localhost untuk uji lokal). Dukungan screen sharing pada browser mobile bervariasi.</p></div>
    <div class="compat-results" aria-live="polite">
        <div><span>Secure context</span><strong id="featureSecure">Memeriksa...</strong></div>
        <div><span>Camera / microphone API</span><strong id="featureMedia">Memeriksa...</strong></div>
        <div><span>Screen sharing API</span><strong id="featureScreen">Memeriksa...</strong></div>
        <div><span>WebRTC API</span><strong id="featureRTC">Memeriksa...</strong></div>
        <small>Indikator ini menunjukkan dukungan API browser, bukan izin perangkat atau keberhasilan sambungan.</small>
    </div>
</section>
