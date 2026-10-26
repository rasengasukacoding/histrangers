<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#090c17">
    <title>HiStrangers · Random Video & Screen Sharing</title>
    <link rel="stylesheet" href="{{ asset('css/random-chat.css') }}">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div class="brand-wrap">
            <a href="{{ url('/') }}" class="brand" aria-label="HiStrangers Beranda">
                <span class="brand-mark">✳</span>
                <span>hi<span class="brand-em">strangers</span><span class="brand-dot">.</span></span>
            </a>
            <span class="top-separator"></span>
            <span class="top-subtitle">Meet beyond boundaries</span>
        </div>
        <div class="top-actions">
            <a href="{{ url('/') }}" class="dashboard-shortcut">⌂ Dashboard</a>
            <span class="live-pill"><span class="pulse-dot"></span> WebRTC P2P</span>
            <a href="{{ url('/screenshare') }}" class="nav-link">Mode Room Manual ↗</a>
            <button type="button" id="btnHelp" class="help-btn" aria-label="Bantuan">?</button>
        </div>
    </header>

    <main class="main-layout">
        <div class="stage">
            <div class="intro">
                <div>
                    <div class="eyebrow"><span class="mini-star">✦</span> REAL-TIME CONNECTION</div>
                    <h1>Meet someone <em>new.</em></h1>
                    <p>Terhubung secara acak melalui video, audio, dan berbagi layar secara privat antarperangkat.</p>
                </div>
                <div class="stage-status" id="stageStatus"><span class="status-dot waiting"></span><span id="statusText">Siap untuk terhubung</span></div>
            </div>

            <div class="video-grid">
                <section class="video-panel remote-panel" aria-label="Video pasangan">
                    <video id="remoteVideo" autoplay playsinline></video>
                    <div class="video-placeholder" id="remotePlaceholder">
                        <div class="orbit"><div class="orbit-inner">✳</div><span class="orbit-small a"></span><span class="orbit-small b"></span></div>
                        <h2 id="placeholderTitle">Connections start here.</h2>
                        <p id="placeholderDescription">Tekan Mulai untuk menemukan pengguna lain yang sedang online.</p>
                    </div>
                    <div class="video-bottom left"><span class="video-caption"><span class="caption-dot remote"></span> <span id="remoteName">Stranger</span></span></div>
                    <div class="video-top right"><span class="video-tag" id="remoteMediaTag">REMOTE VIDEO</span></div>
                </section>
                <section class="video-panel local-panel" aria-label="Video lokal">
                    <video id="localVideo" autoplay playsinline muted></video>
                    <div id="localPlaceholder" class="video-placeholder local-empty">
                        <div class="camera-icon">◉</div>
                        <h2>Your camera is off</h2>
                        <p>Izinkan kamera atau mikrofon saat memulai percakapan.</p>
                    </div>
                    <div class="video-bottom left"><span class="video-caption"><span class="caption-dot local"></span> You</span></div>
                    <div class="video-top right"><span class="video-tag">YOUR CAMERA</span></div>
                </section>
            </div>

            <div class="controls-card">
                <div class="control-group">
                    <button id="btnMic" class="icon-btn" type="button" disabled aria-label="Nyalakan atau matikan mikrofon" title="Mikrofon">🎙</button>
                    <button id="btnCam" class="icon-btn" type="button" disabled aria-label="Nyalakan atau matikan kamera" title="Kamera">▣</button>
                    <button id="btnScreen" class="icon-btn screen-btn" type="button" disabled aria-label="Bagikan layar" title="Bagikan layar">▤ <span>Share screen</span></button>
                </div>
                <div class="control-main">
                    <button id="btnStart" class="primary-btn" type="button"><span>✦</span> Mulai Bertemu</button>
                    <button id="btnNext" class="next-btn hidden" type="button">Next Stranger <span>→</span></button>
                    <button id="btnStop" class="danger-btn hidden" type="button">■ <span>Stop</span></button>
                </div>
            </div>
            <div class="notice"><span>♢</span> Media hanya dikirim setelah kamu menyetujui izin perangkat. Jangan bagikan informasi pribadi atau sensitif.</div>
        </div>

        <aside class="sidebar">
            <section class="side-card about-card">
                <div class="side-heading"><div><span class="eyebrow">THE EXPERIENCE</span><h3>Real conversations.</h3></div><span class="heading-icon">✴</span></div>
                <p>Obrolan spontan dengan seseorang yang baru. Tidak perlu membuat akun.</p>
                <div class="feature-list">
                    <div><span class="feature-icon">◇</span><div><b>Random matching</b><small>Pasangan otomatis, satu lawan satu</small></div></div>
                    <div><span class="feature-icon">▧</span><div><b>Screen sharing</b><small>Bagikan tab atau layar perangkat</small></div></div>
                    <div><span class="feature-icon">⊙</span><div><b>Peer-to-peer media</b><small>WebRTC, dengan opsi TURN relay</small></div></div>
                </div>
            </section>
            <section class="side-card chat-card">
                <div class="chat-header"><div><span class="eyebrow">LIVE CHAT</span><h3>Conversation <span>✦</span></h3></div><span class="chat-counter" id="chatStatus">OFFLINE</span></div>
                <div class="chat-messages" id="chatMessages" aria-live="polite">
                    <div class="system-msg">Selamat datang di HiStrangers. Cari pasangan untuk mulai mengobrol.</div>
                </div>
                <form id="chatForm" class="chat-compose"><input id="chatInput" type="text" placeholder="Tulis pesan..." maxlength="1000" autocomplete="off" disabled aria-label="Pesan"><button id="btnSend" type="submit" disabled aria-label="Kirim pesan">➤</button></form>
                <button id="btnReport" class="report-link" type="button" disabled>⚑ Laporkan pengguna</button>
            </section>
            <div class="sidebar-foot"><span class="tiny-dot"></span> Built with Laravel × WebRTC <span class="foot-version">BETA</span></div>
        </aside>
    </main>
