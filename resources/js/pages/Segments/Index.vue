<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SegmentController from '@/actions/App/Http/Controllers/SegmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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

type CriteriaRule = {
    field: string;
    operator: string;
    value: string;
};

type Criteria = {
    combinator: 'and' | 'or';
    rules: CriteriaRule[];
};

type Segment = {
    id: number;
    name: string;
    criteria: Criteria | null;
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

type PreviewContact = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};

const props = defineProps<{
    campaign: Campaign;
    segments: Paginator<Segment>;
    preview?: { count: number; contacts: PreviewContact[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: campaignsIndex() }],
    },
});

// These mirror the backend's single sources of truth — SegmentEvaluator::FIELDS
// and the SegmentOperator enum. The labels are a UI concern and live only here.
const FIELDS = [
    { value: 'name', label: 'Name' },
    { value: 'email', label: 'Email' },
    { value: 'phone', label: 'Phone' },
];

const OPERATORS = [
    { value: 'equals', label: 'Equals', requiresValue: true },
    { value: 'contains', label: 'Contains', requiresValue: true },
    { value: 'starts_with', label: 'Starts with', requiresValue: true },
    { value: 'ends_with', label: 'Ends with', requiresValue: true },
    { value: 'is_empty', label: 'Is empty', requiresValue: false },
    { value: 'is_not_empty', label: 'Is not empty', requiresValue: false },
];

function operatorRequiresValue(operator: string): boolean {
    return OPERATORS.find((o) => o.value === operator)?.requiresValue ?? true;
}

const editingId = ref<number | null>(null);

const form = useForm<{ name: string; criteria: Criteria }>({
    name: '',
    criteria: { combinator: 'and', rules: [] },
});

const isEditing = computed(() => editingId.value !== null);

// The preview result is held locally rather than read straight from the prop, so
// it can be cleared on any client-only form transition (cancel, edit, reset).
// Otherwise a stale preview would linger, no longer matching the builder.
const previewResult = ref<{ count: number; contacts: PreviewContact[] } | null>(
    props.preview ?? null,
);

function resetForm(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    previewResult.value = null;
}

function startCreate(): void {
    resetForm();
}

function startEdit(segment: Segment): void {
    editingId.value = segment.id;
    form.clearErrors();
    form.name = segment.name;
    form.criteria = {
        combinator: segment.criteria?.combinator ?? 'and',
        rules: (segment.criteria?.rules ?? []).map((rule) => ({ ...rule })),
    };
    previewResult.value = null;
}

function addRule(): void {
    form.criteria.rules.push({ field: 'name', operator: 'equals', value: '' });
}

function removeRule(index: number): void {
    form.criteria.rules.splice(index, 1);
}

function submit(): void {
    if (isEditing.value) {
        form.put(
            SegmentController.update.url([
                props.campaign.slug,
                editingId.value as number,
            ]),
            { preserveScroll: true, onSuccess: () => resetForm() },
        );

        return;
    }

    form.post(SegmentController.store.url(props.campaign.slug), {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
}

/**
 * Run the live preview for the criteria currently in the builder. The preview
 * prop comes back via a partial reload, leaving the form state untouched.
 */
function runPreview(): void {
    router.post(
        SegmentController.preview.url(props.campaign.slug),
        { criteria: form.criteria },
        {
            only: ['preview'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                previewResult.value = props.preview ?? null;
            },
        },
    );
}

function ruleSummary(segment: Segment): string {
    const count = segment.criteria?.rules?.length ?? 0;

    if (count === 0) {
        return 'Matches all contacts';
    }

    return `${count} rule${count === 1 ? '' : 's'} (${segment.criteria?.combinator ?? 'and'})`;
}
</script>

<template>
    <Head :title="`${campaign.name} segments`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                :title="`${campaign.name} segments`"
                :description="`${segments.total} saved segment(s) in this campaign.`"
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
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Criteria</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="segment in segments.data"
                        :key="segment.id"
                        class="border-b last:border-0"
                        :data-test="`segment-row-${segment.id}`"
                    >
                        <td class="px-4 py-2">{{ segment.name }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ ruleSummary(segment) }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="startEdit(segment)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="
                                        router.delete(
                                            SegmentController.destroy.url([
                                                campaign.slug,
                                                segment.id,
                                            ]),
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    Delete
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="segments.data.length === 0">
                        <td
                            colspan="3"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            No segments yet. Build your first one below.
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>

        <Card class="max-w-2xl space-y-4 p-4" data-test="segment-form">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    :title="isEditing ? 'Edit segment' : 'New segment'"
                    description="Define who this segment targets, then preview the match."
                />
                <Button
                    v-if="isEditing"
                    variant="ghost"
                    size="sm"
                    @click="startCreate"
                >
                    Cancel
                </Button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="segment-name">Name</Label>
                    <Input
                        id="segment-name"
                        v-model="form.name"
                        required
                        autocomplete="off"
                        data-test="segment-name-input"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Label>Match</Label>
                        <select
                            v-model="form.criteria.combinator"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            data-test="segment-combinator"
                        >
                            <option value="and">all rules (AND)</option>
                            <option value="or">any rule (OR)</option>
                        </select>
                    </div>

                    <div
                        v-for="(rule, index) in form.criteria.rules"
                        :key="index"
                        class="flex flex-wrap items-center gap-2"
                        :data-test="`criteria-rule-${index}`"
                    >
                        <select
                            v-model="rule.field"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            data-test="criteria-field"
                        >
                            <option
                                v-for="field in FIELDS"
                                :key="field.value"
                                :value="field.value"
                            >
                                {{ field.label }}
                            </option>
                        </select>

                        <select
                            v-model="rule.operator"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            data-test="criteria-operator"
                        >
                            <option
                                v-for="operator in OPERATORS"
                                :key="operator.value"
                                :value="operator.value"
                            >
                                {{ operator.label }}
                            </option>
                        </select>

                        <Input
                            v-if="operatorRequiresValue(rule.operator)"
                            v-model="rule.value"
                            class="w-48"
                            placeholder="Value"
                            data-test="criteria-value"
                        />

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removeRule(index)"
                        >
                            Remove
                        </Button>

                        <InputError
                            :message="
                                form.errors[`criteria.rules.${index}.value`]
                            "
                        />
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="add-rule"
                        @click="addRule"
                    >
                        Add rule
                    </Button>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        data-test="save-segment"
                    >
                        {{ isEditing ? 'Save changes' : 'Create segment' }}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        data-test="preview-segment"
                        @click="runPreview"
                    >
                        Preview match
                    </Button>
                </div>
            </form>

            <div
                v-if="previewResult"
                class="rounded-md border bg-muted/30 p-3"
                data-test="preview-result"
            >
                <p class="text-sm font-medium">
                    {{ previewResult.count }} contact(s) match.
                </p>
                <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                    <li
                        v-for="contact in previewResult.contacts"
                        :key="contact.id"
                    >
                        {{ contact.name }} — {{ contact.email }}
                    </li>
                </ul>
            </div>
        </Card>
    </div>
</template>
