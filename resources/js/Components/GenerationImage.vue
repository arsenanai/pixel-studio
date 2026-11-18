<script setup lang="ts">
import { t } from '@/lib/i18n';
import type { Generation } from '@/types/models';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ generation: Generation; size: 'sm' | 'lg' }>();

const failedToLoad = ref(false);
const loaded = ref(false);

const url = computed(() =>
    failedToLoad.value ? null : (props.generation.image?.url ?? null),
);

watch(url, () => (loaded.value = false));

const placeholder = computed(
    () =>
        ({
            queued: null,
            processing: null,
            failed: t('No image'),
            succeeded: t('Expired'),
        })[props.generation.status],
);

const box = computed(() =>
    props.size === 'sm' ? 'h-14 w-14' : 'aspect-square w-full',
);
</script>

<template>
    <a
        v-if="url"
        :href="url"
        target="_blank"
        rel="noopener"
        class="block shrink-0 overflow-hidden rounded-md bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        :class="[box, { 'animate-pulse motion-reduce:animate-none': !loaded }]"
        :aria-busy="!loaded"
    >
        <img
            :src="url"
            :alt="t('Generated image: :prompt', { prompt: generation.prompt })"
            loading="lazy"
            decoding="async"
            class="h-full w-full object-cover transition-opacity duration-300 motion-reduce:transition-none"
            :class="loaded ? 'opacity-100' : 'opacity-0'"
            @load="loaded = true"
            @error="failedToLoad = true"
        />
        <span class="sr-only">{{ t('(opens full size in a new tab)') }}</span>
    </a>
    <div
        v-else
        class="flex shrink-0 items-center justify-center rounded-md border border-dashed border-gray-300 bg-gray-50 text-[11px] text-gray-500"
        :class="[
            box,
            {
                'animate-pulse border-solid border-gray-200 bg-gray-100 motion-reduce:animate-none':
                    placeholder === null,
            },
        ]"
    >
        {{ placeholder }}
    </div>
</template>
