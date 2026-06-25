<?php

namespace Database\Factories;

use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailEvent>
 */
class EmailEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The blast, its campaign, and the contact are all kept within one campaign,
     * so the factory yields a tenant-consistent event (the campaign, blast, and
     * contact all agree). The provider event id is unique for dedupe.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blast = Blast::factory()->create();

        return [
            'campaign_id' => $blast->campaign_id,
            'blast_id' => $blast->id,
            'contact_id' => Contact::factory()->create([
                'campaign_id' => $blast->campaign_id,
            ]),
            'type' => fake()->randomElement(EmailEventType::cases()),
            'occurred_at' => now(),
            'provider_event_id' => fake()->unique()->uuid(),
        ];
    }
}
