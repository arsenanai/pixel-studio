<script setup lang="ts">
import ReconciliationNotice from '@/Components/Admin/ReconciliationNotice.vue';
import SpendSummary from '@/Components/Admin/SpendSummary.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import SpendChart from '@/Components/SpendChart.vue';
import SpendTable, { type SpendRow } from '@/Components/SpendTable.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDay } from '@/lib/format';
import { t } from '@/lib/i18n';
import type {
    Page,
    Reconciliation,
    ReportUser,
    SpendTotals,
} from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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

const dayRows = computed<SpendRow[]>(() =>
    [...props.days].reverse().map((day) => ({
        key: day.date,
        label: formatDay(day.date, 'long'),
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
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
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
                <SpendSummary :total="total" />

                <ReconciliationNotice
                    v-if="!filters.user"
                    :reconciliation="reconciliation"
                    :source="source"
                />

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
