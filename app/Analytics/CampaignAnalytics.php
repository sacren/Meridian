<?php

namespace App\Analytics;

use App\Enums\DeliveryStatus;
use App\Enums\EmailEventType;
use App\Models\Blast;
use App\Models\BlastRecipient;
use App\Models\Campaign;
use App\Models\EmailEvent;
use App\Segments\SegmentEvaluator;
use Illuminate\Support\Collection;

/**
 * Rolls a campaign's delivery and engagement data up into a read model for the
 * analytics page: per-blast metrics (recipients, sent, failed, opens, clicks,
 * bounces and their rates) plus campaign-wide totals.
 *
 * Like {@see SegmentEvaluator}, this is a Collection pipeline: it
 * loads the campaign's blasts with their delivery and event rows and aggregates
 * them in memory. It owns no table — the numbers are always derived from
 * {@see BlastRecipient} and {@see EmailEvent}, so they
 * cannot drift out of sync with the source rows. The Performance stage may later
 * swap the in-memory aggregation for tuned SQL without changing this class's
 * public surface or the shape it returns.
 */
class CampaignAnalytics
{
    /**
     * Build the campaign's analytics read model.
     *
     * @return array{
     *     totals: array<string, int|float>,
     *     blasts: list<array<string, int|float|string>>,
     * }
     */
    public function forCampaign(Campaign $campaign): array
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
     * The metrics for a single blast, counted from its loaded delivery and event
     * rows.
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
