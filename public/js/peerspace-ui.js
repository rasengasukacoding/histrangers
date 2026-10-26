(() => {
  'use strict';
  const shell = document.getElementById('siteShell');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  const closeMenu = () => {
    shell?.classList.remove('sidebar-open');
    toggle?.setAttribute('aria-expanded', 'false');
  };
  toggle?.addEventListener('click', () => {
    const expanded = shell.classList.toggle('sidebar-open');
    toggle.setAttribute('aria-expanded', String(expanded));
  });
  backdrop?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
  document.querySelectorAll('.app-sidebar a').forEach((link) => link.addEventListener('click', closeMenu));

  const values = [
    ['featureSecure', Boolean(window.isSecureContext)],
    ['featureMedia', Boolean(navigator.mediaDevices?.getUserMedia)],
    ['featureScreen', Boolean(navigator.mediaDevices?.getDisplayMedia)],
    ['featureRTC', typeof window.RTCPeerConnection !== 'undefined'],
  ];
  for (const [id, supported] of values) {
    const target = document.getElementById(id);
    if (target) {
      target.textContent = supported ? 'Tersedia ✓' : 'Tidak tersedia ✕';
      target.classList.add(supported ? 'ok' : 'bad');
    }
  }
})();
