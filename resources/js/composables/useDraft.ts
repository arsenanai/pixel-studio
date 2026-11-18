import { onMounted, ref, watch, type WatchSource } from 'vue';

const PREFIX = 'pixel-studio:draft:';

function storage(): Storage | null {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function forgetAllDrafts(): void {
    const store = storage();

    if (!store) {
        return;
    }

    try {
        Object.keys(store)
            .filter((key) => key.startsWith(PREFIX))
            .forEach((key) => store.removeItem(key));
    } catch {
        return;
    }
}

export function useDraft<T extends object>(options: {
    key: string;
    source: WatchSource<T>;
    isEmpty: (value: T) => boolean;
    restore: (value: Partial<T>) => void;
    leaveSavedDraftAlone?: boolean;
}) {
    const key = PREFIX + options.key;
    const restored = ref(false);
    const savedAt = ref<Date | null>(null);
    let timer: ReturnType<typeof setTimeout> | undefined;

    function write(value: T): void {
        const store = storage();

        try {
            if (options.isEmpty(value)) {
                store?.removeItem(key);
                savedAt.value = null;
            } else {
                store?.setItem(key, JSON.stringify(value));
                savedAt.value = new Date();
            }
        } catch {
            savedAt.value = null;
        }
    }

    function discard(): void {
        clearTimeout(timer);

        try {
            storage()?.removeItem(key);
        } catch {
            return;
        } finally {
            restored.value = false;
            savedAt.value = null;
        }
    }

    onMounted(() => {
        if (options.leaveSavedDraftAlone) {
            return;
        }

        try {
            const saved = storage()?.getItem(key);

            if (saved) {
                const parsed: unknown = JSON.parse(saved);

                if (parsed && typeof parsed === 'object') {
                    options.restore(parsed as Partial<T>);
                    restored.value = true;
                }
            }
        } catch {
            discard();
        }

        watch(
            options.source,
            (value) => {
                clearTimeout(timer);
                timer = setTimeout(() => write(value), 400);
            },
            { deep: true },
        );
    });

    return { restored, savedAt, discard };
}
