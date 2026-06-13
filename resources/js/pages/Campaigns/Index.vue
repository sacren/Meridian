<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, index } from '@/routes/campaigns';

type CampaignListItem = {
    id: number;
    name: string;
    slug: string;
    role: string;
};

defineProps<{
    campaigns: CampaignListItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: index() }],
    },
});
</script>

<template>
    <Head title="Campaigns" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading title="Campaigns" description="The campaigns you belong to." />

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="campaign in campaigns"
                :key="campaign.id"
                :href="dashboard(campaign.slug)"
                class="block"
            >
                <Card
                    class="flex h-full flex-col gap-2 p-4 transition-colors hover:border-primary"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ campaign.name }}</span>
                        <Badge variant="secondary">{{ campaign.role }}</Badge>
                    </div>
                    <span class="text-sm text-muted-foreground">{{
                        campaign.slug
                    }}</span>
                </Card>
            </Link>

            <p
                v-if="campaigns.length === 0"
                class="text-sm text-muted-foreground"
            >
                You don't belong to any campaigns yet. Create your first one
                below.
            </p>
        </div>

        <Card class="max-w-md p-4">
            <Heading
                variant="small"
                title="New campaign"
                description="You'll be added as its owner."
            />

            <Form
                v-bind="CampaignController.store.form()"
                class="mt-4 space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        placeholder="Campaign name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button type="submit" :disabled="processing">
                    Create campaign
                </Button>
            </Form>
        </Card>
    </div>
</template>
