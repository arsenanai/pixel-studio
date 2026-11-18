import { currentLocale, intlLocale } from '@/lib/i18n';

const KK_MONTHS = [
    'қаңтар',
    'ақпан',
    'наурыз',
    'сәуір',
    'мамыр',
    'маусым',
    'шілде',
    'тамыз',
    'қыркүйек',
    'қазан',
    'қараша',
    'желтоқсан',
];

const KK_WEEKDAYS = [
    'жексенбі',
    'дүйсенбі',
    'сейсенбі',
    'сәрсенбі',
    'бейсенбі',
    'жұма',
    'сенбі',
];

function kkShortMonth(month: number): string {
    return (KK_MONTHS[month] ?? '').slice(0, 3);
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

export function formatTokens(count: number): string {
    return new Intl.NumberFormat(intlLocale()).format(count);
}

export function formatTokenRange(min: number, max: number): string {
    return min === max
        ? formatTokens(min)
        : `${formatTokens(min)}–${formatTokens(max)}`;
}

export function formatMoneyRange(min: string, max: string): string {
    return min === max ? min : `${min}–${max}`;
}

export function formatDateTime(iso: string): string {
    const date = new Date(iso);

    if (currentLocale() === 'kk') {
        return `${date.getDate()} ${kkShortMonth(date.getMonth())}, ${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

export function formatDay(day: string, style: 'short' | 'long'): string {
    const date = new Date(`${day}T00:00:00Z`);

    if (currentLocale() === 'kk') {
        const month = date.getUTCMonth();

        return style === 'short'
            ? `${date.getUTCDate()} ${kkShortMonth(month)}`
            : `${date.getUTCDate()} ${KK_MONTHS[month]} ${date.getUTCFullYear()} ж., ${KK_WEEKDAYS[date.getUTCDay()]}`;
    }

    return new Intl.DateTimeFormat(
        'en-US',
        style === 'short'
            ? { month: 'short', day: 'numeric', timeZone: 'UTC' }
            : {
                  weekday: 'short',
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
                  timeZone: 'UTC',
              },
    ).format(date);
}
