<script setup lang="ts">
import { t, tc } from '@/lib/i18n';
import type { SpendTotals } from '@/types/models';

defineProps<{ total: SpendTotals }>();
</script>

<template>
    <dl
        class="mt-6 grid grid-cols-2 divide-gray-200 rounded-lg border border-gray-200 bg-white sm:grid-cols-4 sm:divide-x"
    >
        <div class="p-4">
            <dt class="text-xs font-medium text-gray-500">
                {{ t('Charged to users') }}
            </dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ total.charged.cents }}
            </dd>
            <dd
                class="text-xs tabular-nums text-gray-500"
                :title="t('Exact amount')"
            >
                {{ total.charged.display }}
            </dd>
        </div>
        <div class="p-4">
            <dt class="text-xs font-medium text-gray-500">
                {{ t('Pixelforge cost') }}
            </dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ total.providerCost.cents }}
            </dd>
            <dd
                class="text-xs tabular-nums text-gray-500"
                :title="t('Exact amount')"
            >
                {{ total.providerCost.display }}
            </dd>
        </div>
        <div class="p-4">
            <dt class="text-xs font-medium text-gray-500">
                {{ t('Profit') }}
            </dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ total.profit.cents }}
            </dd>
            <dd
                class="text-xs tabular-nums text-gray-500"
                :title="t('Exact amount')"
            >
                {{ total.profit.display }}
            </dd>
        </div>
        <div class="p-4">
            <dt class="text-xs font-medium text-gray-500">
                {{ t('Margin') }}
            </dt>
            <dd class="mt-1 text-xl font-semibold text-gray-900">
                {{ total.margin ?? '—' }}
            </dd>
            <dd class="text-xs tabular-nums text-gray-500">
                {{ tc(':count image|:count images', total.succeeded) }}
                · {{ tc('+:count failed, free', total.failed) }}
            </dd>
        </div>
    </dl>
</template>
