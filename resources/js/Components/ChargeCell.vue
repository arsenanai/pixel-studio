<script setup lang="ts">
import type { Generation } from '@/types/models';

defineProps<{ generation: Generation }>();
</script>

<template>
    <template v-if="generation.status === 'succeeded' && generation.charge">
        <span class="font-medium text-gray-900">{{
            generation.charge.display
        }}</span>
    </template>
    <template v-else-if="generation.status === 'failed'">
        <span class="text-gray-500">$0.00</span>
        <span class="block text-xs text-gray-500">Not charged</span>
    </template>
    <template v-else>
        <span class="text-gray-400" aria-hidden="true">—</span>
        <span class="block text-xs text-gray-500">When it finishes</span>
    </template>
</template>
