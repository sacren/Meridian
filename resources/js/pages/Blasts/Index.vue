<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BlastController from '@/actions/App/Http/Controllers/BlastController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, index as campaignsIndex } from '@/routes/campaigns';

type Campaign = {
    id: number;
    name: string;
    slug: string;
};

type SegmentOption = {
    id: number;
    name: string;
};

type BlastStatus = 'draft' | 'sending' | 'sent' | 'failed';

type Blast = {
    id: number;
    subject: string;
    body: string;
    status: BlastStatus;
    segment_id: number | null;
    segment: SegmentOption | null;
    recipients_count: number;
    sent_recipients_count: number;
};

type StatusBadge = {
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    class?: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    total: number;
};

const props = defineProps<{
    campaign: Campaign;
    blasts: Paginator<Blast>;
    segments: SegmentOption[];
    canManageContent: boolean;
}>();

// A blast is send-once: it is composable only while Draft and becomes read-only
// the moment it leaves that state, mirroring the server-side policy lock so the
// UI never offers an action the server would reject.
const statusBadges: Record<BlastStatus, StatusBadge> = {
    draft: { variant: 'secondary' },
    sending: {
        variant: 'outline',
        class: 'animate-pulse text-muted-foreground',
    },
    sent: {
        variant: 'default',
        class: 'border-transparent bg-green-600 text-white',
    },
    failed: { variant: 'destructive' },
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: campaignsIndex() }],
    },
});

const editingId = ref<number | null>(null);

const form = useForm<{
    subject: string;
    body: string;
    segment_id: number | null;
}>({
    subject: '',
    body: '',
    segment_id: null,
});

const isEditing = computed(() => editingId.value !== null);

function resetForm(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function startCreate(): void {
    resetForm();
}

function startEdit(blast: Blast): void {
    editingId.value = blast.id;
    form.clearErrors();
    form.subject = blast.subject;
    form.body = blast.body;
    form.segment_id = blast.segment_id;
}

function submit(): void {
    if (isEditing.value) {
        form.put(
            BlastController.update.url([
                props.campaign.slug,
                editingId.value as number,
            ]),
            { preserveScroll: true, onSuccess: () => resetForm() },
        );

        return;
    }

    form.post(BlastController.store.url(props.campaign.slug), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

function targetName(blast: Blast): string {
    return blast.segment?.name ?? 'No target';
}

function isDraft(blast: Blast): boolean {
    return blast.status === 'draft';
}

// A Draft may be sent only once it has a target and the caller can manage
// content; the button is otherwise hidden rather than shown-then-rejected.
function canSend(blast: Blast): boolean {
    return (
        props.canManageContent && isDraft(blast) && blast.segment_id !== null
    );
}

function send(blast: Blast): void {
    router.post(BlastController.send.url([props.campaign.slug, blast.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${campaign.name} blasts`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                :title="`${campaign.name} blasts`"
                :description="`${blasts.total} blast(s) in this campaign.`"
            />
            <Link
                :href="dashboard(campaign.slug)"
                class="text-sm text-muted-foreground hover:text-foreground"
            >
                Back to dashboard
            </Link>
        </div>

        <Card class="overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-2 font-medium">Subject</th>
                        <th class="px-4 py-2 font-medium">Target</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="blast in blasts.data"
                        :key="blast.id"
                        class="border-b last:border-0"
                        :data-test="`blast-row-${blast.id}`"
                    >
                        <td class="px-4 py-2">{{ blast.subject }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ targetName(blast) }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-col items-start gap-1">
                                <Badge
                                    :variant="
                                        statusBadges[blast.status].variant
                                    "
                                    :class="statusBadges[blast.status].class"
                                    class="capitalize"
                                    :data-test="`blast-status-${blast.id}`"
                                >
                                    {{ blast.status }}
                                </Badge>
                                <span
                                    v-if="blast.recipients_count > 0"
                                    class="text-xs text-muted-foreground"
                                    :data-test="`blast-sent-count-${blast.id}`"
                                >
                                    {{ blast.sent_recipients_count }}/{{
                                        blast.recipients_count
                                    }}
                                    sent
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="canSend(blast)"
                                    type="button"
                                    size="sm"
                                    :data-test="`send-blast-${blast.id}`"
                                    @click="send(blast)"
                                >
                                    Send
                                </Button>
                                <Button
                                    v-if="isDraft(blast)"
                                    variant="outline"
                                    size="sm"
                                    @click="startEdit(blast)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    v-if="isDraft(blast)"
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="
                                        router.delete(
                                            BlastController.destroy.url([
                                                campaign.slug,
                                                blast.id,
                                            ]),
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    Delete
                                </Button>
                                <span
                                    v-if="!isDraft(blast)"
                                    class="text-xs text-muted-foreground"
                                >
                                    Read-only
                                </span>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="blasts.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            No blasts yet. Compose your first one below.
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>

        <Card class="max-w-2xl space-y-4 p-4" data-test="blast-form">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    :title="isEditing ? 'Edit blast' : 'New blast'"
                    description="Compose the email and choose who it targets."
                />
                <div class="flex items-center gap-2">
                    <Badge variant="secondary">Draft</Badge>
                    <Button
                        v-if="isEditing"
                        variant="ghost"
                        size="sm"
                        @click="startCreate"
                    >
                        Cancel
                    </Button>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="blast-subject">Subject</Label>
                    <Input
                        id="blast-subject"
                        v-model="form.subject"
                        required
                        autocomplete="off"
                        data-test="blast-subject-input"
                    />
                    <InputError :message="form.errors.subject" />
                </div>

                <div class="grid gap-2">
                    <Label for="blast-body">Body</Label>
                    <textarea
                        id="blast-body"
                        v-model="form.body"
                        rows="6"
                        required
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        data-test="blast-body-input"
                    ></textarea>
                    <InputError :message="form.errors.body" />
                </div>

                <div class="grid gap-2">
                    <Label for="blast-segment">Target segment</Label>
                    <select
                        id="blast-segment"
                        v-model="form.segment_id"
                        class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        data-test="blast-segment-select"
                    >
                        <option :value="null">No target</option>
                        <option
                            v-for="segment in segments"
                            :key="segment.id"
                            :value="segment.id"
                        >
                            {{ segment.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.segment_id" />
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-blast"
                    >
                        {{ isEditing ? 'Save changes' : 'Create blast' }}
                    </Button>
                </div>
            </form>
        </Card>
    </div>
</template>
