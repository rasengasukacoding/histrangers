(() => {
  'use strict';
  const config = window.HISTRANGERS || window.HISTRANGERS || {};
  const $ = (id) => document.getElementById(id);
  const el = {
    remote: $('remoteVideo'), local: $('localVideo'), remoteEmpty: $('remotePlaceholder'),
    localEmpty: $('localPlaceholder'), placeholderTitle: $('placeholderTitle'),
    placeholderDescription: $('placeholderDescription'), status: $('statusText'),
    stageStatus: $('stageStatus'), remoteName: $('remoteName'), mediaTag: $('remoteMediaTag'),
    start: $('btnStart'), next: $('btnNext'), stop: $('btnStop'),
    mic: $('btnMic'), cam: $('btnCam'), screen: $('btnScreen'),
    chat: $('chatMessages'), chatInput: $('chatInput'), send: $('btnSend'),
    chatForm: $('chatForm'), chatStatus: $('chatStatus'), report: $('btnReport'),
    reportDialog: $('reportDialog'), reportForm: $('reportForm'),
    helpDialog: $('helpDialog'), toast: $('toast'),
  };

  const tabKey = 'histrangers-tab-id';
  let tabId = sessionStorage.getItem(tabKey);
  if (!tabId || !/^[a-f0-9-]{36}$/i.test(tabId)) {
    tabId = crypto.randomUUID();
    sessionStorage.setItem(tabKey, tabId);
  }
  let active = false;
  let isBusy = false;
  let currentRoom = null;
  let cursor = 0;
  let pc = null;
  let localStream = null;
  let remoteStream = null;
  let screenStream = null;
  let videoSender = null;
  let pendingCandidates = [];
  let pollTimer = null;
  let epoch = 0;
  let toastTimer;

  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  async function api(endpoint, data = {}) {
    const response = await fetch(`${config.api}/${endpoint}`, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {
        'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-CSRF-TOKEN': config.csrf, 'X-RTC-Tab': tabId,
      },
      body: JSON.stringify(data),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const description = payload.message || `Gagal menghubungi server (${response.status})`;
      throw new Error(description);
    }
    return payload;
  }

  function toast(message) {
    el.toast.textContent = message;
    el.toast.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.toast.classList.remove('visible'), 3800);
  }

  function status(text, kind = 'waiting') {
    el.status.textContent = text;
    el.stageStatus.querySelector('.status-dot').className = `status-dot ${kind}`;
  }

  function buttons() {
    el.start.classList.toggle('hidden', active);
    el.stop.classList.toggle('hidden', !active);
    el.next.classList.toggle('hidden', !active);
    el.next.disabled = !currentRoom || isBusy;
    el.start.disabled = isBusy;
    el.stop.disabled = isBusy;
    el.mic.disabled = !active || !localStream?.getAudioTracks().length;
    el.cam.disabled = !active || !localStream?.getVideoTracks().length;
    el.screen.disabled = !active || !currentRoom || !navigator.mediaDevices?.getDisplayMedia;
    el.screen.classList.toggle('active', Boolean(screenStream));
    el.screen.querySelector('span').textContent = screenStream ? 'Stop sharing' : 'Share screen';
    const canChat = active && Boolean(currentRoom);
    el.chatInput.disabled = !canChat;
    el.send.disabled = !canChat;
    el.report.disabled = !canChat;
    el.chatStatus.textContent = canChat ? 'ONLINE' : 'OFFLINE';
  }

  function addMessage(content, mine = false, system = false) {
    const bubble = document.createElement('div');
    bubble.className = system ? 'system-msg' : `message${mine ? ' me' : ''}`;
    bubble.textContent = content; // Never inject untrusted chat content as HTML.
    el.chat.append(bubble);
    while (el.chat.children.length > 85) el.chat.firstElementChild.remove();
    el.chat.scrollTop = el.chat.scrollHeight;
  }

  function clearRemote() {
    el.remote.srcObject = null;
    el.remoteEmpty.classList.remove('hidden');
    el.remoteName.textContent = 'Stranger';
    el.mediaTag.textContent = 'REMOTE VIDEO';
  }

  function updateLocalPreview() {
    el.local.srcObject = localStream && localStream.getVideoTracks().length ? localStream : null;
    el.localEmpty.classList.toggle('hidden', Boolean(localStream?.getVideoTracks().length && localStream.getVideoTracks()[0].enabled));
    el.mic.classList.toggle('off', !localStream?.getAudioTracks()[0]?.enabled);
    el.cam.classList.toggle('off', !localStream?.getVideoTracks()[0]?.enabled);
    buttons();
  }

  async function acquireMedia() {
    if (!navigator.mediaDevices?.getUserMedia) {
      toast('Akses kamera butuh HTTPS atau localhost. Chat teks mungkin tetap bisa digunakan.');
      return null;
    }
    for (const constraints of [
      { audio: { echoCancellation: true, noiseSuppression: true }, video: { width: { ideal: 1280 }, height: { ideal: 720 } } },
      { audio: true, video: false },
      { audio: false, video: true },
    ]) {
      try { return await navigator.mediaDevices.getUserMedia(constraints); } catch { /* try the next media configuration */ }
    }
    toast('Izin kamera/mikrofon tidak tersedia. Kamu tetap bisa memakai chat teks.');
    return null;
  }

  async function sendSignal(type, payload, expectedRoom = currentRoom) {
    if (!expectedRoom || currentRoom !== expectedRoom) return;
    try { await api('signal', { type, payload }); }
    catch (error) { if (currentRoom === expectedRoom) console.warn('WebRTC signaling:', error.message); }
  }

  function releaseConnection() {
    epoch++;
    const oldPc = pc;
    pc = null;
    if (oldPc) { oldPc.ontrack = null; oldPc.onicecandidate = null; oldPc.close(); }
    if (screenStream) { screenStream.getTracks().forEach((track) => track.stop()); screenStream = null; }
    pendingCandidates = [];
    remoteStream = null;
    videoSender = null;
    currentRoom = null;
    cursor = 0;
    clearRemote();
    buttons();
  }

  async function setupConnection(room, initiator) {
    const connectionEpoch = epoch;
    remoteStream = new MediaStream();
    pc = new RTCPeerConnection({ iceServers: config.iceServers });
    const connection = pc;
    connection.onicecandidate = (event) => {
      if (event.candidate && pc === connection && currentRoom === room) {
        sendSignal('ice', event.candidate.toJSON(), room);
      }
    };
    connection.ontrack = (event) => {
      if (pc !== connection) return;
      if (!remoteStream.getTracks().some((t) => t.id === event.track.id)) remoteStream.addTrack(event.track);
      el.remote.srcObject = remoteStream;
      el.remoteEmpty.classList.add('hidden');
      event.track.addEventListener('ended', () => {
        if (pc === connection && remoteStream.getTracks().every((t) => t.readyState === 'ended')) clearRemote();
      });
    };
    connection.onconnectionstatechange = () => {
      if (pc !== connection) return;
      if (connection.connectionState === 'connected') status('Koneksi aman • Terhubung', 'connected');
      else if (connection.connectionState === 'failed') { status('Koneksi gagal • Coba Next', 'error'); toast('Koneksi WebRTC gagal. Konfigurasikan TURN untuk jaringan terbatas.'); }
      else if (connection.connectionState === 'disconnected') status('Koneksi terputus sementara', 'waiting');
    };

    const tracks = localStream?.getTracks() || [];
    for (const track of tracks) {
      const sender = connection.addTrack(track, localStream);
      if (track.kind === 'video') videoSender = sender;
    }
    if (!tracks.some((track) => track.kind === 'video')) {
      videoSender = connection.addTransceiver('video', { direction: 'sendrecv' }).sender;
    }
    if (!tracks.some((track) => track.kind === 'audio')) {
      connection.addTransceiver('audio', { direction: 'sendrecv' });
    }
    if (initiator) {
      const offer = await connection.createOffer();
      if (pc !== connection || epoch !== connectionEpoch) return;
      await connection.setLocalDescription(offer);
      await sendSignal('offer', { type: connection.localDescription.type, sdp: connection.localDescription.sdp }, room);
    }
  }

  async function consumeSignals(signals, room) {
    for (const entry of signals) {
      if (!pc || room !== currentRoom) return;
      try {
        if (entry.type === 'offer') {
          if (pc.signalingState !== 'stable') continue;
          await pc.setRemoteDescription(new RTCSessionDescription(entry.payload));
          await flushCandidates();
          const answer = await pc.createAnswer();
          await pc.setLocalDescription(answer);
          await sendSignal('answer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp }, room);
        } else if (entry.type === 'answer') {
          if (pc.signalingState !== 'have-local-offer') continue;
          await pc.setRemoteDescription(new RTCSessionDescription(entry.payload));
          await flushCandidates();
        } else if (entry.type === 'ice') {
          if (pc.remoteDescription) await pc.addIceCandidate(entry.payload);
          else pendingCandidates.push(entry.payload);
        } else if (entry.type === 'chat') {
          addMessage(entry.payload?.text || '');
        } else if (entry.type === 'screen') {
          el.mediaTag.textContent = entry.payload?.active ? 'LIVE SCREEN' : 'REMOTE VIDEO';
          addMessage(entry.payload?.active ? 'Pasangan mulai membagikan layar.' : 'Pasangan menghentikan berbagi layar.', false, true);
        }
      } catch (error) { console.warn('Gagal memproses sinyal', entry.type, error); }
    }
  }

  async function flushCandidates() {
    while (pc?.remoteDescription && pendingCandidates.length) {
      const candidate = pendingCandidates.shift();
      try { await pc.addIceCandidate(candidate); } catch (e) { console.warn('ICE candidate:', e); }
    }
  }

  async function applyState(data) {
    if (!active) return;
    if (data.status === 'paired' && data.room) {
      if (currentRoom !== data.room) {
        releaseConnection();
        currentRoom = data.room;
        status('Berhasil menemukan pasangan!', 'connected');
        el.remoteName.textContent = `Stranger · ${data.peer}`;
        el.placeholderTitle.textContent = 'Connecting…';
        el.placeholderDescription.textContent = 'Membangun koneksi WebRTC antarperangkat.';
        addMessage('Pasangan ditemukan! Membangun sambungan video…', false, true);
        buttons();
        await setupConnection(data.room, data.initiator);
      }
      cursor = data.cursor || cursor;
      await consumeSignals(data.signals || [], data.room);
    } else {
      if (currentRoom) {
        releaseConnection();
        addMessage('Pasangan telah keluar. Mencari pengguna lainnya…', false, true);
      }
      if (data.status === 'waiting') {
        status('Mencari pasangan online…');
        el.placeholderTitle.textContent = 'Looking for a connection…';
        el.placeholderDescription.textContent = 'Sedang mencari orang lain yang juga menekan Mulai.';
      } else {
        status('Siap untuk terhubung');
        el.placeholderTitle.textContent = 'Connections start here.';
        el.placeholderDescription.textContent = 'Tekan Mulai untuk menemukan pengguna lain yang sedang online.';
      }
      buttons();
    }
  }

  async function poll() {
    if (!active) return;
    if (isBusy) { pollTimer = setTimeout(poll, 1100); return; }
    try {
      const data = await api('state', { after: cursor });
      await applyState(data);
    } catch (error) {
      console.warn('Gagal mengambil status:', error);
      status('Server signaling tidak terjangkau', 'error');
    } finally {
      if (active) pollTimer = setTimeout(poll, 1100);
    }
  }

  async function start() {
    if (active || isBusy) return;
    isBusy = true;
    buttons();
    try {
      if (!window.isSecureContext) toast('Gunakan HTTPS untuk kamera dan screen sharing antarperangkat.');
      localStream = await acquireMedia();
      active = true;
      status('Mencari pasangan online…');
      updateLocalPreview();
      const data = await api('join');
      await applyState(data);
      clearTimeout(pollTimer);
      pollTimer = setTimeout(poll, 1100);
    } catch (error) {
      active = false;
      localStream?.getTracks().forEach((track) => track.stop());
      localStream = null;
      status('Gagal memulai • Coba lagi', 'error');
      toast(error.message);
    } finally { isBusy = false; buttons(); }
  }

  async function next() {
    if (!active || isBusy || !currentRoom) return;
    isBusy = true;
    clearTimeout(pollTimer);
    releaseConnection();
    status('Mencari orang berikutnya…');
    buttons();
    try { const result = await api('leave', { mode: 'next' }); await applyState(result); }
    catch (error) { toast(error.message); }
    finally { isBusy = false; buttons(); if (active) pollTimer = setTimeout(poll, 900); }
  }

  async function stop() {
    if (isBusy) return;
    isBusy = true;
    clearTimeout(pollTimer);
    active = false;
    releaseConnection();
    try { await api('leave', { mode: 'stop' }); } catch (error) { toast(error.message); }
    localStream?.getTracks().forEach((track) => track.stop());
    localStream = null;
    updateLocalPreview();
    status('Siap untuk terhubung');
    el.placeholderTitle.textContent = 'Connections start here.';
    el.placeholderDescription.textContent = 'Tekan Mulai untuk menemukan pengguna lain yang sedang online.';
    addMessage('Kamu telah keluar dari percakapan.', false, true);
    isBusy = false;
    buttons();
  }

  async function stopScreenSharing() {
    if (!screenStream) return;
    const stream = screenStream;
    screenStream = null;
    stream.getTracks().forEach((track) => { track.onended = null; track.stop(); });
    const original = localStream?.getVideoTracks()[0] || null;
    try { await videoSender?.replaceTrack(original); } catch (error) { console.warn(error); }
    if (currentRoom) await sendSignal('screen', { active: false });
    buttons();
  }

  async function toggleScreen() {
    if (screenStream) return stopScreenSharing();
    if (!pc || !videoSender) return;
    try {
      const display = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      const track = display.getVideoTracks()[0];
      await videoSender.replaceTrack(track);
      screenStream = display;
      track.onended = () => { if (screenStream === display) stopScreenSharing(); };
      await sendSignal('screen', { active: true });
      buttons();
    } catch (error) {
      if (error.name !== 'NotAllowedError') toast(`Tidak dapat membagikan layar: ${error.message}`);
    }
  }

  async function sendChat(event) {
    event.preventDefault();
    const text = el.chatInput.value.trim().slice(0, 1000);
    if (!text || !currentRoom) return;
    el.chatInput.value = '';
    try { await api('signal', { type: 'chat', payload: { text } }); addMessage(text, true); }
    catch (error) { el.chatInput.value = text; toast(error.message); }
  }

  async function report(event) {
    event.preventDefault();
    if (!active || !currentRoom || isBusy) return;
    isBusy = true;
    clearTimeout(pollTimer);
    el.reportDialog.close();
    releaseConnection();
    try {
      const data = await api('report', { reason: $('reportReason').value });
      await applyState(data);
      toast('Laporan disimpan. Sedang mencari pasangan baru.');
    } catch (error) { toast(error.message); }
    finally { isBusy = false; buttons(); if (active) pollTimer = setTimeout(poll, 900); }
  }

  el.start.addEventListener('click', start);
  el.next.addEventListener('click', next);
  el.stop.addEventListener('click', stop);
  el.screen.addEventListener('click', toggleScreen);
  el.chatForm.addEventListener('submit', sendChat);
  el.mic.addEventListener('click', () => {
    const track = localStream?.getAudioTracks()[0];
    if (track) { track.enabled = !track.enabled; updateLocalPreview(); }
  });
  el.cam.addEventListener('click', () => {
    const track = localStream?.getVideoTracks()[0];
    if (track) { track.enabled = !track.enabled; updateLocalPreview(); }
  });
  el.report.addEventListener('click', () => el.reportDialog.showModal());
  el.reportForm.addEventListener('submit', report);
  $('btnHelp').addEventListener('click', () => el.helpDialog.showModal());
  document.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
  window.addEventListener('pagehide', () => {
    clearTimeout(pollTimer);
    localStream?.getTracks().forEach((track) => track.stop());
    screenStream?.getTracks().forEach((track) => track.stop());
    if (active) {
      fetch(`${config.api}/leave`, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-RTC-Tab': tabId },
        body: JSON.stringify({ mode: 'stop' }),
      }).catch(() => {});
    }
  });
  buttons();
})();
