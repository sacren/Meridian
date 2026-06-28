<?php

namespace App\Console\Commands;

use App\Enums\BlastStatus;
use App\Enums\DeliveryStatus;
use App\Enums\EmailEventType;
use App\Enums\SegmentOperator;
use App\Models\Campaign;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds realistic volume into a dedicated benchmark campaign so the Performance
 * stage can measure the read paths (contact list, segment evaluation, campaign
 * analytics) against data at scale. It is the measurement spine's data source: a
 * manual dev tool, excluded from `composer setup` and CI, that refuses to run in
 * production.
 *
 * Isolation is by a dedicated campaign (slug prefixed {@see self::SLUG_PREFIX}),
 * so the seeded rows never touch the existing dev data and are fully
 * cascade-deletable — every child table (contacts, segments, blasts,
 * blast_recipients, email_events) is FK-cascaded from campaigns. The command is
 * re-runnable: it clears any prior benchmark campaign(s) first, then seeds a
 * fresh one, so re-running does not accumulate volume. There is no migrate:fresh.
 *
 * Realism, not just volume: names/emails vary (Faker), email domains are weighted
 * so "contains @gmail.com" matches a believable fraction, a realistic slice has a
 * null/empty phone, and events are spread across each blast's deliveries — uniform
 * data would give misleading index selectivity and match ratios and a worthless
 * baseline. Inserts are chunked so seeding ~50k rows does not exhaust memory.
 */
#[Signature('bench:seed-contacts {--count=50000 : The number of contacts to seed into the benchmark campaign}')]
#[Description('Seed a dedicated benchmark campaign with realistic volume for performance measurement (development only).')]
class SeedBenchmarkContacts extends Command
{
    /**
     * The slug prefix marking a campaign as benchmark data (cleared on re-run).
     */
    protected const SLUG_PREFIX = 'benchmark-';

    /**
     * How many rows to accumulate before flushing an insert, keeping peak memory
     * bounded regardless of the total seeded.
     */
    protected const CHUNK = 2_000;

    /**
     * The email domains contacts are spread across, weighted so a realistic
     * fraction land on gmail.com and most — but not all — end in ".com". The
     * weights are relative and need not sum to 100.
     *
     * @var array<string, int>
     */
    protected const DOMAINS = [
        'gmail.com' => 35,
        'yahoo.com' => 18,
        'hotmail.com' => 12,
        'outlook.com' => 10,
        'icloud.com' => 8,
        'example.com' => 9,
        'proton.me' => 5,
        'mail.net' => 3,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('bench:seed-contacts is a development helper and will not run in production.');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');

        if ($count < 1) {
            $this->error('The --count option must be a positive integer.');

            return self::FAILURE;
        }

        $cleared = $this->clearPriorBenchmarks();

        if ($cleared > 0) {
            $this->info("Cleared {$cleared} prior benchmark campaign(s).");
        }

        $campaign = $this->createBenchmarkCampaign();

        $this->seedContacts($campaign, $count);
        $contactIds = $this->seededContactIds($campaign);

        $this->seedSegments($campaign);
        $blastCount = $this->seedBlastsWithEvents($campaign, $contactIds);

        $this->newLine();
        $this->info("Seeded benchmark campaign [{$campaign->slug}] (id {$campaign->id}):");
        $this->line("  contacts: {$contactIds->count()}");
        $this->line('  segments: '.$campaign->segments()->count());
        $this->line("  blasts:   {$blastCount}");
        $this->line('  recipients: '.DB::table('blast_recipients')
            ->join('blasts', 'blasts.id', '=', 'blast_recipients.blast_id')
            ->where('blasts.campaign_id', $campaign->id)
            ->count());
        $this->line('  events:   '.DB::table('email_events')->where('campaign_id', $campaign->id)->count());

        return self::SUCCESS;
    }

    /**
     * Delete any campaign left behind by a previous run so the benchmark set does
     * not accumulate. The delete cascades to every child row through the schema's
     * foreign keys.
     */
    protected function clearPriorBenchmarks(): int
    {
        return Campaign::query()
            ->where('slug', 'like', self::SLUG_PREFIX.'%')
            ->get()
            ->each->delete()
            ->count();
    }

