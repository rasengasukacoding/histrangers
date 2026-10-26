<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RandomChatTest extends TestCase
{
    use RefreshDatabase;

    private const TAB_A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const TAB_B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    public function test_two_guest_tabs_can_match_and_exchange_signals(): void
    {
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/join')
            ->assertOk()->assertJsonPath('status', 'waiting');
        $matched = $this->withHeader('X-RTC-Tab', self::TAB_B)->postJson('/random/api/join');
        $matched->assertOk()->assertJsonPath('status', 'paired');
        $this->assertNotEmpty($matched->json('room'));

        $first = $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/state', ['after' => 0]);
        $first->assertOk()->assertJsonPath('status', 'paired');
        $this->assertSame($matched->json('room'), $first->json('room'));

        $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/signal', ['type' => 'chat', 'payload' => ['text' => 'Halo!']])
            ->assertOk();
        $received = $this->withHeader('X-RTC-Tab', self::TAB_B)
            ->postJson('/random/api/state', ['after' => 0]);
        $received->assertOk()->assertJsonPath('signals.0.type', 'chat')
            ->assertJsonPath('signals.0.payload.text', 'Halo!');

        $this->withHeader('X-RTC-Tab', self::TAB_B)
            ->postJson('/random/api/leave', ['mode' => 'stop'])->assertOk()->assertJsonPath('status', 'idle');
        $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/state', ['after' => 0])->assertOk()->assertJsonPath('status', 'waiting');
    }

    public function test_invalid_signal_cannot_be_sent_without_a_partner(): void
    {
        $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/join')->assertOk();
        $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/signal', ['type' => 'chat', 'payload' => ['text' => 'Hello']])
            ->assertStatus(409);
    }

    public function test_stopping_a_tab_does_not_requeue_it_when_state_is_polled(): void
    {
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/join')->assertOk();
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/leave', ['mode' => 'stop'])->assertOk()->assertJsonPath('status', 'idle');
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/state', ['after' => 0])->assertOk()->assertJsonPath('status', 'idle');
    }

    public function test_report_without_partner_is_rejected(): void
    {
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/join')->assertOk();
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/report', ['reason' => 'spam'])->assertStatus(409);
    }

    public function test_chat_rejects_excessively_long_messages(): void
    {
        $this->withHeader('X-RTC-Tab', self::TAB_A)->postJson('/random/api/join')->assertOk();
        $this->withHeader('X-RTC-Tab', self::TAB_B)->postJson('/random/api/join')->assertOk();
        $this->withHeader('X-RTC-Tab', self::TAB_A)
            ->postJson('/random/api/signal', ['type' => 'chat', 'payload' => ['text' => str_repeat('A', 1001)]])
            ->assertStatus(422);
    }

    public function test_random_page_has_security_headers_and_nonce_csp(): void
    {
        $response = $this->get('/random');
        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $response->headers->get('Content-Security-Policy', ''));
    }

}
