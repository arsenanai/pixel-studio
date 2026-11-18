import { usePage } from '@inertiajs/vue3';
import type { App } from 'vue';

type Catalog = Record<string, string>;
type Replacements = Record<string, string | number>;

const catalogs = import.meta.glob<Catalog>('../../../lang/*.json', {
    eager: true,
    import: 'default',
});

function catalog(locale: string): Catalog {
    return catalogs[`../../../lang/${locale}.json`] ?? {};
}

function replace(text: string, replacements: Replacements): string {
    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce(
            (result, [name, value]) =>
                result.replaceAll(`:${name}`, String(value)),
            text,
        );
}

export function currentLocale(): string {
    return usePage().props.locale ?? 'en';
}

export function t(key: string, replacements: Replacements = {}): string {
    return replace(catalog(currentLocale())[key] ?? key, replacements);
}

export function tc(
    key: string,
    count: number,
    replacements: Replacements = {},
): string {
    const forms = (catalog(currentLocale())[key] ?? key).split('|');
    const form = forms[count === 1 ? 0 : Math.min(1, forms.length - 1)];

    return replace(form ?? key, {
        count: new Intl.NumberFormat(intlLocale()).format(count),
        ...replacements,
    });
}

export function intlLocale(): string {
    return currentLocale() === 'kk' ? 'kk-KZ' : 'en-US';
}

export const i18n = {
    install(app: App): void {
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$tc = tc;
    },
};
