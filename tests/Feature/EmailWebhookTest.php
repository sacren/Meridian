<?php

use App\Models\BlastRecipient;
use App\Models\EmailEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.email_webhook.secret', 'whook-secret');
});

/**
 * The signature header for a payload, signed with the configured test secret.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function emailWebhookHeaders(array $payload, string $secret = 'whook-secret'): array
{
    return ['X-Signature' => hash_hmac('sha256', json_encode($payload), $secret)];
}

test('a signed webhook records an attributed email event', function () {
    $recipient = BlastRecipient::factory()->sent()->create();
    $payload = [
        'event_id' => 'evt_open_1',
        'message_id' => $recipient->provider_message_id,
        'type' => 'open',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload, emailWebhookHeaders($payload))
        ->assertCreated()
        ->assertJson(['status' => 'recorded']);

    $this->assertDatabaseHas('email_events', [
        'provider_event_id' => 'evt_open_1',
        'campaign_id' => $recipient->blast->campaign_id,
        'blast_id' => $recipient->blast_id,
        'contact_id' => $recipient->contact_id,
        'type' => 'open',
    ]);
});

test('a webhook with no signature is rejected', function () {
    $recipient = BlastRecipient::factory()->sent()->create();
    $payload = [
        'event_id' => 'evt_1',
        'message_id' => $recipient->provider_message_id,
        'type' => 'open',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload)->assertForbidden();

    expect(EmailEvent::count())->toBe(0);
});

test('a webhook with a bad signature is rejected', function () {
    $recipient = BlastRecipient::factory()->sent()->create();
    $payload = [
        'event_id' => 'evt_1',
        'message_id' => $recipient->provider_message_id,
        'type' => 'open',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload, ['X-Signature' => 'not-the-real-hmac'])
        ->assertForbidden();

    expect(EmailEvent::count())->toBe(0);
});

test('a duplicate provider event id is ignored', function () {
    $existing = EmailEvent::factory()->create(['provider_event_id' => 'evt_dup']);
    $recipient = BlastRecipient::factory()->sent()->create();
    $payload = [
        'event_id' => 'evt_dup',
        'message_id' => $recipient->provider_message_id,
        'type' => 'click',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload, emailWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'duplicate']);

    expect(EmailEvent::count())->toBe(1)
        ->and(EmailEvent::first()->is($existing))->toBeTrue();
});

test('a webhook for an unknown message id is accepted but records nothing', function () {
    $payload = [
        'event_id' => 'evt_orphan',
        'message_id' => 'no-such-message',
        'type' => 'bounce',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload, emailWebhookHeaders($payload))
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    expect(EmailEvent::count())->toBe(0);
});

test('a webhook with an unknown event type is rejected as invalid', function () {
    $recipient = BlastRecipient::factory()->sent()->create();
    $payload = [
        'event_id' => 'evt_1',
        'message_id' => $recipient->provider_message_id,
        'type' => 'spam-complaint',
        'occurred_at' => now()->toISOString(),
    ];

    $this->postJson(route('webhooks.email'), $payload, emailWebhookHeaders($payload))
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('type');

    expect(EmailEvent::count())->toBe(0);
});