</div>

<dialog id="helpDialog" class="modal">
    <h2>Cara menggunakan HiStrangers</h2>
    <p>1. Tekan <b>Mulai Bertemu</b>, lalu izinkan kamera dan mikrofon jika ingin melakukan video call.</p>
    <p>2. Ketika menemukan pasangan, kamu dapat mengobrol, mengaktifkan screen sharing, atau menekan Next.</p>
    <p>3. Media terhubung melalui WebRTC; signaling menggunakan server Laravel. Gunakan HTTPS untuk perangkat lain dan konfigurasikan TURN untuk jaringan yang membatasi P2P.</p>
    <button class="primary-btn" type="button" data-close>Oke, mengerti</button>
</dialog>
<dialog id="reportDialog" class="modal">
    <h2>Laporkan pengguna</h2>
    <p>Pilih alasan laporan. Kamu akan langsung berpindah ke pasangan lain. Laporan menyimpan metadata sesi, bukan video atau audio.</p>
    <form id="reportForm">
        <label for="reportReason">Alasan laporan</label>
        <select id="reportReason" required>
            <option value="harassment">Pelecehan / intimidasi</option>
            <option value="nudity">Konten tidak pantas</option>
            <option value="spam">Spam / penipuan</option>
            <option value="other">Lainnya</option>
        </select>
        <div class="modal-actions"><button type="button" class="icon-btn" data-close>Batal</button><button type="submit" class="primary-btn">Laporkan &amp; Next</button></div>
    </form>
</dialog>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script nonce="{{ request()->attributes->get('histrangers_csp_nonce') }}">
window.HISTRANGERS = {
    csrf: @json(csrf_token()),
    api: @json(url('/random/api')),
    iceServers: @json($iceServers),
    relayOnly: @json(config('rtc.relay_only')),
};
</script>
<script src="{{ asset('js/random-chat.js') }}" defer></script>
</body>
</html>
