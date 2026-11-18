<script setup lang="ts">
import { t, tc } from '@/lib/i18n';
import type { Reconciliation } from '@/types/models';
import { Deferred } from '@inertiajs/vue3';

defineProps<{
    reconciliation?: Reconciliation;
    source: { environment: string; keyHint: string | null };
}>();
</script>

<template>
    <section aria-labelledby="reconciliation-heading" class="mt-6">
        <h2 id="reconciliation-heading" class="sr-only">
            {{ t('Check against Pixelforge') }}
        </h2>
        <div aria-live="polite">
            <Deferred data="reconciliation">
                <template #fallback>
                    <p
                        class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600"
                    >
                        {{
                            t(
                                "Checking provider costs against Pixelforge's usage report...",
                            )
                        }}
                    </p>
                </template>

                <template v-if="reconciliation">
                    <p
                        v-if="!reconciliation.available"
                        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        {{
                            t(
                                "Couldn't reach Pixelforge to check these numbers. The report itself is complete; try again shortly.",
                            )
                        }}
                    </p>
                    <p
                        v-else-if="reconciliation.mismatches === 0"
                        class="flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                    >
                        <svg
                            class="h-4 w-4 shrink-0"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <span>
                            {{
                                t(
                                    'Provider costs match Pixelforge exactly for all :days days.',
                                    {
                                        days: reconciliation.checkedDays,
                                    },
                                )
                            }}
                            <code class="font-mono text-xs">GET /v1/usage</code>
                        </span>
                    </p>
                    <div
                        v-else
                        class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                    >
                        <p class="font-medium">
                            {{
                                tc(
                                    ":count day differs from Pixelforge's usage report.|:count days differ from Pixelforge's usage report.",
                                    reconciliation.mismatches,
                                )
                            }}
                        </p>
                        <p class="mt-1 text-red-800">
                            {{
                                t(
                                    'Today can lag while generations are still finishing. Older days should never differ.',
                                )
                            }}
                        </p>
                        <div
                            class="mt-3 max-h-72 overflow-auto rounded border border-red-200 bg-white/60"
                        >
                            <table class="w-full min-w-[32rem] text-left">
                                <caption class="sr-only">
                                    {{
                                        t('Days that differ from Pixelforge')
                                    }}
                                </caption>
                                <thead
                                    class="sticky top-0 bg-red-50 text-xs uppercase tracking-wide text-red-800"
                                >
                                    <tr>
                                        <th scope="col" class="px-3 py-1.5">
                                            {{ t('Day') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-1.5 text-right"
                                        >
                                            {{ t('Images (ours / theirs)') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-1.5 text-right"
                                        >
                                            {{ t('Ours') }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-1.5 text-right"
                                        >
                                            Pixelforge
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-3 py-1.5 text-right"
                                        >
                                            {{ t('Difference') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="day in reconciliation.days"
                                        :key="day.date"
                                    >
                                        <th
                                            scope="row"
                                            class="px-3 py-1.5 font-normal"
                                        >
                                            {{ day.date }}
                                        </th>
                                        <td
                                            class="px-3 py-1.5 text-right tabular-nums"
                                        >
                                            {{ day.ourGenerations }}
                                            /
                                            {{ day.theirGenerations }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right tabular-nums"
                                        >
                                            {{ day.ourCost.display }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right tabular-nums"
                                        >
                                            {{ day.theirCost.display }}
                                        </td>
                                        <td
                                            class="px-3 py-1.5 text-right font-medium tabular-nums"
                                        >
                                            {{ day.difference.display }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </Deferred>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            {{
                t(
                    'Checked against the Pixelforge API key :key (:environment). Usage is reported per key.',
                    {
                        key: source.keyHint ?? '—',
                        environment: source.environment,
                    },
                )
            }}
        </p>
    </section>
</template>
