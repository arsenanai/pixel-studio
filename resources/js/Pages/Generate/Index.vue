<script setup lang="ts">
import CostPanel from '@/Components/CostPanel.vue';
import InputError from '@/Components/InputError.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import { useDraft } from '@/composables/useDraft';
import { useQuote } from '@/composables/useQuote';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatTokens } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { Option } from '@/types/models';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    sizes: Option[];
    qualities: Option[];
    limits: { promptMaxCharacters: number; referenceImagesMax: number };
    prefill: {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
    } | null;
}>();

const DEFAULTS = { size: '1024x1024', quality: 'medium' };

const form = useForm({
    prompt: props.prefill?.prompt ?? '',
    size: props.prefill?.size ?? DEFAULTS.size,
    quality: props.prefill?.quality ?? DEFAULTS.quality,
    reference_images: [...(props.prefill?.reference_images ?? [])],
});

type Draft = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};

const draft = useDraft<Draft>({
    key: String(usePage().props.auth.user.id),
    leaveSavedDraftAlone: props.prefill !== null,
    source: () => ({
        prompt: form.prompt,
        size: form.size,
        quality: form.quality,
        reference_images: [...form.reference_images],
    }),
    isEmpty: (value) =>
        value.prompt.trim() === '' &&
        value.reference_images.every((url) => url.trim() === '') &&
        value.size === DEFAULTS.size &&
        value.quality === DEFAULTS.quality,
    restore: (saved) => {
        if (typeof saved.prompt === 'string') {
            form.prompt = saved.prompt;
        }
        if (props.sizes.some((option) => option.value === saved.size)) {
            form.size = saved.size as string;
        }
        if (props.qualities.some((option) => option.value === saved.quality)) {
            form.quality = saved.quality as string;
        }
        if (Array.isArray(saved.reference_images)) {
            form.reference_images = saved.reference_images
                .filter((url): url is string => typeof url === 'string')
                .slice(0, props.limits.referenceImagesMax);
        }
    },
});

function discardDraft(): void {
    draft.discard();
    form.reset();
}

const characters = computed(() => [...form.prompt].length);
const overLimit = computed(
    () => characters.value > props.limits.promptMaxCharacters,
);

function isHttpUrl(value: string): boolean {
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

const { quote, state, error } = useQuote(() => ({
    prompt: form.prompt,
    size: form.size,
    quality: form.quality,
    reference_images: [
        ...new Set(
            form.reference_images.map((url) => url.trim()).filter(isHttpUrl),
        ),
    ],
}));

const canAfford = computed(() => quote.value?.credits.sufficient ?? true);

const canSubmit = computed(
    () =>
        !form.processing &&
        state.value === 'ready' &&
        canAfford.value &&
        form.prompt.trim() !== '' &&
        !overLimit.value,
);

const submitLabel = computed(() => {
    if (form.processing) return t('Sending...');
    if (state.value === 'loading') return t('Updating price...');
    return t('Generate image');
});

const referenceInputs = ref<HTMLInputElement[]>([]);

async function addReference(): Promise<void> {
    form.reference_images.push('');
    await nextTick();
    referenceInputs.value[form.reference_images.length - 1]?.focus();
}

function removeReference(index: number): void {
    form.reference_images.splice(index, 1);
}

function fieldError(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function submit(): void {
    form.reference_images = form.reference_images
        .map((url) => url.trim())
        .filter((url) => url !== '');

    form.post(route('generations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (props.prefill === null) {
                draft.discard();
            }
            form.reset('prompt', 'reference_images');
        },
        onError: async () => {
            await nextTick();
            document
                .querySelector<HTMLElement>('[aria-invalid="true"]')
                ?.focus();
        },
    });
}
</script>

