<script setup lang="ts">
import { Head, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ApiContactController from '@/actions/App/Http/Controllers/Api/V1/ContactController';
import ApiTokenController from '@/actions/App/Http/Controllers/ApiTokenController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Campaign = {
    id: number;
    name: string;
    slug: string;
    role: string;
};

type Contact = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};

const props = defineProps<{
    campaigns: Campaign[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'API demo', href: ApiTokenController.create.url() },
        ],
    },
});

const token = ref<string | null>(null);
const selectedSlug = ref<string>(props.campaigns[0]?.slug ?? '');

const selectedCampaign = computed<Campaign | null>(
    () => props.campaigns.find((c) => c.slug === selectedSlug.value) ?? null,
);

/** Owner/staffer hold ManageContent; a viewer's POST is a legitimate 403. */
const canWrite = computed<boolean>(() => {
    const role = selectedCampaign.value?.role;

    return role === 'owner' || role === 'staffer';
});

const authHeaders = computed<Record<string, string>>(() => {
    const headers: Record<string, string> = {};

    if (token.value) {
        headers.Authorization = `Bearer ${token.value}`;
    }

    return headers;
});

/**
 * Mint a personal access token for the current web session.
 *
 * This hits the session-authed web route, so it needs no credentials — the
 * already-logged-in user gets a Bearer token returned once as JSON.
 */
const tokenHttp = useHttp();
const tokenError = ref<string | null>(null);

function generateToken(): void {
    tokenError.value = null;

    tokenHttp
        .post(ApiTokenController.store.url(), {
            onSuccess: (response) => {
                token.value = (response as { token: string }).token;
            },
            onHttpException: () => {
                tokenError.value = 'Could not generate a token. Try reloading.';
            },
            onNetworkError: () => {
                tokenError.value = 'Could not reach the server.';
            },
        })
        .catch(() => {});
}

/**
 * GET the selected campaign's contacts from the v1 API, authenticating with the
 * issued Bearer token rather than the web session.
 */
const contactsHttp = useHttp();
const contacts = ref<Contact[]>([]);
const contactsLoaded = ref<boolean>(false);
const contactsError = ref<string | null>(null);

function fetchContacts(): void {
    if (!token.value || selectedSlug.value === '') {
        return;
    }

    contactsError.value = null;

    contactsHttp
        .get(ApiContactController.index.url(selectedSlug.value), {
            headers: authHeaders.value,
            onSuccess: (response) => {
                contacts.value = (response as { data: Contact[] }).data;
                contactsLoaded.value = true;
            },
            onHttpException: (response) => {
                contactsError.value = `The API rejected the request (HTTP ${response.status}).`;
            },
            onNetworkError: () => {
                contactsError.value = 'The request could not reach the API.';
            },
        })
        .catch(() => {});
}

/**
 * POST a new contact to the selected campaign over the v1 API.
 *
 * Writing needs ManageContent (owner/staffer); a viewer receives a 403, which is
 * surfaced as an error state rather than treated as a failure.
 */
const createHttp = useHttp({
    name: '',
    email: '',
    phone: '',
});
const createError = ref<string | null>(null);

function createContact(): void {
    if (!token.value || selectedSlug.value === '') {
        return;
    }

    createError.value = null;

    createHttp
        .post(ApiContactController.store.url(selectedSlug.value), {
            headers: authHeaders.value,
            onSuccess: (response) => {
                contacts.value = [
                    (response as { data: Contact }).data,
                    ...contacts.value,
                ];
                contactsLoaded.value = true;
                createHttp.name = '';
                createHttp.email = '';
                createHttp.phone = '';
            },
            onHttpException: (response) => {
                createError.value =
                    response.status === 403
                        ? 'This role is read-only — adding a contact needs owner or staffer access (HTTP 403).'
                        : `The API rejected the request (HTTP ${response.status}).`;
            },
            onNetworkError: () => {
                createError.value = 'The request could not reach the API.';
            },
        })
        .catch(() => {});
}
</script>

