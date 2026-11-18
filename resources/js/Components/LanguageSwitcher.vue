<script setup lang="ts">
import { t } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

function choose(locale: string): void {
    if (locale !== page.props.locale) {
        router.post(
            route('locale'),
            { locale },
            { preserveScroll: true, preserveState: false },
        );
    }
}
</script>

<template>
    <div
        class="inline-flex rounded-md border border-gray-200 bg-white p-0.5 text-xs font-medium"
        role="group"
        :aria-label="t('Language')"
    >
        <button
            v-for="(name, code) in page.props.locales"
            :key="code"
            type="button"
            :lang="code"
            class="min-h-8 rounded px-2 uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="
                page.props.locale === code
                    ? 'bg-gray-900 text-white'
                    : 'text-gray-600 hover:bg-gray-100'
            "
            :aria-pressed="page.props.locale === code"
            :title="name"
            @click="choose(String(code))"
        >
            {{ code }}
            <span class="sr-only">{{ name }}</span>
        </button>
    </div>
</template>
