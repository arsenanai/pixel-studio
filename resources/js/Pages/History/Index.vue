<script setup lang="ts">
import ChargeCell from '@/Components/ChargeCell.vue';
import GenerationImage from '@/Components/GenerationImage.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDateTime, formatMoneyRange } from '@/lib/format';
import type { Generation, Paginated } from '@/types/models';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps<{
    generations: Paginated<Generation>;
    hasPending: boolean;
}>();

// Poll our own server (not Pixelforge) only while something can still change.
const { start, stop } = usePoll(
    2500,
    { only: ['generations', 'hasPending'] },
    { autoStart: false },
);

watch(
    () => props.hasPending,
    (pending) => (pending ? start() : stop()),
    { immediate: true },
);

const pendingOnPage = computed(
    () =>
        props.generations.data.filter(
            (g) => g.status === 'queued' || g.status === 'processing',
        ).length,
);

const liveSummary = computed(() =>
    pendingOnPage.value === 0
        ? 'All images on this page are finished.'
        : `${pendingOnPage.value} ${pendingOnPage.value === 1 ? 'image is' : 'images are'} still in progress.`,
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
            `${generation.referenceImageCount} reference${generation.referenceImageCount === 1 ? '' : 's'}`,
        );
    }

    return parts.join(' · ');
}
</script>

<template>
    <Head title="History" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-gray-900"
                    >
                        History
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Every image you've asked for, what we estimated, and
                        what you were actually charged.
                    </p>
                </div>
                <Link
                    :href="route('generate')"
                    class="inline-flex min-h-11 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    New image
                </Link>
            </header>

            <p class="sr-only" aria-live="polite">{{ liveSummary }}</p>

            <div
                v-if="generations.data.length === 0"
                class="mt-10 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center"
            >
                <h2 class="text-sm font-medium text-gray-900">No images yet</h2>
                <p class="mt-1 text-sm text-gray-500">
                    You'll see the price before you submit, and only pay for
                    images that succeed.
                </p>
                <Link
                    :href="route('generate')"
                    class="mt-6 inline-flex min-h-11 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Create your first image
                </Link>
            </div>

            <template v-else>
                <!-- Desktop: a table, so money lines up in columns. -->
                <div
                    class="mt-8 hidden overflow-hidden rounded-lg border border-gray-200 bg-white md:block"
                >
                    <table class="w-full table-fixed text-sm">
                        <caption class="sr-only">
                            Your generations, newest first
                        </caption>
                        <thead
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            <tr>
                                <th scope="col" class="w-20 px-4 py-3">
                                    <span class="sr-only">Image</span>
                                </th>
                                <th scope="col" class="px-4 py-3">Prompt</th>
                                <th scope="col" class="w-40 px-4 py-3">
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="w-48 px-4 py-3 text-right"
                                >
                                    Estimate
                                </th>
                                <th
                                    scope="col"
                                    class="w-36 px-4 py-3 text-right"
                                >
                                    Charged
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
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <ChargeCell :generation="generation" />
                                </td>
                            </tr>
                        </TransitionGroup>
                    </table>
                </div>

                <!-- Mobile: one card per generation. -->
                <TransitionGroup
                    tag="ul"
                    name="row"
                    class="mt-6 space-y-3 md:hidden"
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
                                <p class="line-clamp-2 text-sm text-gray-900">
                                    {{ generation.prompt }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ details(generation) }}
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
                                <dt class="text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <StatusBadge
                                        :status="generation.status"
                                        :label="generation.statusLabel"
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Estimate</dt>
                                <dd
                                    class="mt-1 text-sm tabular-nums text-gray-700"
                                >
                                    {{ estimate(generation) }}
                                </dd>
                            </div>
                            <div class="text-right">
                                <dt class="text-gray-500">Charged</dt>
                                <dd class="mt-1 text-sm tabular-nums">
                                    <ChargeCell :generation="generation" />
                                </dd>
                            </div>
                        </dl>
                    </li>
                </TransitionGroup>

                <nav
                    v-if="generations.meta.last_page > 1"
                    class="mt-6 flex items-center justify-between text-sm"
                    aria-label="Pagination"
                >
                    <p class="tabular-nums text-gray-600">
                        {{ generations.meta.from }}–{{ generations.meta.to }} of
                        {{ generations.meta.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="generations.links.prev"
                            :href="generations.links.prev"
                            preserve-scroll
                            class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Newer
                        </Link>
                        <Link
                            v-if="generations.links.next"
                            :href="generations.links.next"
                            preserve-scroll
                            class="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Older
                        </Link>
                    </div>
                </nav>
            </template>
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
