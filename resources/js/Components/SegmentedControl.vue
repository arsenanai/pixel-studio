<script setup lang="ts">
import type { Option } from '@/types/models';

defineProps<{
    name: string;
    legend: string;
    options: Option[];
}>();

const model = defineModel<string>({ required: true });

const SHAPE = 30;

function shape(option: Option): { width: string; height: string } | null {
    if (!option.width || !option.height) {
        return null;
    }

    const longest = Math.max(option.width, option.height);

    return {
        width: `${Math.round((SHAPE * option.width) / longest)}px`,
        height: `${Math.round((SHAPE * option.height) / longest)}px`,
    };
}
</script>

<template>
    <fieldset>
        <legend class="text-sm font-medium text-gray-900">{{ legend }}</legend>
        <div class="mt-2 grid grid-cols-3 gap-2">
            <label
                v-for="option in options"
                :key="option.value"
                class="relative flex min-h-11 cursor-pointer select-none flex-col items-center justify-center rounded-md border px-3 py-2.5 text-center text-sm transition-colors focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2"
                :class="
                    model === option.value
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-950'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'
                "
            >
                <input
                    v-model="model"
                    type="radio"
                    class="sr-only"
                    :name="name"
                    :value="option.value"
                />
                <span
                    v-if="shape(option)"
                    class="mb-2 flex h-[30px] items-center justify-center"
                    aria-hidden="true"
                >
                    <span
                        class="block rounded-[3px] border-2 transition-colors"
                        :class="
                            model === option.value
                                ? 'border-indigo-600 bg-indigo-200/60'
                                : 'border-gray-400 bg-gray-100'
                        "
                        :style="shape(option) ?? undefined"
                    />
                </span>
                <span
                    v-else-if="option.level"
                    class="mb-2 flex h-[30px] items-end justify-center gap-[3px]"
                    aria-hidden="true"
                >
                    <span
                        v-for="bar in 3"
                        :key="bar"
                        class="block w-[6px] rounded-sm transition-colors"
                        :style="{ height: `${10 + bar * 6}px` }"
                        :class="
                            bar <= option.level
                                ? model === option.value
                                    ? 'bg-indigo-600'
                                    : 'bg-gray-500'
                                : 'bg-gray-200'
                        "
                    />
                </span>
                <span class="font-medium">{{ option.label }}</span>
                <span
                    v-if="option.hint"
                    class="mt-0.5 text-xs tabular-nums"
                    :class="
                        model === option.value
                            ? 'text-indigo-700'
                            : 'text-gray-500'
                    "
                >
                    {{ option.hint }}
                </span>
            </label>
        </div>
    </fieldset>
</template>
