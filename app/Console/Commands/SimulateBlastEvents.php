<?php

namespace App\Console\Commands;

use App\Enums\BlastStatus;
use App\Enums\DeliveryStatus;
use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\EmailEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Populates {@see EmailEvent}s for already-sent blasts without a live provider —
 * fork #1's local event source. It stands in for inbound webhooks so the
 * analytics dashboard has open/click/bounce data to roll up in development. It is
 * dev-only and refuses to run in production, where real webhooks are the source.
 */
#[Signature('blast:simulate-events {blast? : The id of the blast to simulate; omit to cover every sent blast}')]
#[Description('Populate email events for sent blasts (development only).')]
class SimulateBlastEvents extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('blast:simulate-events is a development helper and will not run in production.');

            return self::FAILURE;
        }

        $blasts = $this->targetBlasts();

        if ($blasts->isEmpty()) {
            $this->warn('No sent blasts to simulate events for.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($blasts as $blast) {
            $created += $this->simulate($blast);
        }

        $this->info("Simulated {$created} email event(s) across {$blasts->count()} blast(s).");

        return self::SUCCESS;
    }

    /**
     * The sent blasts to simulate over: the one named by the argument, or all.
     *
     * @return Collection<int, Blast>
     */
    protected function targetBlasts(): Collection
    {
        $query = Blast::query()->where('status', BlastStatus::Sent);

        if ($this->argument('blast') !== null) {
            $query->whereKey($this->argument('blast'));
        }

        return $query->get();
    }

    /**
     * Write plausible events for one blast's deliveries: an open (and, for every
     * other one, a click) per accepted delivery, and a bounce per failed one.
     */
    protected function simulate(Blast $blast): int
    {
        $created = 0;

        foreach ($blast->recipients as $index => $recipient) {
            if ($recipient->status === DeliveryStatus::Failed) {
                $this->record($blast, $recipient, EmailEventType::Bounce);
                $created++;

                continue;
            }

            if ($recipient->status !== DeliveryStatus::Sent) {
                continue;
            }

            $this->record($blast, $recipient, EmailEventType::Open);
            $created++;

            if ($index % 2 === 0) {
                $this->record($blast, $recipient, EmailEventType::Click);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Persist a single simulated event attributed to the delivery's contact.
     */
    protected function record(Blast $blast, BlastRecipient $recipient, EmailEventType $type): void
    {
        EmailEvent::create([
            'campaign_id' => $blast->campaign_id,
            'blast_id' => $blast->id,
            'contact_id' => $recipient->contact_id,
            'type' => $type,
            'occurred_at' => now(),
            'provider_event_id' => (string) Str::uuid(),
        ]);
    }
}
