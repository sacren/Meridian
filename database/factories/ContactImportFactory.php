<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\ContactImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactImport>
 */
class ContactImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A fresh import is pending, with the upload already stored on the s3 disk
     * under a tenant-scoped path and no counts or errors recorded yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'disk' => 's3',
            'path' => 'imports/'.fake()->uuid().'.csv',
            'status' => ImportStatus::Pending,
            'imported_count' => 0,
            'failed_count' => 0,
            'errors' => null,
            'completed_at' => null,
        ];
    }

    /**
     * An import the job has finished, with counts and a completion timestamp.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ImportStatus::Completed,
            'imported_count' => fake()->numberBetween(1, 50),
            'completed_at' => now(),
        ]);
    }

    /**
     * An import that could not be processed at all.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ImportStatus::Failed,
            'errors' => [['row' => 0, 'reason' => 'The import file could not be read.']],
            'completed_at' => now(),
        ]);
    }
}