    /**
     * Create the fresh, uniquely-slugged campaign the run seeds into.
     */
    protected function createBenchmarkCampaign(): Campaign
    {
        return Campaign::query()->create([
            'name' => 'Benchmark Campaign',
            'slug' => self::SLUG_PREFIX.now()->format('YmdHis').'-'.Str::lower(Str::random(6)),
        ]);
    }

    /**
     * Seed the contacts in memory-bounded chunks. Names and emails vary, domains
     * are weighted, a realistic slice has a null/empty phone, some carry custom
     * fields, and created_at is spread across ~2 years so the list's sort has real
     * spread to order. Emails carry the row index so they stay unique within the
     * campaign without Faker's unique() memory overhead.
     */
    protected function seedContacts(Campaign $campaign, int $count): void
    {
        $this->info("Seeding {$count} contacts...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $first = fake()->firstName();
            $last = fake()->lastName();
            $handle = Str::of($first.'.'.$last)->lower()->replaceMatches('/[^a-z0-9.]/', '');

            $rows[] = [
                'campaign_id' => $campaign->id,
                'name' => $first.' '.$last,
                'email' => "{$handle}{$i}@".$this->weightedDomain(),
                'phone' => $this->realisticPhone(),
                'custom_fields' => $this->realisticCustomFields(),
                'created_at' => $this->spreadTimestamp(),
                'updated_at' => now(),
            ];

            if (count($rows) >= self::CHUNK) {
                DB::table('contacts')->insert($rows);
                $bar->advance(count($rows));
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('contacts')->insert($rows);
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * The ids of the campaign's seeded contacts, for sampling recipients.
     *
     * @return Collection<int, int>
     */
    protected function seededContactIds(Campaign $campaign): Collection
    {
        return DB::table('contacts')->where('campaign_id', $campaign->id)->pluck('id');
    }

    /**
     * Seed a handful of segments whose criteria match deliberately different
     * fractions of the population (a large ".com" set, a medium "@gmail.com" set,
     * a small "starts with a" set, and an "empty phone" slice), so the evaluator
     * baseline reflects varied selectivity rather than one match ratio.
     */
    protected function seedSegments(Campaign $campaign): void
    {
        $definitions = [
            ['Gmail contacts', SegmentOperator::Contains, 'email', '@gmail.com'],
            ['Dot-com addresses', SegmentOperator::EndsWith, 'email', '.com'],
            ['A names', SegmentOperator::StartsWith, 'name', 'a'],
            ['Missing phone', SegmentOperator::IsEmpty, 'phone', null],
        ];

        foreach ($definitions as [$name, $operator, $field, $value]) {
            $rule = ['field' => $field, 'operator' => $operator->value];

            if ($operator->requiresValue()) {
                $rule['value'] = $value;
            }

            $campaign->segments()->create([
                'name' => $name,
                'criteria' => ['combinator' => 'and', 'rules' => [$rule]],
            ]);
        }
    }

    /**
     * Seed a few sent blasts, each with a realistic slice of the campaign's
     * contacts as recipients (mostly sent, a few failed) and a plausible spread of
     * open/click/bounce events, so the analytics path has real volume to
     * aggregate. Recipients and events are inserted in memory-bounded chunks.
     *
     * @param  Collection<int, int>  $contactIds
     * @return int the number of blasts seeded
     */
    protected function seedBlastsWithEvents(Campaign $campaign, Collection $contactIds): int
    {
        $blastCount = 3;
        $perBlast = min($contactIds->count(), 4_000);

        $this->info("Seeding {$blastCount} blasts with recipients and events...");

        for ($b = 0; $b < $blastCount; $b++) {
            $blast = $campaign->blasts()->create([
                'subject' => fake()->sentence(),
                'body' => fake()->paragraphs(3, true),
                'status' => BlastStatus::Sent,
                'segment_id' => null,
            ]);

            $recipients = [];
            $events = [];

            foreach ($contactIds->shuffle()->take($perBlast) as $contactId) {
                $failed = mt_rand(1, 100) <= 8;

                $recipients[] = $this->recipientRow($blast->id, $contactId, $failed);

                foreach ($this->eventRowsFor($campaign->id, $blast->id, $contactId, $failed) as $event) {
                    $events[] = $event;
                }

                if (count($recipients) >= self::CHUNK) {
                    DB::table('blast_recipients')->insert($recipients);
                    $recipients = [];
                }

                if (count($events) >= self::CHUNK) {
                    DB::table('email_events')->insert($events);
                    $events = [];
                }
            }

            if ($recipients !== []) {
                DB::table('blast_recipients')->insert($recipients);
            }

            if ($events !== []) {
                DB::table('email_events')->insert($events);
            }
        }

        return $blastCount;
    }

    /**
     * A single delivery row: accepted (with a message id and sent_at) or rejected
     * (with failed_at), mirroring the send pipeline's two terminal states.
     *
     * @return array<string, mixed>
     */
    protected function recipientRow(int $blastId, int $contactId, bool $failed): array
    {
        return [
            'blast_id' => $blastId,
            'contact_id' => $contactId,
            'provider_message_id' => $failed ? null : (string) Str::uuid(),
            'status' => ($failed ? DeliveryStatus::Failed : DeliveryStatus::Sent)->value,
            'sent_at' => $failed ? null : now(),
            'failed_at' => $failed ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * The events a delivery produces: a bounce for a failure, or — for a sent one
     * — a likely open and, less often, a following click, matching the shape the
     * dev event simulator writes.
     *
     * @return list<array<string, mixed>>
     */
    protected function eventRowsFor(int $campaignId, int $blastId, int $contactId, bool $failed): array
    {
        if ($failed) {
            return [$this->eventRow($campaignId, $blastId, $contactId, EmailEventType::Bounce)];
        }

        $events = [];

        if (mt_rand(1, 100) <= 70) {
            $events[] = $this->eventRow($campaignId, $blastId, $contactId, EmailEventType::Open);

            if (mt_rand(1, 100) <= 40) {
                $events[] = $this->eventRow($campaignId, $blastId, $contactId, EmailEventType::Click);
            }
        }

        return $events;
    }

    /**
     * One event row, uniquely keyed so the provider_event_id dedupe index holds.
     *
     * @return array<string, mixed>
     */
    protected function eventRow(int $campaignId, int $blastId, int $contactId, EmailEventType $type): array
    {
        return [
            'campaign_id' => $campaignId,
            'blast_id' => $blastId,
            'contact_id' => $contactId,
            'type' => $type->value,
            'occurred_at' => now(),
            'provider_event_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Pick an email domain by its relative weight.
     */
    protected function weightedDomain(): string
    {
        $roll = mt_rand(1, array_sum(self::DOMAINS));

        foreach (self::DOMAINS as $domain => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $domain;
            }
        }

        return array_key_first(self::DOMAINS);
    }

    /**
     * A phone number, or — for a realistic slice — a null or blank one, so the
     * is_empty/is_not_empty operators have a real mix to select over.
     */
    protected function realisticPhone(): ?string
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 10 => null,
            $roll <= 18 => '',
            default => fake()->phoneNumber(),
        };
    }

    /**
     * Custom fields for a minority of contacts, null for the rest — the column is
     * sparsely populated in practice.
     *
     * @return string|null the JSON payload the raw insert stores
     */
    protected function realisticCustomFields(): ?string
    {
        if (mt_rand(1, 100) > 12) {
            return null;
        }

        return json_encode([
            'plan' => fake()->randomElement(['free', 'pro', 'enterprise']),
            'city' => fake()->city(),
        ]);
    }

    /**
     * A created_at spread across roughly the past two years, so ordering the list
     * by recency exercises a real distribution rather than a single instant.
     */
    protected function spreadTimestamp(): CarbonInterface
    {
        return now()
            ->subDays(mt_rand(0, 729))
            ->subSeconds(mt_rand(0, 86_399));
    }
}
