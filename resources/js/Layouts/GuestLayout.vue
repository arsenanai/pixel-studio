<script setup lang="ts">
import BrandMark from '@/Components/BrandMark.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { t } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-50 px-4 pt-10 sm:justify-center sm:pt-0"
    >
        <div class="absolute right-4 top-4">
            <LanguageSwitcher />
        </div>

        <Link href="/" class="text-lg">
            <BrandMark />
        </Link>
        <p class="mt-2 max-w-sm text-center text-sm text-gray-600">
            {{
                t(
                    'Generate images and pay exactly what they cost. Failed images are free.',
                )
            }}
        </p>

        <div
            class="mt-6 w-full overflow-hidden rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
