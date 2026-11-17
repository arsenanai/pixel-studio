import { isQuoteResponse } from '@/lib/guards';
import type { QuoteResponse } from '@/types/models';
import axios from 'axios';
import { onBeforeUnmount, ref, watch, type Ref } from 'vue';

export interface QuoteInput {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
}

export type QuoteState = 'loading' | 'ready' | 'error';

const DEBOUNCE_MS = 300;

export function useQuote(input: () => QuoteInput): {
    quote: Ref<QuoteResponse | null>;
    state: Ref<QuoteState>;
    error: Ref<string | null>;
} {
    const quote = ref<QuoteResponse | null>(null);
    const state = ref<QuoteState>('loading');
    const error = ref<string | null>(null);

    let timer: ReturnType<typeof setTimeout> | undefined;
    let controller: AbortController | undefined;

    async function load(payload: QuoteInput): Promise<void> {
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await axios.post<unknown>(
                route('quote'),
                payload,
                {
                    signal: controller.signal,
                },
            );

            if (!isQuoteResponse(response.data)) {
                throw new Error('Unexpected quote response');
            }

            quote.value = response.data;
            state.value = 'ready';
            error.value = null;
        } catch (failure: unknown) {
            if (axios.isCancel(failure)) {
                return;
            }

            state.value = 'error';
            error.value = messageFor(failure);
        }
    }

    watch(
        input,
        (payload) => {
            state.value = 'loading';
            clearTimeout(timer);
            timer = setTimeout(() => void load(payload), DEBOUNCE_MS);
        },
        { deep: true, immediate: true },
    );

    onBeforeUnmount(() => {
        clearTimeout(timer);
        controller?.abort();
    });

    return { quote, state, error };
}

function messageFor(failure: unknown): string {
    if (axios.isAxiosError(failure)) {
        const status = failure.response?.status;

        if (status === 422) {
            return 'Fix the highlighted fields to see a price.';
        }

        if (status === 429) {
            return 'Too many price checks. Pause for a moment.';
        }

        const data: unknown = failure.response?.data;

        if (
            typeof data === 'object' &&
            data !== null &&
            'message' in data &&
            typeof data.message === 'string'
        ) {
            return data.message;
        }
    }

    return 'Could not load a price. Check your connection and try again.';
}
