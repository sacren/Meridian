<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { dashboard, index as campaignsIndex } from '@/routes/campaigns';

type Campaign = {
    id: number;
    name: string;
    slug: string;
};

type BlastStatus = 'draft' | 'sending' | 'sent' | 'failed';

type BlastMetrics = {
    id: number;
    subject: string;
    status: BlastStatus;
    recipients: number;
    sent: number;
    failed: number;
    opens: number;
    clicks: number;
    bounces: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
};

type Totals = {
    recipients: number;
    sent: number;
    failed: number;
    opens: number;
    clicks: number;
    bounces: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
};

const props = defineProps<{
    campaign: Campaign;
    analytics: {
        totals: Totals;
        blasts: BlastMetrics[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: campaignsIndex() }],
    },
});

const statusVariants: Record<
    BlastStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    sending: 'outline',
    sent: 'default',
    failed: 'destructive',
};

// The three engagement rates the summary charts as bars, in display order.
const rateBars = computed(() => [
    { label: 'Open rate', value: props.analytics.totals.open_rate },
    { label: 'Click rate', value: props.analytics.totals.click_rate },
    { label: 'Bounce rate', value: props.analytics.totals.bounce_rate },
]);

const totalTiles = computed(() => [
    { label: 'Recipients', value: props.analytics.totals.recipients },
    { label: 'Sent', value: props.analytics.totals.sent },
    { label: 'Opens', value: props.analytics.totals.opens },
    { label: 'Clicks', value: props.analytics.totals.clicks },
    { label: 'Bounces', value: props.analytics.totals.bounces },
]);
</script>

<template>
    <Head :title="`${campaign.name} analytics`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                :title="`${campaign.name} analytics`"
                :description="`Delivery and engagement across ${analytics.blasts.length} blast(s).`"
            />
            <Link
                :href="dashboard(campaign.slug)"
                class="text-sm text-muted-foreground hover:text-foreground"
            >
                Back to dashboard
            </Link>
        </div>

        <Card class="space-y-6 p-6" data-test="analytics-totals">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div
                    v-for="tile in totalTiles"
                    :key="tile.label"
                    class="flex flex-col gap-1"
                >
                    <span class="text-sm text-muted-foreground">{{
                        tile.label
                    }}</span>
                    <span class="text-2xl font-semibold">{{ tile.value }}</span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="bar in rateBars"
                    :key="bar.label"
                    class="flex flex-col gap-1"
                >
                    <div
                        class="flex items-center justify-between text-sm text-muted-foreground"
                    >
                        <span>{{ bar.label }}</span>
                        <span class="font-medium text-foreground"
                            >{{ bar.value }}%</span
                        >
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${Math.min(bar.value, 100)}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </Card>

        <Card class="overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-2 font-medium">Blast</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Recipients
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Sent</th>
                        <th class="px-4 py-2 text-right font-medium">Opens</th>
                        <th class="px-4 py-2 text-right font-medium">Clicks</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Bounces
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Open %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="blast in analytics.blasts"
                        :key="blast.id"
                        class="border-b last:border-0"
                        :data-test="`analytics-row-${blast.id}`"
                    >
                        <td class="px-4 py-2">{{ blast.subject }}</td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="statusVariants[blast.status]"
                                class="capitalize"
                            >
                                {{ blast.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2 text-right">
                            {{ blast.recipients }}
                        </td>
                        <td class="px-4 py-2 text-right">{{ blast.sent }}</td>
                        <td class="px-4 py-2 text-right">{{ blast.opens }}</td>
                        <td class="px-4 py-2 text-right">{{ blast.clicks }}</td>
                        <td class="px-4 py-2 text-right">
                            {{ blast.bounces }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            {{ blast.open_rate }}%
                        </td>
                    </tr>

                    <tr v-if="analytics.blasts.length === 0">
                        <td
                            colspan="8"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            No blasts yet. Analytics appear once a blast is
                            sent.
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>
    </div>
</template>
