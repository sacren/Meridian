<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { index } from '@/routes/campaigns';
import { index as blastsIndex } from '@/routes/campaigns/blasts';
import { index as contactsIndex } from '@/routes/campaigns/contacts';
import { index as segmentsIndex } from '@/routes/campaigns/segments';

type Campaign = {
    id: number;
    name: string;
    slug: string;
};

defineProps<{
    campaign: Campaign;
    role: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: index() }],
    },
});
</script>

<template>
    <Head :title="campaign.name" />

    <div
        class="flex h-full flex-1 flex-col gap-6 p-4"
        data-test="campaign-dashboard"
    >
        <div class="flex items-center gap-3">
            <Heading :title="campaign.name" />
            <Badge variant="secondary" data-test="campaign-role">{{
                role
            }}</Badge>
        </div>

        <Card class="grid auto-rows-min gap-4 p-6 md:grid-cols-3">
            <div>
                <p class="text-sm text-muted-foreground">Campaign</p>
                <p class="font-medium">{{ campaign.name }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Slug</p>
                <p class="font-medium">{{ campaign.slug }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Your role</p>
                <p class="font-medium">{{ role }}</p>
            </div>
        </Card>

        <section class="space-y-3">
            <Heading variant="small" title="Manage" />

            <!--
                Destinations stack vertically; each is a self-contained
                card-as-link. Future sections (Segments, Blasts, Members)
                drop in here as sibling <Link> cards.
            -->
            <div class="flex max-w-md flex-col gap-4">
                <Link
                    :href="contactsIndex(campaign.slug)"
                    class="block"
                    data-test="contacts-link"
                >
                    <Card
                        class="flex flex-col gap-1 p-4 transition-colors hover:border-primary"
                    >
                        <span class="font-medium">Contacts</span>
                        <span class="text-sm text-muted-foreground">
                            View and manage this campaign's contacts.
                        </span>
                    </Card>
                </Link>

                <Link
                    :href="segmentsIndex(campaign.slug)"
                    class="block"
                    data-test="segments-link"
                >
                    <Card
                        class="flex flex-col gap-1 p-4 transition-colors hover:border-primary"
                    >
                        <span class="font-medium">Segments</span>
                        <span class="text-sm text-muted-foreground">
                            Build and preview saved contact filters.
                        </span>
                    </Card>
                </Link>

                <Link
                    :href="blastsIndex(campaign.slug)"
                    class="block"
                    data-test="blasts-link"
                >
                    <Card
                        class="flex flex-col gap-1 p-4 transition-colors hover:border-primary"
                    >
                        <span class="font-medium">Blasts</span>
                        <span class="text-sm text-muted-foreground">
                            Compose draft emails targeting a segment.
                        </span>
                    </Card>
                </Link>
            </div>
        </section>
    </div>
</template>
