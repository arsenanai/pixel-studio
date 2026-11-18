<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { Money, Page } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type TransactionType = 'purchase' | 'charge';

const props = defineProps<{
    balance: Money;
    held: Money;
    available: Money;
    packs: { dollars: number; amount: Money }[];
    filters: { type: TransactionType | null };
    transactions: Page<{
        id: number;
        type: TransactionType;
        label: string;
        amount: Money;
        balanceAfter: Money;
        prompt: string | null;
        createdAt: string;
    }>;
}>();

const form = useForm({ dollars: 0 });
const loading = ref(false);

function buy(dollars: number): void {
    form.dollars = dollars;
    form.post(route('credits.store'), { preserveScroll: true });
}

function filter(type: TransactionType | null): void {
    router.get(route('credits'), type === null ? {} : { type }, {
        only: ['transactions', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

const tabs = computed(() => [
    { value: null, label: t('All') },
    { value: 'purchase' as const, label: t('Purchases') },
    { value: 'charge' as const, label: t('Charges') },
]);

const emptyMessage = computed(() =>
    props.filters.type === null
        ? t('Nothing yet. Add some credits to start generating.')
        : props.filters.type === 'purchase'
          ? t('No purchases yet.')
          : t('No charges yet. You only pay for images that succeed.'),
);
</script>

<template>
    <Head :title="t('Credits')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                    {{ t('Credits') }}
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-600">
                    {{
                        t(
                            'Credits are US dollars. Each image holds its highest possible price while it runs, then you pay only what it actually cost. Failed images are free.',
                        )
                    }}
                </p>
            </header>

            <dl
                class="mt-8 grid grid-cols-1 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0"
            >
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        {{ t('Available to spend') }}
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ available.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        {{ t('Held for images in progress') }}
                    </dt>
                    <dd class="mt-1 text-lg tabular-nums text-gray-700">
                        {{ held.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        {{ t('Balance') }}
                    </dt>
                    <dd class="mt-1 text-lg tabular-nums text-gray-700">
                        {{ balance.display }}
                    </dd>
                </div>
            </dl>

            <section aria-labelledby="buy-heading" class="mt-10">
                <h2
                    id="buy-heading"
                    class="text-base font-semibold text-gray-900"
                >
                    {{ t('Buy credits') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{
                        t(
                            'This is a demo: no payment is taken and no card is needed.',
                        )
                    }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        v-for="pack in packs"
                        :key="pack.dollars"
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-5 text-sm font-semibold tabular-nums text-gray-900 shadow-sm hover:border-indigo-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="buy(pack.dollars)"
                    >
                        {{ t('Add :amount', { amount: pack.amount.display }) }}
                    </button>
                </div>
                <p v-if="form.errors.dollars" class="mt-2 text-sm text-red-600">
                    {{ form.errors.dollars }}
                </p>
            </section>

            <section aria-labelledby="activity-heading" class="mt-10">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        id="activity-heading"
                        class="text-base font-semibold text-gray-900"
                    >
                        {{ t('Activity') }}
                    </h2>
                    <div
                        class="flex gap-1"
                        role="group"
                        :aria-label="t('Filter by type')"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.label"
                            type="button"
                            class="min-h-10 rounded-md px-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            :class="
                                filters.type === tab.value
                                    ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200'
                                    : 'text-gray-600 hover:bg-white/60 hover:text-gray-900'
                            "
                            :aria-pressed="filters.type === tab.value"
                            @click="filter(tab.value)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
                <div
                    class="mt-3 overflow-x-auto rounded-lg border border-gray-200 bg-white transition-opacity"
                    :class="loading ? 'opacity-60' : ''"
                    :aria-busy="loading"
                >
                    <table class="w-full min-w-[36rem] text-sm">
                        <caption class="sr-only">
                            {{
                                t('Credit purchases and charges, newest first')
                            }}
                        </caption>
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            <tr>
                                <th scope="col" class="w-40 px-4 py-3">
                                    {{ t('When') }}
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    {{ t('What') }}
                                </th>
                                <th
                                    scope="col"
                                    class="w-36 px-4 py-3 text-right"
                                >
                                    {{ t('Amount') }}
                                </th>
                                <th
                                    scope="col"
                                    class="w-36 px-4 py-3 text-right"
                                >
                                    {{ t('Balance after') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="transactions.data.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-gray-500"
                                >
                                    {{ emptyMessage }}
                                </td>
                            </tr>
                            <tr
                                v-for="transaction in transactions.data"
                                :key="transaction.id"
                            >
                                <td class="px-4 py-2.5 text-gray-600">
                                    <time :datetime="transaction.createdAt">{{
                                        formatDateTime(transaction.createdAt)
                                    }}</time>
                                </td>
                                <td class="px-4 py-2.5 text-gray-900">
                                    {{ transaction.label }}
                                    <span
                                        v-if="transaction.prompt"
                                        class="block max-w-md truncate text-xs text-gray-500"
                                        :title="transaction.prompt"
                                    >
                                        {{ transaction.prompt }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums"
                                    :class="
                                        transaction.type === 'purchase'
                                            ? 'text-emerald-700'
                                            : 'text-gray-900'
                                    "
                                >
                                    {{
                                        transaction.type === 'purchase'
                                            ? '+'
                                            : ''
                                    }}{{ transaction.amount.display }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums text-gray-600"
                                >
                                    {{ transaction.balanceAfter.display }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination
                    :links="transactions.links"
                    :from="transactions.from"
                    :to="transactions.to"
                    :total="transactions.total"
                    :only="['transactions', 'filters']"
                />
            </section>
        </div>
    </AuthenticatedLayout>
</template>
