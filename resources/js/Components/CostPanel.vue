<script setup lang="ts">
import type { QuoteState } from '@/composables/useQuote';
import { formatMoneyRange, formatTokenRange } from '@/lib/format';
import type { Money, PriceQuote, Range } from '@/types/models';
import { computed } from 'vue';

const props = defineProps<{
    quote: PriceQuote | null;
    state: QuoteState;
    error: string | null;
}>();

// A reference image line with no images is noise; every other line always applies.
const lines = computed(
    () =>
        props.quote?.lines.filter(
            (line) => line.type !== 'input_image_tokens' || line.tokens.max > 0,
        ) ?? [],
);

function range(money: Range<Money>): string {
    return formatMoneyRange(money.min.display, money.max.display);
}
</script>

<template>
    <section
        aria-labelledby="cost-heading"
        class="rounded-lg border border-gray-200 bg-white p-5"
    >
        <h2 id="cost-heading" class="text-sm font-medium text-gray-500">
            Estimated cost
        </h2>

        <div aria-live="polite" class="mt-1">
            <template v-if="quote">
                <p
                    class="text-xl font-semibold tabular-nums tracking-tight text-gray-900 transition-opacity"
                    :class="{ 'opacity-40': state === 'loading' }"
                >
                    <span class="whitespace-nowrap">{{
                        quote.charge.min.display
                    }}</span>
                    <span class="text-gray-400"> – </span>
                    <span class="whitespace-nowrap">{{
                        quote.charge.max.display
                    }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    Usually about
                    <span class="tabular-nums text-gray-700">{{
                        quote.charge.typical.display
                    }}</span>
                </p>
            </template>
            <div v-else-if="state !== 'error'" class="space-y-2 pt-1">
                <div
                    class="h-6 w-48 animate-pulse rounded bg-gray-100 motion-reduce:animate-none"
                />
                <div
                    class="h-4 w-32 animate-pulse rounded bg-gray-100 motion-reduce:animate-none"
                />
            </div>

            <p
                v-if="state === 'error'"
                role="alert"
                class="mt-2 text-sm text-red-700"
            >
                {{ error }}
            </p>
        </div>

        <table v-if="quote" class="mt-5 w-full text-sm">
            <caption class="sr-only">
                Cost breakdown at Pixelforge's rates
            </caption>
            <thead class="sr-only">
                <tr>
                    <th scope="col">Item</th>
                    <th scope="col">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="line in lines" :key="line.type">
                    <th
                        scope="row"
                        class="py-2 pr-3 text-left font-normal text-gray-700"
                    >
                        {{ line.label }}
                        <span class="block text-xs tabular-nums text-gray-500">
                            {{
                                formatTokenRange(
                                    line.tokens.min,
                                    line.tokens.max,
                                )
                            }}
                            tokens at {{ line.ratePerMillion.display }}/1M
                        </span>
                    </th>
                    <td
                        class="py-2 text-right align-top tabular-nums text-gray-900"
                    >
                        {{ range(line.cost) }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t border-gray-200 text-gray-700">
                <tr>
                    <th scope="row" class="pt-3 text-left font-normal">
                        Pixelforge's cost
                    </th>
                    <td class="pt-3 text-right tabular-nums">
                        {{ range(quote.providerCost) }}
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="pt-1 text-left font-normal">
                        Our {{ quote.markupPercent }}% markup
                    </th>
                    <td class="pt-1 text-right tabular-nums">
                        {{ range(quote.markup) }}
                    </td>
                </tr>
                <tr class="font-medium text-gray-900">
                    <th scope="row" class="pt-1 text-left">You pay</th>
                    <td class="pt-1 text-right tabular-nums">
                        {{ range(quote.charge) }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <p class="mt-4 text-xs leading-5 text-gray-500">
            The exact amount depends on how many tokens Pixelforge counts in
            your prompt and how long the model reasons. You're charged exactly
            that when the image is ready. Failed generations are free.
        </p>
    </section>
</template>
