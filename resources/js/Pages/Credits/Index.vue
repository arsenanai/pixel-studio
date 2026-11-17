<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { Money } from '@/types/models';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    balance: Money;
    held: Money;
    available: Money;
    packs: { dollars: number; amount: Money }[];
    transactions: {
        id: number;
        label: string;
        amount: Money;
        createdAt: string;
    }[];
}>();

const form = useForm({ dollars: 0 });

function buy(dollars: number): void {
    form.dollars = dollars;
    form.post(route('credits.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Credits" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                    Credits
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-600">
                    Credits are US dollars. Each image holds its highest
                    possible price while it runs, then you pay only what it
                    actually cost. Failed images are free.
                </p>
            </header>

            <dl
                class="mt-8 grid grid-cols-1 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0"
            >
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        Available to spend
                    </dt>
                    <dd
                        class="mt-1 text-2xl font-semibold tabular-nums text-gray-900"
                    >
                        {{ available.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        Held for images in progress
                    </dt>
                    <dd class="mt-1 text-lg tabular-nums text-gray-700">
                        {{ held.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">Balance</dt>
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
                    Buy credits
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    This is a demo: no payment is taken and no card is needed.
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
                        Add {{ pack.amount.display }}
                    </button>
                </div>
                <p v-if="form.errors.dollars" class="mt-2 text-sm text-red-600">
                    {{ form.errors.dollars }}
                </p>
            </section>

            <section aria-labelledby="activity-heading" class="mt-10">
                <h2
                    id="activity-heading"
                    class="text-base font-semibold text-gray-900"
                >
                    Activity
                </h2>
                <div
                    class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white"
                >
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Credit purchases and charges, newest first
                        </caption>
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3">When</th>
                                <th scope="col" class="px-4 py-3">What</th>
                                <th scope="col" class="px-4 py-3 text-right">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="transactions.length === 0">
                                <td
                                    colspan="3"
                                    class="px-4 py-8 text-center text-gray-500"
                                >
                                    Nothing yet. Add some credits to start
                                    generating.
                                </td>
                            </tr>
                            <tr
                                v-for="transaction in transactions"
                                :key="transaction.id"
                            >
                                <td class="px-4 py-2.5 text-gray-600">
                                    <time :datetime="transaction.createdAt">{{
                                        formatDateTime(transaction.createdAt)
                                    }}</time>
                                </td>
                                <td class="px-4 py-2.5 text-gray-900">
                                    {{ transaction.label }}
                                </td>
                                <td
                                    class="px-4 py-2.5 text-right tabular-nums text-gray-900"
                                >
                                    {{ transaction.amount.display }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
