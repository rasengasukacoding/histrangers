<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Screen Share & Video Meeting - Laravel WebRTC</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .video-box { transform: scaleX(-1); }
        .screen-box { transform: scaleX(1); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans">

    <!-- Header / Navbar Top -->
    <header class="bg-slate-900 border-b border-slate-800 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 bg-red-600 text-white rounded-lg flex items-center justify-center font-bold">
                <i class="fas fa-desktop text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg leading-tight">Laravel ShareScreen</h1>
                <p class="text-xs text-slate-400">P2P Screen Sharing & Real-time Call</p>
            </div>
        </div>

        <!-- Room Controls / Status -->
        <div class="flex items-center space-x-3">
            <div id="statusBadge" class="px-3 py-1 bg-amber-500/10 text-amber-400 text-xs font-semibold rounded-full border border-amber-500/20 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Belum Terhubung</span>
            </div>
            <button id="btnCopyRoom" class="hidden px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg border border-slate-700 transition flex items-center gap-1.5">
                <i class="fas fa-copy"></i>
                <span>Salin ID Ruang</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col lg:flex-row overflow-hidden relative">

        <!-- Area Video Display -->
        <section class="flex-1 bg-slate-950 p-4 flex flex-col gap-4 overflow-y-auto">
            
            <!-- Panel Utama: Screen Share / Video Remote -->
            <div class="relative flex-1 min-h-[350px] bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden flex items-center justify-center shadow-xl">
                <!-- Video Remote (Teman / Screen Sharing) -->
                <video id="remoteVideo" autoplay playsinline class="w-full h-full object-contain screen-box"></video>
                
                <!-- Placeholder saat belum ada sinyal video -->
                <div id="remotePlaceholder" class="absolute flex flex-col items-center justify-center text-slate-500 p-6 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-800 flex items-center justify-center mb-3 text-3xl text-slate-600">
                        <i class="fas fa-[#FF2D20] fa-display"></i>
                    </div>
                    <h3 class="text-slate-300 font-semibold text-lg">Menunggu Tampilan Layar / Kamera</h3>
                    <p class="text-xs max-w-sm mt-1">Masukkan Room ID di panel sebelah kanan untuk memulai koneksi peer-to-peer.</p>
                </div>

                <!-- Picture-in-Picture: Local Video (Kamera Diri Sendiri) -->
                <div class="absolute bottom-4 right-4 w-44 h-32 bg-slate-950 rounded-xl border border-slate-700/60 overflow-hidden shadow-2xl">
                    <video id="localVideo" autoplay playsinline muted class="w-full h-full object-cover video-box"></video>
                    <div class="absolute bottom-1.5 left-2 bg-slate-900/80 backdrop-blur px-2 py-0.5 rounded text-[10px] font-medium text-slate-300">
                        Anda (Kamera)
                    </div>
                </div>
            </div>

            <!-- Toolbar Kontrol Aksi bawah -->
            <div class="bg-slate-900 border border-slate-800 p-3 rounded-2xl flex items-center justify-center gap-3">
                <button id="btnToggleMic" class="w-12 h-12 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center text-lg transition">
                    <i class="fas fa-microphone"></i>
                </button>
                <button id="btnToggleCam" class="w-12 h-12 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 flex items-center justify-center text-lg transition">
                    <i class="fas fa-video"></i>
                </button>
                <button id="btnShareScreen" class="px-5 h-12 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl flex items-center gap-2 transition text-sm shadow-lg shadow-red-600/20">
                    <i class="fas fa-desktop"></i>
                    <span>Bagikan Layar</span>
                </button>
                <button id="btnEndCall" class="w-12 h-12 rounded-xl bg-red-600/20 hover:bg-red-600 text-red-500 hover:text-white flex items-center justify-center text-lg transition">
                    <i class="fas fa-phone-slash"></i>
                </button>
            </div>
        </section>

        <!-- Sidebar Kanan: Sinyal & Chat Panel -->
        <aside class="w-full lg:w-80 bg-slate-900 border-t lg:border-t-0 lg:border-l border-slate-800 flex flex-col justify-between">
            
            <!-- Panel Koneksi Room ID -->
            <div class="p-4 border-b border-slate-800">
                <h2 class="text-sm font-semibold text-slate-300 mb-3 flex items-center gap-2">
                    <i class="fas fa-network-wired text-red-500"></i> Pengaturan Koneksi P2P
                </h2>
                
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">ID Ruangan (Room ID)</label>
                        <div class="flex gap-2">
                            <input type="text" id="roomIdInput" placeholder="Contoh: room-123" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-red-500">
                            <button id="btnConnect" class="bg-red-600 hover:bg-red-500 px-4 py-2 text-sm font-medium rounded-lg text-white transition">
                                Connect
                            </button>
                        </div>
                    </div>

                    <!-- Visual Manual Signaling Data (Skeptis Signaling Server) -->
                    <div class="pt-2">
                        <button id="toggleSignaling" class="text-xs text-slate-400 hover:text-slate-200 underline">
                            Tampilkan Data Signaling SDP
                        </button>
                        <div id="signalingArea" class="hidden mt-2 space-y-2">
                            <textarea id="localSdp" readonly rows="3" placeholder="Sinyal Lokal Anda..." class="w-full text-[10px] font-mono bg-slate-950 border border-slate-800 p-2 rounded text-slate-400 focus:outline-none"></textarea>
                            <textarea id="remoteSdp" rows="3" placeholder="Tempel Sinyal Remote..." class="w-full text-[10px] font-mono bg-slate-950 border border-slate-800 p-2 rounded text-slate-300 focus:outline-none"></textarea>
                            <button id="btnApplyRemoteSdp" class="w-full py-1 bg-slate-800 hover:bg-slate-700 text-xs text-slate-300 rounded border border-slate-700">
                                Terapkan Sinyal Remote
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel Chat Pesan -->
            <div class="flex-1 p-4 flex flex-col justify-between min-h-[200px]">
                <div>
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Obrolan Pesan</h3>
                    <div id="chatBox" class="h-44 lg:h-64 overflow-y-auto space-y-2 text-xs pr-1">
                        <div class="bg-slate-950/60 p-2 rounded-lg border border-slate-800 text-slate-400 italic">
                            Sistem: Masukkan Room ID untuk mulai berkirim pesan.
                        </div>
                    </div>
                </div>

                <!-- Input Chat -->
                <div class="mt-3 flex gap-2">
                    <input type="text" id="chatInput" placeholder="Ketik pesan..." class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-red-500">
                    <button id="btnSendChat" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-2 rounded-lg text-xs transition">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </aside>
    </main>

    <!-- WebRTC JavaScript Logic -->
    <script>
        const configuration = {
            iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
        };

        let peerConnection;
        let localStream;
        let screenStream;
        let dataChannel;

        // Element Referensi
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        const remotePlaceholder = document.getElementById('remotePlaceholder');
        const statusBadge = document.getElementById('statusBadge');
        
        const btnToggleMic = document.getElementById('btnToggleMic');
        const btnToggleCam = document.getElementById('btnToggleCam');
        const btnShareScreen = document.getElementById('btnShareScreen');
        const btnConnect = document.getElementById('btnConnect');
        const roomIdInput = document.getElementById('roomIdInput');
        
        let isMicOn = true;
        let isCamOn = true;

        // Inisialisasi Media Lokal (Kamera & Mic)
        async function initLocalStream() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                localVideo.srcObject = localStream;
            } catch (err) {
                console.error("Error mengaktifkan kamera/mic:", err);
            }
        }

        // WebRTC Connection Setup
        function createPeerConnection() {
            peerConnection = new RTCPeerConnection(configuration);

            // Tambahkan track lokal
            if (localStream) {
                localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
            }

            // Menerima stream dari peer remote
            peerConnection.ontrack = (event) => {
                remoteVideo.srcObject = event.streams[0];
                remotePlaceholder.classList.add('hidden');
                updateStatus('Terhubung', 'emerald');
            };

            // Ice Candidate Event
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    document.getElementById('localSdp').value = JSON.stringify(peerConnection.localDescription);
                }
            };

            // Setup Data Channel untuk Chat
            dataChannel = peerConnection.createDataChannel('chat');
            setupDataChannel(dataChannel);

            peerConnection.ondatachannel = (event) => {
                setupDataChannel(event.channel);
            };
        }

        // Feature Screen Sharing
        btnShareScreen.addEventListener('click', async () => {
            try {
                screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = screenStream.getVideoTracks()[0];

                if (peerConnection) {
                    const senders = peerConnection.getSenders();
                    const sender = senders.find(s => s.track?.kind === 'video');
                    if (sender) {
                        sender.replaceTrack(screenTrack);
                    }
                }

                localVideo.srcObject = screenStream;

                screenTrack.onended = () => {
                    if (peerConnection && localStream) {
                        const sender = peerConnection.getSenders().find(s => s.track?.kind === 'video');
                        if (sender) sender.replaceTrack(localStream.getVideoTracks()[0]);
                    }
                    localVideo.srcObject = localStream;
                };

            } catch (err) {
                console.error("Gagal melakukan Share Screen:", err);
            }
        });

        // Toggle Audio/Video
        btnToggleMic.addEventListener('click', () => {
            if (localStream) {
                isMicOn = !isMicOn;
                localStream.getAudioTracks()[0].enabled = isMicOn;
                btnToggleMic.classList.toggle('bg-red-600', !isMicOn);
            }
        });

        btnToggleCam.addEventListener('click', () => {
            if (localStream) {
                isCamOn = !isCamOn;
                localStream.getVideoTracks()[0].enabled = isCamOn;
                btnToggleCam.classList.toggle('bg-red-600', !isCamOn);
            }
        });

        // Manual Connection / Offer Generation
        btnConnect.addEventListener('click', async () => {
            if (!roomIdInput.value.trim()) return alert("Masukkan Room ID terlebih dahulu!");
            
            createPeerConnection();
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);
            
            document.getElementById('localSdp').value = JSON.stringify(offer);
            document.getElementById('signalingArea').classList.remove('hidden');
            updateStatus('Menunggu Jawaban...', 'amber');
        });

        document.getElementById('btnApplyRemoteSdp').addEventListener('click', async () => {
            const remoteSdpVal = document.getElementById('remoteSdp').value;
            if (!remoteSdpVal) return;

            const desc = JSON.parse(remoteSdpVal);
            if (!peerConnection) createPeerConnection();

            await peerConnection.setRemoteDescription(desc);

            if (desc.type === 'offer') {
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                document.getElementById('localSdp').value = JSON.stringify(answer);
            }
        });

        function setupDataChannel(channel) {
            channel.onmessage = (e) => appendChatMessage('Teman', e.data);
        }

        function appendChatMessage(sender, text) {
            const chatBox = document.getElementById('chatBox');
            const msgEl = document.createElement('div');
            msgEl.className = 'bg-slate-800 p-2 rounded-lg text-slate-200';
            // NEVER render remote peer data as HTML (prevents stored/reflected DOM XSS).
            const label = document.createElement('span');
            label.className = 'font-bold text-red-400';
            label.textContent = `${sender}: `;
            msgEl.append(label, document.createTextNode(String(text).slice(0, 1000)));
            chatBox.appendChild(msgEl);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        document.getElementById('btnSendChat').addEventListener('click', () => {
            const chatInput = document.getElementById('chatInput');
            if (chatInput.value.trim() && dataChannel && dataChannel.readyState === 'open') {
                dataChannel.send(chatInput.value);
                appendChatMessage('Anda', chatInput.value);
                chatInput.value = '';
            }
        });

        function updateStatus(text, color) {
            // Whitelist CSS variants and keep display text out of HTML parsing.
            const safeColor = ['emerald', 'amber', 'red'].includes(color) ? color : 'amber';
            statusBadge.className = `px-3 py-1 bg-${safeColor}-500/10 text-${safeColor}-400 text-xs font-semibold rounded-full border border-${safeColor}-500/20 flex items-center gap-2`;
            const dot = document.createElement('span');
            dot.className = `w-2 h-2 rounded-full bg-${safeColor}-400 animate-pulse`;
            const label = document.createElement('span');
            label.textContent = String(text).slice(0, 200);
            statusBadge.replaceChildren(dot, label);
        }

        document.getElementById('toggleSignaling').addEventListener('click', () => {
            document.getElementById('signalingArea').classList.toggle('hidden');
        });

        // Load awal
        initLocalStream();
    </script>
</body>
</html>