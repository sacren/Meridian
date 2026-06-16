<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContactController from '@/actions/App/Http/Controllers/ContactController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, index as campaignsIndex } from '@/routes/campaigns';

type Campaign = {
    id: number;
    name: string;
    slug: string;
};

type Contact = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
};

const props = defineProps<{
    campaign: Campaign;
    contacts: Paginator<Contact>;
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Campaigns', href: campaignsIndex() }],
    },
});

const search = ref(props.filters.search);
const editing = ref<Contact | null>(null);

/**
 * Reload the index preserving the current sort, applying the typed search term.
 */
function applySearch(): void {
    router.get(
        ContactController.index.url(props.campaign.slug, {
            query: {
                search: search.value || undefined,
                sort: props.filters.sort,
                direction: props.filters.direction,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/**
 * The href that sorts by the given column, toggling direction when it is already active.
 */
function sortHref(column: string): string {
    const direction =
        props.filters.sort === column && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';

    return ContactController.index.url(props.campaign.slug, {
        query: {
            search: props.filters.search || undefined,
            sort: column,
            direction,
        },
    });
}

/**
 * The arrow indicator for a sortable column header.
 */
function sortIndicator(column: string): string {
    if (props.filters.sort !== column) {
        return '';
    }

    return props.filters.direction === 'asc' ? '↑' : '↓';
}
</script>

<template>
    <Head :title="`${campaign.name} contacts`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                :title="`${campaign.name} contacts`"
                :description="`${contacts.total} contact(s) in this campaign.`"
            />
            <Link
                :href="dashboard(campaign.slug)"
                class="text-sm text-muted-foreground hover:text-foreground"
            >
                Back to dashboard
            </Link>
        </div>

        <form class="flex max-w-sm gap-2" @submit.prevent="applySearch">
            <Input
                v-model="search"
                type="search"
                placeholder="Search name or email"
                data-test="contact-search-input"
            />
            <Button type="submit" variant="secondary">Search</Button>
        </form>

        <Card class="overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-2 font-medium">
                            <Link
                                :href="sortHref('name')"
                                class="hover:underline"
                            >
                                Name {{ sortIndicator('name') }}
                            </Link>
                        </th>
                        <th class="px-4 py-2 font-medium">
                            <Link
                                :href="sortHref('email')"
                                class="hover:underline"
                            >
                                Email {{ sortIndicator('email') }}
                            </Link>
                        </th>
                        <th class="px-4 py-2 font-medium">Phone</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="contact in contacts.data"
                        :key="contact.id"
                        class="border-b last:border-0"
                        :data-test="`contact-row-${contact.id}`"
                    >
                        <td class="px-4 py-2">{{ contact.name }}</td>
                        <td class="px-4 py-2">{{ contact.email }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ contact.phone ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="editing = contact"
                                >
                                    Edit
                                </Button>
                                <Form
                                    v-bind="
                                        ContactController.destroy.form([
                                            campaign.slug,
                                            contact.id,
                                        ])
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        size="sm"
                                    >
                                        Delete
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="contacts.data.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            No contacts match your search.
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>

        <div
            v-if="contacts.links.length > 3"
            class="flex flex-wrap items-center gap-1"
        >
            <template v-for="(link, i) in contacts.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded px-3 py-1 text-sm hover:bg-muted"
                    :class="{ 'bg-muted font-medium': link.active }"
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="px-3 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>

        <Card class="max-w-md p-4">
            <Heading
                variant="small"
                title="New contact"
                description="Add a contact to this campaign."
            />

            <Form
                v-bind="ContactController.store.form(campaign.slug)"
                reset-on-success
                class="mt-4 space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="create-name">Name</Label>
                    <Input
                        id="create-name"
                        name="name"
                        required
                        autocomplete="off"
                        data-test="contact-name-input"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-email">Email</Label>
                    <Input
                        id="create-email"
                        name="email"
                        type="email"
                        required
                        autocomplete="off"
                        data-test="contact-email-input"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="create-phone">Phone</Label>
                    <Input
                        id="create-phone"
                        name="phone"
                        autocomplete="off"
                        data-test="contact-phone-input"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="create-contact-button"
                >
                    Add contact
                </Button>
            </Form>
        </Card>

        <Dialog
            :open="editing !== null"
            @update:open="
                (open) => {
                    if (!open) editing = null;
                }
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit contact</DialogTitle>
                </DialogHeader>

                <Form
                    v-if="editing"
                    v-bind="
                        ContactController.update.form([
                            campaign.slug,
                            editing.id,
                        ])
                    "
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                    @success="editing = null"
                >
                    <div class="grid gap-2">
                        <Label for="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            name="name"
                            :default-value="editing.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-email">Email</Label>
                        <Input
                            id="edit-email"
                            name="email"
                            type="email"
                            :default-value="editing.email"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-phone">Phone</Label>
                        <Input
                            id="edit-phone"
                            name="phone"
                            :default-value="editing.phone ?? ''"
                        />
                        <InputError :message="errors.phone" />
                    </div>

                    <Button type="submit" :disabled="processing">
                        Save changes
                    </Button>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
