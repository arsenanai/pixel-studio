<script setup lang="ts">
import { intlLocale, t } from '@/lib/i18n';
import type { PageLink } from '@/types/models';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    links: PageLink[];
    from: number | null;
    to: number | null;
    total: number;
    only?: string[];
}>();

const previous = computed(() => props.links[0]?.url ?? null);
const next = computed(() => props.links[props.links.length - 1]?.url ?? null);
const pages = computed(() => props.links.slice(1, -1));
const number = computed(() => new Intl.NumberFormat(intlLocale()));
</script>

<template>
    <nav
        v-if="previous || next"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
        :aria-label="t('Pagination')"
    >
        <p class="tabular-nums text-gray-600">
            {{
                t(':from–:to of :total', {
                    from: number.format(from ?? 0),
                    to: number.format(to ?? 0),
                    total: number.format(total),
                })
            }}
        </p>
        <div class="flex items-center gap-1">
            <component
                :is="previous ? Link : 'span'"
                :href="previous ?? undefined"
                :only="only"
                preserve-scroll
                preserve-state
                class="inline-flex min-h-10 items-center rounded-md border border-gray-300 bg-white px-3 font-medium text-gray-700"
                :class="
                    previous
                        ? 'hover:bg-gray-50'
                        : 'cursor-not-allowed opacity-50'
                "
                :aria-disabled="previous ? undefined : 'true'"
            >
                {{ t('Previous') }}
            </component>
            <template v-for="(link, index) in pages" :key="index">
                <span
                    v-if="link.url === null"
                    class="hidden px-2 text-gray-400 sm:inline"
                    aria-hidden="true"
                    >…</span
                >
                <Link
                    v-else
                    :href="link.url"
                    :only="only"
                    preserve-scroll
                    preserve-state
                    class="hidden min-h-10 min-w-10 items-center justify-center rounded-md px-2 tabular-nums sm:inline-flex"
                    :class="
                        link.active
                            ? 'bg-indigo-600 font-semibold text-white'
                            : 'text-gray-700 hover:bg-gray-100'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </template>
            <component
                :is="next ? Link : 'span'"
                :href="next ?? undefined"
                :only="only"
                preserve-scroll
                preserve-state
                class="inline-flex min-h-10 items-center rounded-md border border-gray-300 bg-white px-3 font-medium text-gray-700"
                :class="
                    next ? 'hover:bg-gray-50' : 'cursor-not-allowed opacity-50'
                "
                :aria-disabled="next ? undefined : 'true'"
            >
                {{ t('Next') }}
            </component>
        </div>
    </nav>
</template>
