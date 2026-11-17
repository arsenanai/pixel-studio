<script setup lang="ts">
import type { Generation } from '@/types/models';
import { computed, ref } from 'vue';

const props = defineProps<{ generation: Generation; size: 'sm' | 'lg' }>();

// Signed URLs stop working 24h after completion; a quiet placeholder beats a broken image.
const failedToLoad = ref(false);

const url = computed(() =>
    failedToLoad.value ? null : (props.generation.image?.url ?? null),
);

// null while pending: the box pulses instead of showing text.
const placeholder = computed(
    () =>
        ({
            queued: null,
            processing: null,
            failed: 'No image',
            succeeded: 'Expired',
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
        :class="box"
    >
        <img
            :src="url"
            :alt="`Generated image: ${generation.prompt}`"
            loading="lazy"
            class="h-full w-full object-cover"
            @error="failedToLoad = true"
        />
        <span class="sr-only">(opens full size in a new tab)</span>
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
