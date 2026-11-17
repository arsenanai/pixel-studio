<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import SpendTable, { type SpendRow } from '@/Components/SpendTable.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { Reconciliation, ReportUser, SpendTotals } from '@/types/models';
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    range: { from: string; to: string };
    source: { environment: string; keyHint: string | null };
    total: SpendTotals;
    days: { date: string; totals: SpendTotals }[];
    users: { user: ReportUser; totals: SpendTotals }[];
    dayUsers: { date: string; user: ReportUser; totals: SpendTotals }[];
    reconciliation?: Reconciliation;
}>();

const page = usePage();
const filters = reactive({ from: props.range.from, to: props.range.to });

function apply(): void {
    router.get(route('admin.report'), filters, { preserveScroll: true });
}

const dayRows = computed<SpendRow[]>(() =>
    props.days.map((day) => ({
        key: day.date,
        label: day.date,
        totals: day.totals,
    })),
);

const userRows = computed<SpendRow[]>(() =>
    props.users.map((row) => ({
        key: String(row.user.id),
        label: row.user.name,
        detail: row.user.email,
        totals: row.totals,
    })),
);

const dayUserRows = computed<SpendRow[]>(() =>
    props.dayUsers.map((row) => ({
        key: `${row.date}-${row.user.id}`,
        label: row.date,
        detail: row.user.name,
        totals: row.totals,
    })),
);

const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);
</script>

