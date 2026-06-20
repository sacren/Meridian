<?php

namespace Database\Factories;

use App\Enums\BlastStatus;
use App\Models\Blast;
use App\Models\Campaign;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blast>
 */
class BlastFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The target segment is created within the same campaign as the blast, so
     * the factory always yields a tenant-consistent draft.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'status' => BlastStatus::Draft,
            'segment_id' => fn (array $attributes) => Segment::factory()->create([
                'campaign_id' => $attributes['campaign_id'],
            ]),
        ];
    }
}
