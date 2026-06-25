<?php

use App\Enums\BlastStatus;
use App\Enums\DeliveryStatus;
use App\Enums\Role;
use App\Enums\SegmentOperator;
use App\Jobs\SendBlastRecipient;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Attach $user to $campaign with the given role, returning the user.
 */
function sendBlastMember(Campaign $campaign, Role $role): User
{
    $user = User::factory()->create();
    $campaign->users()->attach($user, ['role' => $role->value]);

    return $user;
}

/**
 * A Draft blast in $campaign targeting a segment with the given criteria.
 */
function sendBlastDraft(Campaign $campaign, ?array $criteria = null): Blast
{
    $segment = Segment::factory()->for($campaign)->create(['criteria' => $criteria]);

    return Blast::factory()->for($campaign)->create(['segment_id' => $segment->id]);
}

test('sending a blast writes one delivery and dispatches one job per recipient, then moves to Sending', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->count(3)->create();
    $blast = sendBlastDraft($campaign);

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    expect($blast->fresh()->status)->toBe(BlastStatus::Sending)
        ->and(BlastRecipient::where('blast_id', $blast->id)->count())->toBe(3)
        ->and(BlastRecipient::where('blast_id', $blast->id)->pluck('status')->all())
        ->toBe([DeliveryStatus::Pending, DeliveryStatus::Pending, DeliveryStatus::Pending]);

    Queue::assertPushed(SendBlastRecipient::class, 3);
});

test('the recipients are the target segment audience resolved via the evaluator', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    $matching = Contact::factory()->for($campaign)->create(['email' => 'keep@list.test']);
    Contact::factory()->for($campaign)->create(['email' => 'drop@other.test']);
    $blast = sendBlastDraft($campaign, [
        'combinator' => 'and',
        'rules' => [
            ['field' => 'email', 'operator' => SegmentOperator::Contains->value, 'value' => 'keep'],
        ],
    ]);

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    $recipients = BlastRecipient::where('blast_id', $blast->id)->pluck('contact_id');

    expect($recipients->all())->toBe([$matching->id]);

    Queue::assertPushed(SendBlastRecipient::class, 1);
});

test('a viewer may not send a blast', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $viewer = sendBlastMember($campaign, Role::Viewer);
    Contact::factory()->for($campaign)->create();
    $blast = sendBlastDraft($campaign);

    $this->actingAs($viewer)
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertForbidden();

    expect($blast->fresh()->status)->toBe(BlastStatus::Draft);
    Queue::assertNothingPushed();
});

test('a non-member may not send a blast', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $stranger = User::factory()->create();
    Contact::factory()->for($campaign)->create();
    $blast = sendBlastDraft($campaign);

    $this->actingAs($stranger)
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertForbidden();

    expect($blast->fresh()->status)->toBe(BlastStatus::Draft);
    Queue::assertNothingPushed();
});

test('a blast with no target segment cannot be sent', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create();
    $blast = Blast::factory()->for($campaign)->create(['segment_id' => null]);

    $this->actingAs($staffer)
        ->from(route('campaigns.blasts.index', $campaign))
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertRedirect(route('campaigns.blasts.index', $campaign));

    expect($blast->fresh()->status)->toBe(BlastStatus::Draft)
        ->and(BlastRecipient::where('blast_id', $blast->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a blast that has left Draft cannot be re-sent', function () {
    Queue::fake();

    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    Contact::factory()->for($campaign)->create();
    $segment = Segment::factory()->for($campaign)->create(['criteria' => null]);
    $blast = Blast::factory()->for($campaign)->sending()->create(['segment_id' => $segment->id]);

    $this->actingAs($staffer)
        ->post(route('campaigns.blasts.send', [$campaign, $blast]))
        ->assertForbidden();

    expect(BlastRecipient::where('blast_id', $blast->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a blast that has left Draft cannot be edited', function () {
    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->sending()->create();

    $this->actingAs($staffer)
        ->put(route('campaigns.blasts.update', [$campaign, $blast]), [
            'subject' => 'Too late',
            'body' => 'This edit must be blocked.',
        ])
        ->assertForbidden();

    expect($blast->fresh()->subject)->not->toBe('Too late');
});

test('a blast that has left Draft cannot be deleted', function () {
    $campaign = Campaign::factory()->create();
    $staffer = sendBlastMember($campaign, Role::Staffer);
    $blast = Blast::factory()->for($campaign)->sending()->create();

    $this->actingAs($staffer)
        ->delete(route('campaigns.blasts.destroy', [$campaign, $blast]))
        ->assertForbidden();

    $this->assertDatabaseHas('blasts', ['id' => $blast->id]);
});
