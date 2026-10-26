<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Guest 1-to-1 random matching + HTTP signaling for WebRTC.
 * Deliberately requires no additional Composer/npm packages.
 * All state mutation is serialized by a cache atomic lock.
 */
class RandomChatController extends Controller
{
    private const TIMEOUT_SECONDS = 35;
    private const MAX_SIGNALS = 100;

    public function page(): \Illuminate\View\View
    {
        return view('random-chat', [
            'iceServers' => $this->iceServers(),
        ]);
    }

    private function peerId(Request $request): string
    {
        // Distinct tabs using the same Laravel session must not impersonate one another.
        $tab = (string) $request->header('X-RTC-Tab', '');
        abort_unless((bool) preg_match('/^[a-f0-9-]{36}$/i', $tab), 422, 'Tab ID tidak valid.');
        return hash('sha256', $request->session()->getId().':'.strtolower($tab));
    }

    private function locked(callable $callback): mixed
    {
        return Cache::lock('rtc:matchmaking:v1', 10)->block(4, function () use ($callback) {
            return DB::transaction($callback, 3);
        });
    }

    private function register(string $peerId): void
    {
        if (!DB::table('rtc_peers')->where('id', $peerId)->exists()) {
            DB::table('rtc_peers')->insert([
                'id' => $peerId, 'status' => 'idle', 'partner_id' => null,
                'room_id' => null, 'last_seen_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('rtc_peers')->where('id', $peerId)
            ->update(['last_seen_at' => now(), 'updated_at' => now()]);
    }

    private function cleanup(): void
    {
        $stale = DB::table('rtc_peers')
            ->where('status', '!=', 'idle')
            ->where('last_seen_at', '<', now()->subSeconds(self::TIMEOUT_SECONDS))
            ->limit(80)->get();
        foreach ($stale as $peer) {
            if ($peer->partner_id) {
                DB::table('rtc_peers')
                    ->where('id', $peer->partner_id)
                    ->where('room_id', $peer->room_id)
                    ->where('partner_id', $peer->id)
                    ->update(['status' => 'waiting', 'partner_id' => null, 'room_id' => null, 'updated_at' => now()]);
            }
            DB::table('rtc_peers')->where('id', $peer->id)
                ->update(['status' => 'idle', 'partner_id' => null, 'room_id' => null, 'updated_at' => now()]);
        }
        // Periodic cleanup limits signaling and exclusion growth.
        if (random_int(1, 80) === 1) {
            DB::table('rtc_signals')->where('created_at', '<', now()->subMinutes(15))->delete();
            DB::table('rtc_exclusions')->where('expires_at', '<', now())->delete();
        }
    }

    private function pair(string $me): void
    {
        $self = DB::table('rtc_peers')->where('id', $me)->first();
        if (!$self || $self->status !== 'waiting') return;

        $waiting = DB::table('rtc_peers')
            ->where('status', 'waiting')->where('id', '!=', $me)
            ->where('last_seen_at', '>=', now()->subSeconds(self::TIMEOUT_SECONDS))
            ->orderBy('last_seen_at')->limit(100)->get();

        foreach ($waiting as $candidate) {
            [$left, $right] = strcmp($me, $candidate->id) < 0
                ? [$me, $candidate->id] : [$candidate->id, $me];
            $excluded = DB::table('rtc_exclusions')
                ->where('left_peer_id', $left)->where('right_peer_id', $right)
                ->where('expires_at', '>', now())->exists();
            if ($excluded) continue;

            $room = (string) Str::uuid();
            DB::table('rtc_peers')->where('id', $me)->where('status', 'waiting')
                ->update(['status' => 'paired', 'partner_id' => $candidate->id,
                    'room_id' => $room, 'updated_at' => now()]);
            DB::table('rtc_peers')->where('id', $candidate->id)->where('status', 'waiting')
                ->update(['status' => 'paired', 'partner_id' => $me,
                    'room_id' => $room, 'updated_at' => now()]);
            return;
        }
    }

    public function join(Request $request): JsonResponse
    {
        $me = $this->peerId($request);
        $response = $this->locked(function () use ($me) {
            $this->cleanup();
            $this->register($me);
            DB::table('rtc_peers')->where('id', $me)->where('status', 'idle')
                ->update(['status' => 'waiting', 'updated_at' => now()]);
            $this->pair($me);
            return $this->peerState($me, 0);
        });
        return response()->json($response)->header('Cache-Control', 'no-store');
    }

    public function state(Request $request): JsonResponse
    {
        $request->validate(['after' => 'sometimes|integer|min:0']);
        $me = $this->peerId($request);
        $after = (int) $request->input('after', 0);
        $data = $this->locked(function () use ($me, $after) {
            $this->cleanup();
            $this->register($me);
            // A stopped tab must NOT be silently requeued by a late polling request.
            // Only an explicit /join action moves a guest from idle to waiting.
            $this->pair($me);
            return $this->peerState($me, $after);
        });
        return response()->json($data)->header('Cache-Control', 'no-store');
    }

    private function peerState(string $me, int $after): array
    {
        $peer = DB::table('rtc_peers')->where('id', $me)->first();
        $messages = collect();
        if ($peer->status === 'paired') {
            $messages = DB::table('rtc_signals')
                ->where('to_peer_id', $me)->where('room_id', $peer->room_id)
                ->where('id', '>', $after)->orderBy('id')->limit(self::MAX_SIGNALS)->get();
        }
        return [
            'status' => $peer->status,
            'room' => $peer->room_id,
            'initiator' => $peer->status === 'paired' && strcmp($me, $peer->partner_id) < 0,
            'peer' => $peer->status === 'paired' ? substr($peer->partner_id, 0, 8) : null,
            'signals' => $messages->map(fn ($s) => [
                'id' => $s->id, 'type' => $s->type, 'payload' => json_decode($s->payload, true),
            ])->values(),
            'cursor' => $messages->isNotEmpty() ? $messages->last()->id : $after,
        ];
    }

    public function signal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['offer', 'answer', 'ice', 'chat', 'screen'])],
            'payload' => 'required|array',
        ]);
        $type = $validated['type'];
        $payload = $validated['payload'];
        if (in_array($type, ['offer', 'answer'], true)) {
            $sdp = $payload['sdp'] ?? null;
            abort_unless(($payload['type'] ?? null) === $type && is_string($sdp), 422, 'SDP tidak valid.');
            abort_if(strlen($sdp) > 65536 || strlen($sdp) < 5, 422, 'Panjang SDP tidak valid.');
            $payload = ['type' => $type, 'sdp' => $sdp];
        } elseif ($type === 'chat') {
            abort_unless(is_string($payload['text'] ?? null), 422, 'Pesan tidak valid.');
            $text = trim($payload['text']);
            abort_if($text === '' || mb_strlen($text) > 1000 || strlen($text) > 4000, 422, 'Pesan terlalu panjang atau kosong.');
            $payload = ['text' => $text];
        } elseif ($type === 'ice') {
            $candidate = $payload['candidate'] ?? null;
            abort_unless(is_string($candidate) && strlen($candidate) <= 4096, 422, 'ICE tidak valid.');
            $index = $payload['sdpMLineIndex'] ?? null;
            $mid = $payload['sdpMid'] ?? null;
            abort_unless($index === null || (is_int($index) && $index >= 0 && $index <= 255), 422, 'ICE index salah.');
            abort_unless($mid === null || (is_string($mid) && strlen($mid) <= 64), 422, 'ICE mid salah.');
            $fragment = $payload['usernameFragment'] ?? null;
            $payload = ['candidate' => $candidate, 'sdpMid' => $mid, 'sdpMLineIndex' => $index];
            if ($fragment !== null) {
                abort_unless(is_string($fragment) && strlen($fragment) <= 256, 422, 'ICE fragment salah.');
                $payload['usernameFragment'] = $fragment;
            }
        } elseif ($type === 'screen') {
            abort_unless(is_bool($payload['active'] ?? null), 422, 'Status screen tidak valid.');
            $payload = ['active' => $payload['active']];
        }
        $serialized = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        abort_if(strlen($serialized) > 130000, 413, 'Ukuran sinyal terlalu besar.');
        $me = $this->peerId($request);
        if ($type === 'chat') {
            abort_unless(RateLimiter::attempt('rtc:chat:'.$me, 20, fn () => true, 60), 429, 'Batas pesan chat: 20 per menit.');
        }
        $id = $this->locked(function () use ($me, $type, $serialized) {
            $peer = DB::table('rtc_peers')->where('id', $me)->first();
            abort_unless($peer && $peer->status === 'paired' && $peer->partner_id && $peer->room_id, 409, 'Belum berpasangan.');
            $other = DB::table('rtc_peers')->where('id', $peer->partner_id)->first();
            abort_unless($other && $other->room_id === $peer->room_id && $other->partner_id === $me, 409, 'Pasangan sudah terputus.');
            return DB::table('rtc_signals')->insertGetId([
                'room_id' => $peer->room_id,
                'from_peer_id' => $me, 'to_peer_id' => $other->id,
                'type' => $type, 'payload' => $serialized, 'created_at' => now(),
            ]);
        });
        return response()->json(['ok' => true, 'id' => $id]);
    }

    private function disconnect(string $me, string $mode, ?string $reason = null): void
    {
        $peer = DB::table('rtc_peers')->where('id', $me)->first();
        if (!$peer) return;
        if ($peer->status === 'paired' && $peer->partner_id && $peer->room_id) {
            $partner = DB::table('rtc_peers')->where('id', $peer->partner_id)->first();
            if ($partner && $partner->room_id === $peer->room_id && $partner->partner_id === $me) {
                DB::table('rtc_peers')->where('id', $partner->id)
                    ->update(['status' => 'waiting', 'partner_id' => null, 'room_id' => null, 'updated_at' => now()]);
            }
            [$left, $right] = strcmp($me, $peer->partner_id) < 0
                ? [$me, $peer->partner_id] : [$peer->partner_id, $me];
            DB::table('rtc_exclusions')->updateOrInsert(
                ['left_peer_id' => $left, 'right_peer_id' => $right],
                ['expires_at' => now()->addMinutes($reason ? 60 * 24 * 365 : 10)]
            );
            if ($reason !== null) {
                DB::table('rtc_reports')->insert([
                    'reporter_peer_id' => $me, 'reported_peer_id' => $peer->partner_id,
                    'room_id' => $peer->room_id, 'reason' => $reason, 'created_at' => now(),
                ]);
            }
        }
        DB::table('rtc_peers')->where('id', $me)
            ->update(['status' => $mode === 'next' ? 'waiting' : 'idle',
                'partner_id' => null, 'room_id' => null,
                'last_seen_at' => now(), 'updated_at' => now()]);
    }

    public function leave(Request $request): JsonResponse
    {
        $validated = $request->validate(['mode' => ['required', Rule::in(['next', 'stop'])]]);
        $me = $this->peerId($request);
        $data = $this->locked(function () use ($me, $validated) {
            $this->disconnect($me, $validated['mode']);
            if ($validated['mode'] === 'next') $this->pair($me);
            return $this->peerState($me, 0);
        });
        return response()->json($data);
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', Rule::in(['harassment', 'nudity', 'spam', 'other'])]]);
        $me = $this->peerId($request);
        $data = $this->locked(function () use ($me, $validated) {
            $peer = DB::table('rtc_peers')->where('id', $me)->first();
            abort_unless($peer && $peer->status === 'paired' && $peer->partner_id, 409, 'Tidak ada pasangan untuk dilaporkan.');
            $this->disconnect($me, 'next', $validated['reason']);
            $this->pair($me);
            return $this->peerState($me, 0);
        });
        return response()->json($data);
    }

    private function iceServers(): array
    {
        $servers = [['urls' => ['stun:stun.l.google.com:19302']]];
        $url = trim((string) config('rtc.turn_url'));
        if ($url === '') return $servers;

        // TURN REST / coturn: expiring HMAC credentials; shared secret stays on server.
        $secret = (string) config('rtc.turn_shared_secret');
        if ($secret !== '') {
            $ttl = min(86400, max(600, (int) config('rtc.turn_ttl', 3600)));
            $username = (time() + $ttl).':'.bin2hex(random_bytes(8));
            $credential = base64_encode(hash_hmac('sha1', $username, $secret, true));
            $servers[] = ['urls' => [$url], 'username' => $username, 'credential' => $credential];
        } elseif (!app()->environment('production')) {
            $user = trim((string) config('rtc.turn_username'));
            $pass = (string) config('rtc.turn_credential');
            if ($user !== '' && $pass !== '') {
                $servers[] = ['urls' => [$url], 'username' => $user, 'credential' => $pass];
            }
        }
        return $servers;
    }
}
