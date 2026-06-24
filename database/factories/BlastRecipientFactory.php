<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlastRecipient>
 */
class BlastRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The contact is created within the blast's own campaign, so the factory
     * always yields a tenant-consistent delivery (recipient and blast share the
     * campaign). A fresh delivery starts pending, with no message id yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blast_id' => Blast::factory(),
            'contact_id' => fn (array $attributes) => Contact::factory()->create([
                'campaign_id' => Blast::find($attributes['blast_id'])->campaign_id,
            ]),
            'provider_message_id' => null,
            'status' => DeliveryStatus::Pending,
            'sent_at' => null,
            'failed_at' => null,
        ];
    }

    /**
     * A delivery the provider has accepted, carrying its message id.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Sent,
            'provider_message_id' => fake()->uuid(),
            'sent_at' => now(),
        ]);
    }

    /**
     * A delivery the provider rejected.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Failed,
            'failed_at' => now(),
        ]);
    }
}