<template>
    <Head :title="t('Generate')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
            <header class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">
                    {{ t('New image') }}
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{
                        t(
                            "You'll see the price range before you submit and pay the exact cost once it's done.",
                        )
                    }}
                </p>
            </header>

            <div
                v-if="prefill"
                class="mt-6 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950"
                role="status"
            >
                {{
                    t(
                        'Copied from an earlier image. Nothing is sent until you press Generate.',
                    )
                }}
            </div>

            <div
                v-if="draft.restored.value"
                class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-950"
                role="status"
            >
                <p>{{ t('We restored the draft you were working on.') }}</p>
                <button
                    type="button"
                    class="font-medium text-indigo-700 underline underline-offset-2 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @click="discardDraft"
                >
                    {{ t('Start over') }}
                </button>
            </div>

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <form class="space-y-8" novalidate @submit.prevent="submit">
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <label
                                for="prompt"
                                class="text-sm font-medium text-gray-900"
                            >
                                {{ t('Prompt') }}
                            </label>
                            <span class="flex items-baseline gap-3 text-xs">
                                <span
                                    v-if="draft.savedAt.value"
                                    class="text-gray-500"
                                    aria-live="polite"
                                >
                                    {{ t('Draft saved') }}
                                </span>
                                <span
                                    id="prompt-count"
                                    class="tabular-nums"
                                    :class="
                                        overLimit
                                            ? 'font-medium text-red-700'
                                            : 'text-gray-500'
                                    "
                                >
                                    {{ formatTokens(characters) }} /
                                    {{
                                        formatTokens(limits.promptMaxCharacters)
                                    }}
                                </span>
                            </span>
                        </div>
                        <textarea
                            id="prompt"
                            v-model="form.prompt"
                            rows="5"
                            autofocus
                            :placeholder="
                                t(
                                    'A lighthouse on a cliff at dawn, oil painting',
                                )
                            "
                            class="mt-2 block w-full resize-y rounded-md border-gray-300 text-sm leading-6 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :aria-invalid="
                                form.errors.prompt || overLimit
                                    ? 'true'
                                    : undefined
                            "
                            aria-describedby="prompt-count prompt-error"
                        />
                        <InputError
                            id="prompt-error"
                            class="mt-2"
                            :message="form.errors.prompt"
                        />
                    </div>

                    <SegmentedControl
                        v-model="form.size"
                        name="size"
                        :legend="t('Size')"
                        :options="sizes"
                    />
                    <InputError :message="form.errors.size" />

                    <SegmentedControl
                        v-model="form.quality"
                        name="quality"
                        :legend="t('Quality')"
                        :options="qualities"
                    />
                    <InputError :message="form.errors.quality" />

                    <fieldset>
                        <legend class="text-sm font-medium text-gray-900">
                            {{ t('Reference images') }}
                            <span class="font-normal text-gray-500">{{
                                t('(optional)')
                            }}</span>
                        </legend>
                        <p class="mt-1 text-xs text-gray-500">
                            {{
                                t(
                                    'Image URLs to guide the style. Pixelforge bills each as 765 input tokens, up to :max.',
                                    { max: limits.referenceImagesMax },
                                )
                            }}
                        </p>

                        <ul
                            v-if="form.reference_images.length"
                            class="mt-3 space-y-2"
                        >
                            <li
                                v-for="(_, index) in form.reference_images"
                                :key="index"
                            >
                                <div class="flex gap-2">
                                    <label
                                        :for="`reference-${index}`"
                                        class="sr-only"
                                    >
                                        {{
                                            t('Reference image :number URL', {
                                                number: index + 1,
                                            })
                                        }}
                                    </label>
                                    <input
                                        :id="`reference-${index}`"
                                        ref="referenceInputs"
                                        v-model="form.reference_images[index]"
                                        type="url"
                                        inputmode="url"
                                        placeholder="https://example.com/style.png"
                                        class="block min-h-11 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        :aria-invalid="
                                            fieldError(
                                                `reference_images.${index}`,
                                            )
                                                ? 'true'
                                                : undefined
                                        "
                                        :aria-describedby="`reference-${index}-error`"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="removeReference(index)"
                                    >
                                        <span class="sr-only">{{
                                            t(
                                                'Remove reference image :number',
                                                { number: index + 1 },
                                            )
                                        }}</span>
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
                                <InputError
                                    :id="`reference-${index}-error`"
                                    class="mt-1"
                                    :message="
                                        fieldError(`reference_images.${index}`)
                                    "
                                />
                            </li>
                        </ul>
                        <InputError
                            class="mt-2"
                            :message="form.errors.reference_images"
                        />

                        <button
                            v-if="
                                form.reference_images.length <
                                limits.referenceImagesMax
                            "
                            type="button"
                            class="mt-3 inline-flex min-h-11 items-center rounded-md px-1 text-sm font-medium text-indigo-700 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @click="addReference"
                        >
                            {{ t('+ Add a reference image') }}
                        </button>
                    </fieldset>

                    <div class="border-t border-gray-200 pt-6">
                        <p
                            v-if="fieldError('generation')"
                            role="alert"
                            class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            {{ fieldError('generation') }}
                        </p>
                        <div
                            v-if="
                                (quote && state === 'ready' && !canAfford) ||
                                fieldError('credits')
                            "
                            role="alert"
                            class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            <p v-if="fieldError('credits')">
                                {{ fieldError('credits') }}
                            </p>
                            <p v-else-if="quote">
                                {{
                                    t(
                                        'This image could cost up to :max, and you have :available available.',
                                        {
                                            max: quote.charge.max.display,
                                            available:
                                                quote.credits.available.display,
                                        },
                                    )
                                }}
                            </p>
                            <Link
                                :href="route('credits')"
                                class="mt-1 inline-block font-medium underline hover:no-underline"
                            >
                                {{ t('Buy credits') }}
                            </Link>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-indigo-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-indigo-300 sm:w-auto"
                            :disabled="!canSubmit"
                        >
                            {{ submitLabel }}
                        </button>
                        <p
                            v-if="quote && state === 'ready'"
                            class="mt-2 text-xs text-gray-500"
                        >
                            {{
                                t(
                                    "You'll pay between :min and :max, or nothing if it fails.",
                                    {
                                        min: quote.charge.min.display,
                                        max: quote.charge.max.display,
                                    },
                                )
                            }}
                        </p>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <CostPanel :quote="quote" :state="state" :error="error" />
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
