<script setup lang="ts">
import { t, tc } from '@/lib/i18n';
import type { SpendTotals } from '@/types/models';
import { Link } from '@inertiajs/vue3';

export interface SpendRow {
    key: string;
    label: string;
    detail?: string;
    href?: string;
    totals: SpendTotals;
}

defineProps<{
    caption: string;
    heading: string;
    rows: SpendRow[];
    total?: SpendTotals;
    empty?: string;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full min-w-[40rem] text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead
                class="border-b border-gray-200 bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500"
            >
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">
                        {{ heading }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        {{ t('Images') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        {{ t('Charged') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        {{ t('Pixelforge cost') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-right">
                        {{ t('Profit') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-if="rows.length === 0">
                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                        {{ empty ?? t('Nothing finished in this range.') }}
                    </td>
                </tr>
                <tr v-for="row in rows" :key="row.key">
                    <th
                        scope="row"
                        class="px-4 py-2.5 text-left font-normal text-gray-900"
                    >
                        <Link
                            v-if="row.href"
                            :href="row.href"
                            preserve-scroll
                            class="font-medium text-indigo-700 underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >{{ row.label }}</Link
                        >
                        <template v-else>{{ row.label }}</template>
                        <span
                            v-if="row.detail"
                            class="block text-xs text-gray-500"
                            >{{ row.detail }}</span
                        >
                    </th>
                    <td
                        class="px-4 py-2.5 text-right tabular-nums text-gray-700"
                    >
                        {{ row.totals.succeeded }}
                        <span
                            v-if="row.totals.failed"
                            class="block text-xs text-gray-500"
                        >
                            {{ tc('+:count failed, free', row.totals.failed) }}
                        </span>
                    </td>
                    <td
                        class="px-4 py-2.5 text-right tabular-nums text-gray-900"
                    >
                        {{ row.totals.charged.display }}
                    </td>
                    <td
                        class="px-4 py-2.5 text-right tabular-nums text-gray-700"
                    >
                        {{ row.totals.providerCost.display }}
                    </td>
                    <td
                        class="px-4 py-2.5 text-right tabular-nums text-gray-900"
                    >
                        {{ row.totals.profit.display }}
                    </td>
                </tr>
            </tbody>
            <tfoot
                v-if="total && rows.length > 1"
                class="border-t border-gray-200 bg-gray-50 font-medium text-gray-900"
            >
                <tr>
                    <th scope="row" class="px-4 py-2.5 text-left">
                        {{ t('Total') }}
                    </th>
                    <td class="px-4 py-2.5 text-right tabular-nums">
                        {{ total.succeeded }}
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums">
                        {{ total.charged.display }}
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums">
                        {{ total.providerCost.display }}
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums">
                        {{ total.profit.display }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
