<script setup lang="ts">
import ChargeCell from '@/Components/ChargeCell.vue';
import GenerationImage from '@/Components/GenerationImage.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDateTime, formatMoneyRange } from '@/lib/format';
import { intlLocale, t, tc } from '@/lib/i18n';
import type { Generation, Paginated } from '@/types/models';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type Filter = 'all' | 'active' | 'succeeded' | 'failed';

const props = defineProps<{
    generations: Paginated<Generation>;
    filters: { status: Filter; q: string };
    counts: Record<Filter, number>;
    hasPending: boolean;
}>();

const { start, stop } = usePoll(
    2500,
    { only: ['generations', 'hasPending', 'counts'] },
    { autoStart: false },
);

watch(
    () => props.hasPending,
    (pending) => (pending ? start() : stop()),
    { immediate: true },
);

const search = ref(props.filters.q);
const loading = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

function visit(status: Filter, q: string): void {
    router.get(
        route('history'),
        Object.fromEntries(
            Object.entries({
                status: status === 'all' ? '' : status,
                q,
            }).filter(([, value]) => value !== ''),
        ),
        {
            only: ['generations', 'filters', 'counts', 'hasPending'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(
        () => visit(props.filters.status, value.trim()),
        300,
    );
});

onBeforeUnmount(() => clearTimeout(searchTimer));

const tabs = computed(() => [
    { value: 'all' as const, label: t('All') },
    { value: 'active' as const, label: t('In progress') },
    { value: 'succeeded' as const, label: t('Done') },
    { value: 'failed' as const, label: t('Failed') },
]);

const number = computed(() => new Intl.NumberFormat(intlLocale()));

const pendingOnPage = computed(
    () =>
        props.generations.data.filter(
            (g) => g.status === 'queued' || g.status === 'processing',
        ).length,
);

const liveSummary = computed(() =>
    pendingOnPage.value === 0
        ? t('All images on this page are finished.')
        : tc(
              ':count image is still in progress.|:count images are still in progress.',
              pendingOnPage.value,
          ),
);

const emptyMessage = computed(() => {
    if (props.filters.q !== '') {
        return t('No images match “:q”.', { q: props.filters.q });
    }

    return {
        all: t('No images yet'),
        active: t('Nothing is in progress right now.'),
        succeeded: t('No finished images yet.'),
        failed: t('No failed images. Failed images are always free.'),
    }[props.filters.status];
});

const isFiltered = computed(
    () => props.filters.q !== '' || props.filters.status !== 'all',
);

function estimate(generation: Generation): string {
    return formatMoneyRange(
        generation.estimate.min.display,
        generation.estimate.max.display,
    );
}

function details(generation: Generation): string {
    const parts = [
        generation.size.label,
        generation.size.dimensions,
        generation.quality.label,
    ];

    if (generation.referenceImageCount > 0) {
        parts.push(
            tc(
                ':count reference|:count references',
                generation.referenceImageCount,
            ),
        );
    }

    return parts.join(' · ');
}
</script>

<template>
    <Head :title="t('History')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-gray-900"
                    >
                        {{ t('History') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{
                            t(
                                "Every image you've asked for, what we estimated, and what you were actually charged.",
                            )
                        }}
                    </p>
                </div>
                <Link
                    :href="route('generate')"
                    class="inline-flex min-h-11 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {{ t('New image') }}
                </Link>
            </header>

            <p class="sr-only" aria-live="polite">{{ liveSummary }}</p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <nav
                    class="-mx-1 flex max-w-full gap-1 overflow-x-auto px-1"
                    :aria-label="t('Filter by status')"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="
                            filters.status === tab.value
                                ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200'
                                : 'text-gray-600 hover:bg-white/60 hover:text-gray-900'
                        "
                        :aria-pressed="filters.status === tab.value"
                        @click="visit(tab.value, search.trim())"
                    >
                        {{ tab.label }}
                        <span
                            class="rounded-full px-2 py-0.5 text-xs tabular-nums"
                            :class="
                                filters.status === tab.value
                                    ? 'bg-indigo-50 text-indigo-700'
                                    : 'bg-gray-100 text-gray-600'
                            "
                            >{{ number.format(counts[tab.value]) }}</span
                        >
                    </button>
                </nav>
                <div class="w-full sm:w-72">
                    <label for="history-search" class="sr-only">{{
                        t('Search prompts')
                    }}</label>
                    <input
                        id="history-search"
                        v-model="search"
                        type="search"
                        autocomplete="off"
                        :placeholder="t('Search prompts')"
                        class="block min-h-11 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
            </div>

            <div
                class="transition-opacity"
                :class="loading ? 'opacity-60' : ''"
                :aria-busy="loading"
            >
                <div
                    v-if="generations.data.length === 0"
                    class="mt-6 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center"
                >
                    <h2 class="text-sm font-medium text-gray-900">
                        {{ emptyMessage }}
                    </h2>
                    <template v-if="!isFiltered">
                        <p class="mt-1 text-sm text-gray-500">
                            {{
                                t(
                                    "You'll see the price before you submit, and only pay for images that succeed.",
                                )
                            }}
                        </p>
                        <Link
                            :href="route('generate')"
                            class="mt-6 inline-flex min-h-11 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {{ t('Create your first image') }}
                        </Link>
                    </template>
                    <button
                        v-else
                        type="button"
                        class="mt-4 text-sm font-medium text-indigo-700 underline underline-offset-2 hover:text-indigo-900"
                        @click="
                            search = '';
                            visit('all', '');
                        "
                    >
                        {{ t('Clear filters') }}
                    </button>
                </div>

                <template v-else>
                    <div
                        class="mt-4 hidden overflow-hidden rounded-lg border border-gray-200 bg-white md:block"
                    >
                        <table class="w-full table-fixed text-sm">
                            <caption class="sr-only">
                                {{
                                    t('Your generations, newest first')
                                }}
                            </caption>
                            <thead
                                class="border-b border-gray-200 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                <tr>
                                    <th scope="col" class="w-20 px-4 py-3">
                                        <span class="sr-only">{{
                                            t('Image')
                                        }}</span>
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        {{ t('Prompt') }}
                                    </th>
                                    <th scope="col" class="w-40 px-4 py-3">
                                        {{ t('Status') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="w-48 px-4 py-3 text-right"
                                    >
                                        {{ t('Estimate') }}
                                    </th>
                                    <th
                                        scope="col"
                                        class="w-36 px-4 py-3 text-right"
                                    >
                                        {{ t('Charged') }}
                                    </th>
                                </tr>
                            </thead>
                            <TransitionGroup
                                tag="tbody"
                                name="row"
                                class="divide-y divide-gray-100"
                            >
                                <tr
                                    v-for="generation in generations.data"
                                    :key="generation.id"
                                    class="align-top"
                                >
                                    <td class="px-4 py-3">
                                        <GenerationImage
                                            :generation="generation"
                                            size="sm"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="line-clamp-2 text-gray-900"
                                            :title="generation.prompt"
                                        >
                                            {{ generation.prompt }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ details(generation) }} ·
                                            <time
                                                :datetime="generation.createdAt"
                                                >{{
                                                    formatDateTime(
                                                        generation.createdAt,
                                                    )
                                                }}</time
                                            >
                                        </p>
                                        <p
                                            v-if="generation.error"
                                            class="mt-1 text-xs text-red-700"
                                        >
                                            {{ generation.error }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <StatusBadge
                                            :status="generation.status"
                                            :label="generation.statusLabel"
                                        />
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right tabular-nums text-gray-600"
                                    >
                                        {{ estimate(generation) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right tabular-nums"
                                    >
                                        <ChargeCell :generation="generation" />
                                    </td>
                                </tr>
                            </TransitionGroup>
                        </table>
                    </div>

                    <TransitionGroup
                        tag="ul"
                        name="row"
                        class="mt-4 space-y-3 md:hidden"
                    >
                        <li
                            v-for="generation in generations.data"
                            :key="generation.id"
                            class="rounded-lg border border-gray-200 bg-white p-4"
                        >
                            <div class="flex gap-3">
                                <GenerationImage
                                    :generation="generation"
                                    size="sm"
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="line-clamp-2 text-sm text-gray-900"
                                    >
                                        {{ generation.prompt }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ details(generation) }} ·
                                        <time
                                            :datetime="generation.createdAt"
                                            >{{
                                                formatDateTime(
                                                    generation.createdAt,
                                                )
                                            }}</time
                                        >
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="generation.error"
                                class="mt-3 text-xs text-red-700"
                            >
                                {{ generation.error }}
                            </p>
                            <dl
                                class="mt-3 grid grid-cols-3 gap-2 border-t border-gray-100 pt-3 text-xs"
                            >
                                <div>
                                    <dt class="text-gray-500">
                                        {{ t('Status') }}
                                    </dt>
                                    <dd class="mt-1">
                                        <StatusBadge
                                            :status="generation.status"
                                            :label="generation.statusLabel"
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">
                                        {{ t('Estimate') }}
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm tabular-nums text-gray-700"
                                    >
                                        {{ estimate(generation) }}
                                    </dd>
                                </div>
                                <div class="text-right">
                                    <dt class="text-gray-500">
                                        {{ t('Charged') }}
                                    </dt>
                                    <dd class="mt-1 text-sm tabular-nums">
                                        <ChargeCell :generation="generation" />
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    </TransitionGroup>

                    <Pagination
                        :links="generations.meta.links"
                        :from="generations.meta.from"
                        :to="generations.meta.to"
                        :total="generations.meta.total"
                        :only="[
                            'generations',
                            'filters',
                            'counts',
                            'hasPending',
                        ]"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.row-enter-active {
    transition: opacity 200ms ease-out;
}
.row-enter-from {
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .row-enter-active {
        transition: none;
    }
}
</style>
