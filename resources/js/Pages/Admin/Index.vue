<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import SpendChart from '@/Components/SpendChart.vue';
import SpendTable, { type SpendRow } from '@/Components/SpendTable.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { intlLocale, t, tc } from '@/lib/i18n';
import type {
    Page,
    Reconciliation,
    ReportUser,
    SpendTotals,
} from '@/types/models';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

type Sort = 'charged' | 'profit' | 'images';

const props = defineProps<{
    range: { from: string; to: string };
    filters: { q: string; sort: Sort; user: ReportUser | null };
    source: { environment: string; keyHint: string | null };
    total: SpendTotals;
    days: { date: string; totals: SpendTotals }[];
    users: Page<{ user: ReportUser; totals: SpendTotals }> | null;
    reconciliation?: Reconciliation;
}>();

const page = usePage();
const loading = ref(false);
const range = reactive({ from: props.range.from, to: props.range.to });
const search = ref(props.filters.q);
const sort = ref<Sort>(props.filters.sort);

watch(
    () => props.range,
    (value) => Object.assign(range, value),
);

const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

function utcDay(offset = 0): string {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() - offset);

    return date.toISOString().slice(0, 10);
}

const presets = computed(() =>
    [7, 30, 90].map((days) => ({
        days,
        from: utcDay(days - 1),
        to: utcDay(),
        active:
            props.range.to === utcDay() &&
            props.range.from === utcDay(days - 1),
    })),
);

function params(overrides: Record<string, string | number | null> = {}) {
    const all: Record<string, string | number | null> = {
        from: range.from,
        to: range.to,
        q: search.value || null,
        sort: sort.value === 'charged' ? null : sort.value,
        user: props.filters.user?.id ?? null,
        ...overrides,
    };

    return Object.fromEntries(
        Object.entries(all).filter(
            ([, value]) => value !== null && value !== '',
        ),
    );
}

function visit(
    overrides: Record<string, string | number | null> = {},
    only?: string[],
): void {
    router.get(route('admin.report'), params(overrides), {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({}, ['users', 'filters']), 300);
});

watch(sort, () => visit({}, ['users', 'filters']));

onBeforeUnmount(() => clearTimeout(searchTimer));

const exportUrl = computed(() =>
    route('admin.export', params({ q: null, sort: null })),
);

const longDate = computed(
    () =>
        new Intl.DateTimeFormat(intlLocale(), {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            timeZone: 'UTC',
        }),
);

const dayRows = computed<SpendRow[]>(() =>
    [...props.days].reverse().map((day) => ({
        key: day.date,
        label: longDate.value.format(new Date(day.date)),
        totals: day.totals,
    })),
);

const userRows = computed<SpendRow[]>(() =>
    (props.users?.data ?? []).map((row) => ({
        key: String(row.user.id),
        label: row.user.name,
        detail: row.user.email,
        href: route(
            'admin.report',
            params({ user: row.user.id, q: null, sort: null, page: null }),
        ),
        totals: row.totals,
    })),
);

const activeDays = computed(
    () =>
        props.days.filter((day) => day.totals.succeeded + day.totals.failed > 0)
            .length,
);
</script>

