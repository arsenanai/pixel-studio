<script setup lang="ts">
import type { GenerationStatus } from '@/types/models';
import { computed } from 'vue';

const props = defineProps<{ status: GenerationStatus; label: string }>();

const dot = computed(
    () =>
        ({
            queued: 'bg-gray-400',
            processing: 'bg-amber-500',
            succeeded: 'bg-emerald-500',
            failed: 'bg-red-500',
        })[props.status],
);

const pending = computed(
    () => props.status === 'queued' || props.status === 'processing',
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap text-sm text-gray-700"
    >
        <span class="relative flex h-2 w-2" aria-hidden="true">
            <span
                v-if="pending"
                class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 motion-reduce:hidden"
                :class="dot"
            />
            <span
                class="relative inline-flex h-2 w-2 rounded-full"
                :class="dot"
            />
        </span>
        {{ label }}
    </span>
</template>
