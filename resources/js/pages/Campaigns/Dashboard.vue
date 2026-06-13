<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { index } from '@/routes/campaigns';

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

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center gap-3">
            <Heading :title="campaign.name" />
            <Badge variant="secondary">{{ role }}</Badge>
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
    </div>
</template>
