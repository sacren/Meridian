<?php

namespace App\Analytics;

use App\Enums\DeliveryStatus;
use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\EmailEvent;
use Illuminate\Support\Collection;

/**
 * Rolls a campaign's delivery and engagement data up into a read model for the
 * analytics page: per-blast metrics (recipients, sent, failed, opens, clicks,
 * bounces and their rates) plus campaign-wide totals.
 *
 * It owns no table — the numbers are always derived from {@see BlastRecipient}
 * and {@see EmailEvent}, so they cannot drift out of sync with the source rows.
 * {@see forCampaign()} computes the per-blast counts in SQL (a `COUNT`/`GROUP BY`
 * over each blast's delivery and event rows) and assembles the read model in PHP,
 * so it never materializes the recipient and event rows just to count them. The
 * pre-optimization Collection version survives as {@see forCampaignInMemory()},
 * the differential-test oracle this SQL path is proven equivalent to.
 */
class CampaignAnalytics
{
    /**
     * Build the campaign's analytics read model.
     *
     * The per-blast counts come from two grouped aggregate queries — one over
     * {@see BlastRecipient} (recipients/sent/failed), one over {@see EmailEvent}
     * (opens/clicks/bounces) — keyed by blast, so a blast absent from either
     * aggregate (no deliveries / no events) defaults every count to zero. The
     * blasts list itself is loaded without its rows, preserving the `latest()`
     * order the page and its tests read.
     *
     * @return array{
     *     totals: array<string, int|float>,
     *     blasts: list<array<string, int|float|string>>,
     * }
     */
    public function forCampaign(Campaign $campaign): array
    {
        $blasts = $campaign->blasts()
            ->latest()
            ->get(['id', 'subject', 'status']);

        $blastIds = $blasts->pluck('id')->all();

        $recipientCounts = BlastRecipient::query()
            ->whereIn('blast_id', $blastIds)
            ->groupBy('blast_id')
            ->selectRaw(
                'blast_id, COUNT(*) as recipients, SUM(status = ?) as sent, SUM(status = ?) as failed',
                [DeliveryStatus::Sent->value, DeliveryStatus::Failed->value],
            )
            ->get()
            ->keyBy('blast_id');

        $eventCounts = EmailEvent::query()
            ->whereIn('blast_id', $blastIds)
            ->groupBy('blast_id')
            ->selectRaw(
                'blast_id, SUM(type = ?) as opens, SUM(type = ?) as clicks, SUM(type = ?) as bounces',
                [EmailEventType::Open->value, EmailEventType::Click->value, EmailEventType::Bounce->value],
            )
            ->get()
            ->keyBy('blast_id');

        $metrics = $blasts->map(fn (Blast $blast): array => $this->assembleMetrics(
            $blast,
            $recipientCounts->get($blast->id),
            $eventCounts->get($blast->id),
        ));

        return [
            'totals' => $this->totals($metrics),
            'blasts' => $metrics->values()->all(),
        ];
    }

    /**
     * The pre-optimization in-memory rollup, retained verbatim as the oracle the
     * differential test asserts {@see forCampaign()} equal to. It loads the
     * campaign's blasts with their delivery and event rows and counts them in PHP
     * — correct but memory-heavy at volume, which is exactly why the production
     * path moved to SQL aggregates. Kept only as a test reference; not called in
     * production.
     *
     * @return array{
     *     totals: array<string, int|float>,
     *     blasts: list<array<string, int|float|string>>,
     * }
     */
    public function forCampaignInMemory(Campaign $campaign): array
    {
        $blasts = $campaign->blasts()
            ->with(['recipients', 'events'])
            ->latest()
            ->get()
            ->map(fn (Blast $blast): array => $this->blastMetrics($blast));

        return [
            'totals' => $this->totals($blasts),
            'blasts' => $blasts->values()->all(),
        ];
    }

    /**
     * The metrics for a single blast, assembled from its two keyed aggregate rows.
     * A missing row (the blast had no deliveries or no events) coerces to zero for
     * every count, and the `SUM`/`COUNT` results — which MySQL returns as
     * string/decimal — are cast back to int.
     *
     * @return array<string, int|float|string>
     */
    protected function assembleMetrics(Blast $blast, ?BlastRecipient $recipientCounts, ?EmailEvent $eventCounts): array
    {
        $recipients = (int) ($recipientCounts->recipients ?? 0);
        $sent = (int) ($recipientCounts->sent ?? 0);
        $failed = (int) ($recipientCounts->failed ?? 0);

        $opens = (int) ($eventCounts->opens ?? 0);
        $clicks = (int) ($eventCounts->clicks ?? 0);
        $bounces = (int) ($eventCounts->bounces ?? 0);

        return [
            'id' => $blast->id,
            'subject' => $blast->subject,
            'status' => $blast->status->value,
            'recipients' => $recipients,
            'sent' => $sent,
            'failed' => $failed,
            'opens' => $opens,
            'clicks' => $clicks,
            'bounces' => $bounces,
            'open_rate' => $this->rate($opens, $sent),
            'click_rate' => $this->rate($clicks, $sent),
            'bounce_rate' => $this->rate($bounces, $recipients),
        ];
    }

    /**
     * The metrics for a single blast, counted from its loaded delivery and event
     * rows — the {@see forCampaignInMemory()} oracle's per-blast counter.
     *
     * @return array<string, int|float|string>
     */
    protected function blastMetrics(Blast $blast): array
    {
        $recipients = $blast->recipients->count();
        $sent = $blast->recipients->where('status', DeliveryStatus::Sent)->count();
        $failed = $blast->recipients->where('status', DeliveryStatus::Failed)->count();

        $opens = $blast->events->where('type', EmailEventType::Open)->count();
        $clicks = $blast->events->where('type', EmailEventType::Click)->count();
        $bounces = $blast->events->where('type', EmailEventType::Bounce)->count();

        return [
            'id' => $blast->id,
            'subject' => $blast->subject,
            'status' => $blast->status->value,
            'recipients' => $recipients,
            'sent' => $sent,
            'failed' => $failed,
            'opens' => $opens,
            'clicks' => $clicks,
            'bounces' => $bounces,
            'open_rate' => $this->rate($opens, $sent),
            'click_rate' => $this->rate($clicks, $sent),
            'bounce_rate' => $this->rate($bounces, $recipients),
        ];
    }

    /**
     * The campaign-wide totals, summed from the per-blast metrics. Rates are
     * recomputed from the summed counts rather than averaged, so a campaign's
     * open rate is its total opens over its total delivered — not the mean of its
     * blasts' rates, which would over-weight small blasts.
     *
     * @param  Collection<int, array<string, int|float|string>>  $blasts
     * @return array<string, int|float>
     */
    protected function totals(Collection $blasts): array
    {
        $sum = fn (string $key): int => (int) $blasts->sum($key);

        $recipients = $sum('recipients');
        $sent = $sum('sent');
        $opens = $sum('opens');
        $clicks = $sum('clicks');
        $bounces = $sum('bounces');

        return [
            'recipients' => $recipients,
            'sent' => $sent,
            'failed' => $sum('failed'),
            'opens' => $opens,
            'clicks' => $clicks,
            'bounces' => $bounces,
            'open_rate' => $this->rate($opens, $sent),
            'click_rate' => $this->rate($clicks, $sent),
            'bounce_rate' => $this->rate($bounces, $recipients),
        ];
    }

    /**
     * A count expressed as a percentage of a denominator, rounded to one decimal
     * place. A zero denominator yields zero rather than dividing by zero.
     */
    protected function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round($numerator / $denominator * 100, 1);
    }
}