<template>
    <Head :title="t('Spend report')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-gray-900"
                    >
                        {{ t('Spend report') }}
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-600">
                        {{
                            t(
                                'What users were charged, what Pixelforge charged us, and the difference. Days are UTC and count a generation on the day it finished, which is the day Pixelforge bills it.',
                            )
                        }}
                    </p>
                </div>
                <a
                    :href="exportUrl"
                    class="inline-flex min-h-11 items-center gap-2 rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"
                        />
                        <path
                            d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"
                        />
                    </svg>
                    {{ t('Download CSV') }}
                </a>
            </header>

            <form
                class="mt-6 flex flex-wrap items-end gap-3"
                @submit.prevent="visit({ page: null })"
            >
                <div
                    class="inline-flex rounded-md border border-gray-300 bg-white p-0.5"
                    role="group"
                    :aria-label="t('Quick ranges')"
                >
                    <button
                        v-for="preset in presets"
                        :key="preset.days"
                        type="button"
                        class="min-h-10 rounded px-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="
                            preset.active
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-700 hover:bg-gray-100'
                        "
                        :aria-pressed="preset.active"
                        @click="
                            visit({
                                from: preset.from,
                                to: preset.to,
                                page: null,
                            })
                        "
                    >
                        {{ t(':days days', { days: preset.days }) }}
                    </button>
                </div>
                <div>
                    <label
                        for="from"
                        class="block text-xs font-medium text-gray-700"
                        >{{ t('From') }}</label
                    >
                    <input
                        id="from"
                        v-model="range.from"
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
                        >{{ t('To') }}</label
                    >
                    <input
                        id="to"
                        v-model="range.to"
                        type="date"
                        class="mt-1 block min-h-11 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="submit"
                    class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {{ t('Show') }}
                </button>
                <InputError
                    id="range-error"
                    class="w-full"
                    :message="
                        errors.from ?? errors.to ?? errors.user ?? errors.sort
                    "
                />
            </form>

            <div
                v-if="filters.user"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950"
            >
                <p>
                    {{ t('Showing only') }}
                    <span class="font-semibold">{{ filters.user.name }}</span>
                    <span class="ml-1 text-indigo-800"
                        >· {{ filters.user.email }}</span
                    >
                </p>
                <Link
                    :href="
                        route(
                            'admin.report',
                            params({ user: null, page: null }),
                        )
                    "
                    preserve-scroll
                    class="font-medium text-indigo-700 underline underline-offset-2 hover:text-indigo-900"
                >
                    {{ t('Show everyone') }}
                </Link>
            </div>

            <div
                class="transition-opacity"
                :class="loading ? 'opacity-60' : ''"
                :aria-busy="loading"
            >
                <dl
                    class="mt-6 grid grid-cols-2 divide-gray-200 rounded-lg border border-gray-200 bg-white sm:grid-cols-4 sm:divide-x"
                >
                    <div class="p-4">
                        <dt class="text-xs font-medium text-gray-500">
                            {{ t('Charged to users') }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900">
                            {{ total.charged.cents }}
                        </dd>
                        <dd
                            class="text-xs tabular-nums text-gray-500"
                            :title="t('Exact amount')"
                        >
                            {{ total.charged.display }}
                        </dd>
                    </div>
                    <div class="p-4">
                        <dt class="text-xs font-medium text-gray-500">
                            {{ t('Pixelforge cost') }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900">
                            {{ total.providerCost.cents }}
                        </dd>
                        <dd
                            class="text-xs tabular-nums text-gray-500"
                            :title="t('Exact amount')"
                        >
                            {{ total.providerCost.display }}
                        </dd>
                    </div>
                    <div class="p-4">
                        <dt class="text-xs font-medium text-gray-500">
                            {{ t('Profit') }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900">
                            {{ total.profit.cents }}
                        </dd>
                        <dd
                            class="text-xs tabular-nums text-gray-500"
                            :title="t('Exact amount')"
                        >
                            {{ total.profit.display }}
                        </dd>
                    </div>
                    <div class="p-4">
                        <dt class="text-xs font-medium text-gray-500">
                            {{ t('Margin') }}
                        </dt>
                        <dd class="mt-1 text-xl font-semibold text-gray-900">
                            {{ total.margin ?? '—' }}
                        </dd>
                        <dd class="text-xs tabular-nums text-gray-500">
                            {{
                                tc(
                                    ':count image|:count images',
                                    total.succeeded,
                                )
                            }}
                            · {{ tc('+:count failed, free', total.failed) }}
                        </dd>
                    </div>
                </dl>

                <section
                    v-if="!filters.user"
                    aria-labelledby="reconciliation-heading"
                    class="mt-6"
                >
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
                                        <code class="font-mono text-xs"
                                            >GET /v1/usage</code
                                        >
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
                                        <table
                                            class="w-full min-w-[32rem] text-left"
                                        >
                                            <caption class="sr-only">
                                                {{
                                                    t(
                                                        'Days that differ from Pixelforge',
                                                    )
                                                }}
                                            </caption>
                                            <thead
                                                class="sticky top-0 bg-red-50 text-xs uppercase tracking-wide text-red-800"
                                            >
                                                <tr>
                                                    <th
                                                        scope="col"
                                                        class="px-3 py-1.5"
                                                    >
                                                        {{ t('Day') }}
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        class="px-3 py-1.5 text-right"
                                                    >
                                                        {{
                                                            t(
                                                                'Images (ours / theirs)',
                                                            )
                                                        }}
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
                                                        {{
                                                            day.theirGenerations
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-1.5 text-right tabular-nums"
                                                    >
                                                        {{
                                                            day.ourCost.display
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-1.5 text-right tabular-nums"
                                                    >
                                                        {{
                                                            day.theirCost
                                                                .display
                                                        }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-1.5 text-right font-medium tabular-nums"
                                                    >
                                                        {{
                                                            day.difference
                                                                .display
                                                        }}
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

                <section class="mt-8" :aria-label="t('Charged per day')">
                    <SpendChart
                        :days="days"
                        @select="
                            (date) =>
                                visit({ from: date, to: date, page: null })
                        "
                    />
                    <details class="group mt-3">
                        <summary
                            class="inline-flex min-h-10 cursor-pointer items-center text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            {{
                                t(
                                    'Show the daily table (:count days with activity)',
                                    { count: activeDays },
                                )
                            }}
                        </summary>
                        <div class="mt-3">
                            <SpendTable
                                :caption="t('Spend per UTC day')"
                                :heading="t('Day (UTC)')"
                                :rows="dayRows"
                                :total="total"
                            />
                        </div>
                    </details>
                </section>

                <section v-if="users" aria-labelledby="by-user" class="mt-10">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2
                                id="by-user"
                                class="text-base font-semibold text-gray-900"
                            >
                                {{ t('By user') }}
                            </h2>
                            <p class="text-xs text-gray-500">
                                {{
                                    t(
                                        'Select a user to see only their numbers.',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label
                                    for="user-search"
                                    class="block text-xs font-medium text-gray-700"
                                    >{{ t('Search') }}</label
                                >
                                <input
                                    id="user-search"
                                    v-model="search"
                                    type="search"
                                    autocomplete="off"
                                    :placeholder="t('Name or email')"
                                    class="mt-1 block min-h-11 w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label
                                    for="user-sort"
                                    class="block text-xs font-medium text-gray-700"
                                    >{{ t('Sort by') }}</label
                                >
                                <select
                                    id="user-sort"
                                    v-model="sort"
                                    class="mt-1 block min-h-11 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="charged">
                                        {{ t('Most charged') }}
                                    </option>
                                    <option value="profit">
                                        {{ t('Most profit') }}
                                    </option>
                                    <option value="images">
                                        {{ t('Most images') }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <SpendTable
                            :caption="t('Spend per user')"
                            :heading="t('User')"
                            :rows="userRows"
                            :empty="
                                search
                                    ? t('No users match that search.')
                                    : undefined
                            "
                        />
                        <Pagination
                            :links="users.links"
                            :from="users.from"
                            :to="users.to"
                            :total="users.total"
                            :only="['users', 'filters']"
                        />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
