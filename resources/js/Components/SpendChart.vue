<script setup lang="ts">
import { intlLocale, t, tc } from '@/lib/i18n';
import type { SpendTotals } from '@/types/models';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    days: { date: string; totals: SpendTotals }[];
}>();

const emit = defineEmits<{ select: [date: string] }>();

const HEIGHT = 240;
const AXIS = 28;
const LEFT = 64;
const RIGHT = 8;
const TOP = 12;
const GAP = 2;
const RADIUS = 4;

const root = ref<HTMLElement | null>(null);
const width = ref(720);
const active = ref<number | null>(null);
let observer: ResizeObserver | null = null;

onMounted(() => {
    if (!root.value) {
        return;
    }

    width.value = root.value.clientWidth || width.value;
    observer = new ResizeObserver((entries) => {
        const entry = entries[0];

        if (entry) {
            width.value = Math.max(280, Math.floor(entry.contentRect.width));
        }
    });
    observer.observe(root.value);
});

onBeforeUnmount(() => observer?.disconnect());

function dollars(nano: string): number {
    return Number(nano) / 1e9;
}

const plotWidth = computed(() => width.value - LEFT - RIGHT);
const plotHeight = HEIGHT - TOP - AXIS;
const baseline = TOP + plotHeight;

const scaleMax = computed(() => {
    const max = Math.max(
        ...props.days.map((day) => dollars(day.totals.charged.nano)),
        0,
    );

    if (max === 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(max));
    const step = [1, 2, 2.5, 5, 10].find((s) => s * magnitude * 4 >= max) ?? 10;

    return step * magnitude * 4;
});

const ticks = computed(() =>
    [0, 1, 2, 3, 4].map((i) => (scaleMax.value / 4) * i),
);

const money = computed(
    () =>
        new Intl.NumberFormat(intlLocale(), {
            style: 'currency',
            currency: 'USD',
            notation: scaleMax.value >= 10_000 ? 'compact' : 'standard',
            maximumFractionDigits: scaleMax.value < 1 ? 2 : 0,
            minimumFractionDigits: 0,
        }),
);

const dateLabel = computed(
    () =>
        new Intl.DateTimeFormat(intlLocale(), {
            month: 'short',
            day: 'numeric',
            timeZone: 'UTC',
        }),
);

const longDate = computed(
    () =>
        new Intl.DateTimeFormat(intlLocale(), {
            weekday: 'short',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            timeZone: 'UTC',
        }),
);

function y(value: number): number {
    return baseline - (value / scaleMax.value) * plotHeight;
}

const band = computed(() => plotWidth.value / Math.max(props.days.length, 1));
const barWidth = computed(() => Math.max(2, Math.min(24, band.value - GAP)));

function topRounded(x: number, top: number, w: number, h: number): string {
    const r = Math.min(RADIUS, w / 2, h);

    return [
        `M${x},${top + h}`,
        `V${top + r}`,
        `Q${x},${top} ${x + r},${top}`,
        `H${x + w - r}`,
        `Q${x + w},${top} ${x + w},${top + r}`,
        `V${top + h}`,
        'Z',
    ].join(' ');
}

const bars = computed(() =>
    props.days.map((day, index) => {
        const cost = dollars(day.totals.providerCost.nano);
        const profit = dollars(day.totals.profit.nano);
        const x = LEFT + index * band.value + (band.value - barWidth.value) / 2;
        const costTop = y(cost);
        const total = y(cost + profit);
        const costHeight = baseline - costTop;
        const profitHeight = costTop - total - GAP;
        const hasProfit = profitHeight >= 1;

        return {
            day,
            index,
            hitX: LEFT + index * band.value,
            cost:
                costHeight > 0
                    ? hasProfit
                        ? `M${x},${baseline} V${costTop} H${x + barWidth.value} V${baseline} Z`
                        : topRounded(x, costTop, barWidth.value, costHeight)
                    : null,
            profit: hasProfit
                ? topRounded(x, total, barWidth.value, profitHeight)
                : null,
        };
    }),
);

const labelEvery = computed(() =>
    Math.max(
        1,
        Math.ceil(
            props.days.length / Math.max(2, Math.floor(plotWidth.value / 72)),
        ),
    ),
);

const isEmpty = computed(() =>
    props.days.every((day) => day.totals.charged.nano === '0'),
);

const current = computed(() =>
    active.value === null ? null : (props.days[active.value] ?? null),
);

const TOOLTIP = 240;

const tooltipLeft = computed(() => {
    if (active.value === null) {
        return 0;
    }

    const start = LEFT + active.value * band.value;
    const right = start + band.value + 8;

    return right + TOOLTIP <= width.value
        ? right
        : Math.max(0, start - 8 - TOOLTIP);
});

function summary(day: { date: string; totals: SpendTotals }): string {
    return t(
        ':date: charged :charged, Pixelforge cost :cost, profit :profit, :images.',
        {
            date: longDate.value.format(new Date(day.date)),
            charged: day.totals.charged.display,
            cost: day.totals.providerCost.display,
            profit: day.totals.profit.display,
            images: tc(':count image|:count images', day.totals.succeeded),
        },
    );
}

function move(step: number): void {
    const last = props.days.length - 1;
    active.value = Math.min(
        last,
        Math.max(0, (active.value ?? (step > 0 ? -1 : last + 1)) + step),
    );
}

function choose(): void {
    if (current.value) {
        emit('select', current.value.date);
    }
}
</script>

