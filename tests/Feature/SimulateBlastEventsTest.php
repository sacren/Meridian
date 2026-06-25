<?php

use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\EmailEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the command populates events for a sent blast', function () {
    $blast = Blast::factory()->sent()->create();
    BlastRecipient::factory()->for($blast)->sent()->create();
    BlastRecipient::factory()->for($blast)->sent()->create();
    BlastRecipient::factory()->for($blast)->failed()->create();

    $this->artisan('blast:simulate-events', ['blast' => $blast->id])
        ->assertSuccessful();

    $events = EmailEvent::where('blast_id', $blast->id);

    expect($events->clone()->where('type', EmailEventType::Open)->count())->toBe(2)
        ->and($events->clone()->where('type', EmailEventType::Click)->count())->toBe(1)
        ->and($events->clone()->where('type', EmailEventType::Bounce)->count())->toBe(1)
        ->and($events->clone()->where('campaign_id', '!=', $blast->campaign_id)->count())->toBe(0);
});

test('the command ignores blasts that have not been sent', function () {
    $draft = Blast::factory()->create();
    BlastRecipient::factory()->for($draft)->sent()->create();

    $this->artisan('blast:simulate-events')->assertSuccessful();

    expect(EmailEvent::count())->toBe(0);
});

test('the command refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $blast = Blast::factory()->sent()->create();
    BlastRecipient::factory()->for($blast)->sent()->create();

    $this->artisan('blast:simulate-events', ['blast' => $blast->id])
        ->assertFailed();

    expect(EmailEvent::count())->toBe(0);
});
