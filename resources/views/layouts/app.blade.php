<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#090e1a">
    <title>@yield('title', 'Dashboard') · HiStrangers</title>
    <meta name="description" content="HiStrangers: random video chat dan screen sharing antarperangkat menggunakan WebRTC dan Laravel.">
    <link rel="stylesheet" href="{{ asset('css/histrangers-ui.css') }}">
    @stack('styles')
</head>
<body class="dashboard-body">
    <a class="skip-link" href="#mainContent">Langsung ke konten</a>
    <div class="site-shell" id="siteShell">
        <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>
        <x-sidebar />
        <div class="site-main">
            <x-navbar />
            <main class="site-content" id="mainContent">
                @yield('content')
            </main>
            <x-footer />
        </div>
    </div>
    <div class="ui-toast" id="siteToast" role="status" aria-live="polite"></div>
    <script src="{{ asset('js/histrangers-ui.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