<template>
    <Head title="Spend report" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header>
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                    Spend report
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-600">
                    What users were charged, what Pixelforge charged us, and the
                    difference. Days are UTC and count a generation on the day
                    it finished, which is the day Pixelforge bills it.
                </p>
            </header>

            <form
                class="mt-6 flex flex-wrap items-end gap-3"
                @submit.prevent="apply"
            >
                <div>
                    <label
                        for="from"
                        class="block text-xs font-medium text-gray-700"
                        >From</label
                    >
                    <input
                        id="from"
                        v-model="filters.from"
                        type="date"
                        class="mt-1 block min-h-11 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :aria-invalid="errors.from ? 'true' : undefined"
                        aria-describedby="range-error"
                    />
                </div>
                <div>
                    <label
                        for="to"
                        class="block text-xs font-medium text-gray-700"
                        >To</label
                    >
                    <input
                        id="to"
                        v-model="filters.to"
                        type="date"
                        class="mt-1 block min-h-11 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Show
                </button>
                <InputError
                    id="range-error"
                    class="w-full"
                    :message="errors.from ?? errors.to"
                />
            </form>

            <dl
                class="mt-8 grid grid-cols-2 divide-gray-200 rounded-lg border border-gray-200 bg-white sm:grid-cols-4 sm:divide-x"
            >
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        Charged to users
                    </dt>
                    <dd
                        class="mt-1 text-lg font-semibold tabular-nums text-gray-900"
                    >
                        {{ total.charged.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">
                        Pixelforge cost
                    </dt>
                    <dd
                        class="mt-1 text-lg font-semibold tabular-nums text-gray-900"
                    >
                        {{ total.providerCost.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">Profit</dt>
                    <dd
                        class="mt-1 text-lg font-semibold tabular-nums text-gray-900"
                    >
                        {{ total.profit.display }}
                    </dd>
                </div>
                <div class="p-4">
                    <dt class="text-xs font-medium text-gray-500">Margin</dt>
                    <dd
                        class="mt-1 text-lg font-semibold tabular-nums text-gray-900"
                    >
                        {{ total.margin ?? '—' }}
                    </dd>
                    <dd class="text-xs tabular-nums text-gray-500">
                        {{ total.succeeded }} images, {{ total.failed }} failed
                        (free)
                    </dd>
                </div>
            </dl>

            <section aria-labelledby="reconciliation-heading" class="mt-6">
                <h2 id="reconciliation-heading" class="sr-only">
                    Check against Pixelforge
                </h2>
                <div aria-live="polite">
                    <Deferred data="reconciliation">
                        <template #fallback>
                            <p
                                class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600"
                            >
                                Checking provider costs against Pixelforge's
                                usage report…
                            </p>
                        </template>

                        <template v-if="reconciliation">
                            <p
                                v-if="!reconciliation.available"
                                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                            >
                                Couldn't reach Pixelforge to check these
                                numbers. The report itself is complete; try
                                again shortly.
                            </p>
                            <p
                                v-else-if="reconciliation.mismatches === 0"
                                class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                            >
                                Provider costs match Pixelforge's
                                <code class="font-mono text-xs"
                                    >GET /v1/usage</code
                                >
                                exactly for all
                                {{ reconciliation.checkedDays }} days.
                            </p>
                            <div
                                v-else
                                class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                            >
                                <p class="font-medium">
                                    {{ reconciliation.mismatches }}
                                    {{
                                        reconciliation.mismatches === 1
                                            ? 'day differs'
                                            : 'days differ'
                                    }}
                                    from Pixelforge's usage report.
                                </p>
                                <p class="mt-1 text-red-800">
                                    Today can lag while generations are still
                                    finishing. Older days should never differ.
                                </p>
                                <table class="mt-3 w-full text-left">
                                    <caption class="sr-only">
                                        Days that differ from Pixelforge
                                    </caption>
                                    <thead
                                        class="text-xs uppercase tracking-wide text-red-800"
                                    >
                                        <tr>
                                            <th scope="col" class="py-1">
                                                Day
                                            </th>
                                            <th
                                                scope="col"
                                                class="py-1 text-right"
                                            >
                                                Images (ours / theirs)
                                            </th>
                                            <th
                                                scope="col"
                                                class="py-1 text-right"
                                            >
                                                Ours
                                            </th>
                                            <th
                                                scope="col"
                                                class="py-1 text-right"
                                            >
                                                Pixelforge
                                            </th>
                                            <th
                                                scope="col"
                                                class="py-1 text-right"
                                            >
                                                Difference
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
                                                class="py-1 font-normal"
                                            >
                                                {{ day.date }}
                                            </th>
                                            <td
                                                class="py-1 text-right tabular-nums"
                                            >
                                                {{ day.ourGenerations }} /
                                                {{ day.theirGenerations }}
                                            </td>
                                            <td
                                                class="py-1 text-right tabular-nums"
                                            >
                                                {{ day.ourCost.display }}
                                            </td>
                                            <td
                                                class="py-1 text-right tabular-nums"
                                            >
                                                {{ day.theirCost.display }}
                                            </td>
                                            <td
                                                class="py-1 text-right font-medium tabular-nums"
                                            >
                                                {{ day.difference.display }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </Deferred>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Checked against the Pixelforge API key
                    <span v-if="source.keyHint" class="font-mono">{{
                        source.keyHint
                    }}</span>
                    ({{ source.environment }}). Usage is reported per key.
                </p>
            </section>

            <section aria-labelledby="by-day" class="mt-10">
                <h2 id="by-day" class="text-base font-semibold text-gray-900">
                    By day
                </h2>
                <div class="mt-3">
                    <SpendTable
                        caption="Spend per UTC day"
                        heading="Day (UTC)"
                        :rows="dayRows"
                        :total="total"
                    />
                </div>
            </section>

            <section aria-labelledby="by-user" class="mt-10">
                <h2 id="by-user" class="text-base font-semibold text-gray-900">
                    By user
                </h2>
                <div class="mt-3">
                    <SpendTable
                        caption="Spend per user"
                        heading="User"
                        :rows="userRows"
                        :total="total"
                    />
                </div>
            </section>

            <section aria-labelledby="by-day-user" class="mt-10">
                <h2
                    id="by-day-user"
                    class="text-base font-semibold text-gray-900"
                >
                    By day and user
                </h2>
                <div class="mt-3">
                    <SpendTable
                        caption="Spend per user per UTC day"
                        heading="Day · user"
                        :rows="dayUserRows"
                        :total="total"
                    />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