<template>
    <Head title="API demo" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            title="API console"
            description="Mint a token for your session, then drive the v1 REST API over HTTP from the browser."
        />

        <Card class="max-w-2xl space-y-4 p-4">
            <Heading
                variant="small"
                title="1. Generate a token"
                description="Issues a personal access token for your current login — no second sign-in. v1 tokens carry full access for your account."
            />

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    :disabled="tokenHttp.processing"
                    data-test="generate-token-button"
                    @click="generateToken"
                >
                    {{ token ? 'Regenerate token' : 'Generate token' }}
                </Button>

                <span
                    v-if="token"
                    class="text-sm text-muted-foreground"
                    data-test="token-status"
                >
                    Token issued — used as the Bearer credential below.
                </span>
            </div>

            <p
                v-if="token"
                class="rounded bg-muted px-3 py-2 font-mono text-xs break-all"
                data-test="api-token"
            >
                {{ token }}
            </p>

            <InputError :message="tokenError ?? ''" />
        </Card>

        <Card class="max-w-2xl space-y-4 p-4">
            <Heading
                variant="small"
                title="2. Read contacts over the API"
                description="GET /api/v1/campaigns/{slug}/contacts with the Bearer token."
            />

            <div
                v-if="campaigns.length === 0"
                class="text-sm text-muted-foreground"
            >
                You don't belong to any campaign yet — create one first to try
                the API.
            </div>

            <template v-else>
                <div class="grid max-w-sm gap-2">
                    <Label for="campaign-select">Campaign</Label>
                    <select
                        id="campaign-select"
                        v-model="selectedSlug"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        data-test="campaign-select"
                    >
                        <option
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            :value="campaign.slug"
                        >
                            {{ campaign.name }} ({{ campaign.role }})
                        </option>
                    </select>
                    <p
                        v-if="selectedCampaign && !canWrite"
                        class="text-xs text-muted-foreground"
                    >
                        You're a viewer here — reads work, but adding a contact
                        will return 403.
                    </p>
                </div>

                <Button
                    type="button"
                    variant="secondary"
                    :disabled="!token || contactsHttp.processing"
                    data-test="fetch-contacts-button"
                    @click="fetchContacts"
                >
                    {{
                        contactsHttp.processing ? 'Fetching…' : 'Fetch contacts'
                    }}
                </Button>

                <p v-if="!token" class="text-xs text-muted-foreground">
                    Generate a token first to enable the API calls.
                </p>

                <InputError :message="contactsError ?? ''" />

                <Card v-if="contactsLoaded" class="overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-2 font-medium">Name</th>
                                <th class="px-4 py-2 font-medium">Email</th>
                                <th class="px-4 py-2 font-medium">Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="contact in contacts"
                                :key="contact.id"
                                class="border-b last:border-0"
                                :data-test="`api-contact-row-${contact.id}`"
                            >
                                <td class="px-4 py-2">{{ contact.name }}</td>
                                <td class="px-4 py-2">{{ contact.email }}</td>
                                <td class="px-4 py-2 text-muted-foreground">
                                    {{ contact.phone ?? '—' }}
                                </td>
                            </tr>

                            <tr v-if="contacts.length === 0">
                                <td
                                    colspan="3"
                                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                                >
                                    This campaign has no contacts yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </template>
        </Card>

        <Card v-if="campaigns.length > 0" class="max-w-md space-y-4 p-4">
            <Heading
                variant="small"
                title="3. Write a contact over the API"
                description="POST /api/v1/campaigns/{slug}/contacts — needs owner or staffer access."
            />

            <form class="space-y-4" @submit.prevent="createContact">
                <div class="grid gap-2">
                    <Label for="api-create-name">Name</Label>
                    <Input
                        id="api-create-name"
                        v-model="createHttp.name"
                        autocomplete="off"
                        data-test="api-contact-name-input"
                    />
                    <InputError :message="createHttp.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="api-create-email">Email</Label>
                    <Input
                        id="api-create-email"
                        v-model="createHttp.email"
                        type="email"
                        autocomplete="off"
                        data-test="api-contact-email-input"
                    />
                    <InputError :message="createHttp.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="api-create-phone">Phone</Label>
                    <Input
                        id="api-create-phone"
                        v-model="createHttp.phone"
                        autocomplete="off"
                        data-test="api-contact-phone-input"
                    />
                    <InputError :message="createHttp.errors.phone" />
                </div>

                <Button
                    type="submit"
                    :disabled="!token || createHttp.processing"
                    data-test="api-create-contact-button"
                >
                    {{
                        createHttp.processing
                            ? 'Adding…'
                            : 'Add contact via API'
                    }}
                </Button>

                <InputError :message="createError ?? ''" />
            </form>
        </Card>
    </div>
</template>
