<script setup lang="ts">
import { t } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

const page = usePage();
const dismissed = ref(false);

const stop = router.on('success', () => {
    dismissed.value = false;
});
onBeforeUnmount(stop);

watch(
    () => page.props.flash,
    () => {
        dismissed.value = false;
    },
);
</script>

<template>
    <div
        v-if="
            !dismissed && (page.props.flash.success || page.props.flash.error)
        "
        class="mx-auto max-w-5xl px-4 pt-6 sm:px-6 lg:px-8"
    >
        <div
            :role="page.props.flash.error ? 'alert' : 'status'"
            class="flex items-start justify-between gap-4 rounded-md border px-4 py-3 text-sm"
            :class="
                page.props.flash.error
                    ? 'border-red-200 bg-red-50 text-red-800'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-900'
            "
        >
            <p>{{ page.props.flash.error ?? page.props.flash.success }}</p>
            <button
                type="button"
                class="-m-1 inline-flex min-h-8 min-w-8 items-center justify-center rounded opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                @click="dismissed = true"
            >
                <span class="sr-only">{{ t('Dismiss') }}</span>
                <svg
                    class="h-4 w-4"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                    />
                </svg>
            </button>
        </div>
    </div>
</template>