<template>
    <figure class="viz-root rounded-lg border border-gray-200 bg-white p-4">
        <figcaption class="flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">
                    {{ t('Charged per day') }}
                </p>
                <p class="text-xs text-gray-500">
                    {{
                        t(
                            'Each column is what users paid, split into what Pixelforge charged us and our profit. Select a day to zoom in.',
                        )
                    }}
                </p>
            </div>
            <ul class="flex gap-4 text-xs text-gray-600">
                <li class="flex items-center gap-1.5">
                    <span
                        class="swatch"
                        style="background: var(--series-1)"
                        aria-hidden="true"
                    />
                    {{ t('Pixelforge cost') }}
                </li>
                <li class="flex items-center gap-1.5">
                    <span
                        class="swatch"
                        style="background: var(--series-2)"
                        aria-hidden="true"
                    />
                    {{ t('Profit') }}
                </li>
            </ul>
        </figcaption>

        <div ref="root" class="relative mt-4">
            <p
                v-if="isEmpty"
                class="flex items-center justify-center rounded-md bg-gray-50 text-sm text-gray-500"
                :style="{ height: `${HEIGHT}px` }"
            >
                {{ t('Nothing was charged in this range.') }}
            </p>
            <template v-else>
                <svg
                    :width="width"
                    :height="HEIGHT"
                    :viewBox="`0 0 ${width} ${HEIGHT}`"
                    class="block max-w-full touch-pan-y outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    role="application"
                    tabindex="0"
                    :aria-label="
                        t(
                            'Charged per day. Use the left and right arrow keys to read each day, and Enter to zoom in.',
                        )
                    "
                    @keydown.left.prevent="move(-1)"
                    @keydown.right.prevent="move(1)"
                    @keydown.home.prevent="active = 0"
                    @keydown.end.prevent="active = days.length - 1"
                    @keydown.enter.prevent="choose"
                    @blur="active = null"
                    @pointerleave="active = null"
                >
                    <g aria-hidden="true">
                        <g v-for="tick in ticks" :key="tick">
                            <line
                                :x1="LEFT"
                                :x2="width - RIGHT"
                                :y1="y(tick)"
                                :y2="y(tick)"
                                :stroke="
                                    tick === 0 ? 'var(--axis)' : 'var(--grid)'
                                "
                                stroke-width="1"
                                shape-rendering="crispEdges"
                            />
                            <text
                                :x="LEFT - 8"
                                :y="y(tick)"
                                text-anchor="end"
                                dominant-baseline="middle"
                                class="tick"
                            >
                                {{ money.format(tick) }}
                            </text>
                        </g>

                        <template v-for="bar in bars" :key="bar.day.date">
                            <path
                                v-if="bar.cost"
                                :d="bar.cost"
                                fill="var(--series-1)"
                                :opacity="
                                    active === null || active === bar.index
                                        ? 1
                                        : 0.45
                                "
                            />
                            <path
                                v-if="bar.profit"
                                :d="bar.profit"
                                fill="var(--series-2)"
                                :opacity="
                                    active === null || active === bar.index
                                        ? 1
                                        : 0.45
                                "
                            />
                            <text
                                v-if="bar.index % labelEvery === 0"
                                :x="bar.hitX + band / 2"
                                :y="HEIGHT - 8"
                                text-anchor="middle"
                                class="tick"
                            >
                                {{ dateLabel.format(new Date(bar.day.date)) }}
                            </text>
                        </template>
                    </g>

                    <rect
                        v-for="bar in bars"
                        :key="`hit-${bar.day.date}`"
                        :x="bar.hitX"
                        :y="TOP"
                        :width="band"
                        :height="plotHeight"
                        fill="transparent"
                        class="cursor-pointer"
                        @pointerenter="active = bar.index"
                        @pointermove="active = bar.index"
                        @click="emit('select', bar.day.date)"
                    />
                </svg>

                <div
                    v-if="current"
                    class="pointer-events-none absolute top-0 z-10 w-60 rounded-md border border-gray-200 bg-white p-3 text-xs shadow-lg"
                    :style="{ left: `${tooltipLeft}px` }"
                >
                    <p class="font-medium text-gray-500">
                        {{ longDate.format(new Date(current.date)) }}
                    </p>
                    <p class="mt-1 text-base font-semibold text-gray-900">
                        {{ current.totals.charged.display }}
                    </p>
                    <dl class="mt-2 space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="flex items-center gap-1.5 text-gray-600">
                                <span
                                    class="key"
                                    style="background: var(--series-1)"
                                    aria-hidden="true"
                                />
                                {{ t('Pixelforge cost') }}
                            </dt>
                            <dd class="tabular-nums text-gray-900">
                                {{ current.totals.providerCost.display }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="flex items-center gap-1.5 text-gray-600">
                                <span
                                    class="key"
                                    style="background: var(--series-2)"
                                    aria-hidden="true"
                                />
                                {{ t('Profit') }}
                            </dt>
                            <dd class="tabular-nums text-gray-900">
                                {{ current.totals.profit.display }}
                            </dd>
                        </div>
                        <div
                            class="flex items-center justify-between gap-2 text-gray-600"
                        >
                            <dt>{{ t('Images') }}</dt>
                            <dd class="tabular-nums">
                                {{ current.totals.succeeded
                                }}<template v-if="current.totals.failed">
                                    ·
                                    {{
                                        tc(
                                            '+:count failed, free',
                                            current.totals.failed,
                                        )
                                    }}</template
                                >
                            </dd>
                        </div>
                    </dl>
                </div>
            </template>
            <p class="sr-only" aria-live="polite">
                {{ current ? summary(current) : '' }}
            </p>
        </div>
    </figure>
</template>

<style scoped>
.viz-root {
    --series-1: #2a78d6;
    --series-2: #eb6834;
    --grid: #e1e0d9;
    --axis: #c3c2b7;
    --muted: #6b6a65;
}
.tick {
    fill: var(--muted);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}
.swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
}
.key {
    display: inline-block;
    width: 10px;
    height: 2px;
    border-radius: 1px;
}
</style>
