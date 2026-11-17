import type {
    Estimate,
    Money,
    PriceQuote,
    QuoteLine,
    QuoteResponse,
} from '@/types/models';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

export function isMoney(value: unknown): value is Money {
    return (
        isRecord(value) &&
        typeof value.nano === 'string' &&
        /^-?\d+$/.test(value.nano) &&
        typeof value.display === 'string'
    );
}

function isEstimate(value: unknown): value is Estimate<Money> {
    return (
        isRecord(value) &&
        isMoney(value.min) &&
        isMoney(value.typical) &&
        isMoney(value.max)
    );
}

function isQuoteLine(value: unknown): value is QuoteLine {
    return (
        isRecord(value) &&
        typeof value.type === 'string' &&
        typeof value.label === 'string' &&
        isRecord(value.tokens) &&
        typeof value.tokens.min === 'number' &&
        typeof value.tokens.max === 'number' &&
        isMoney(value.ratePerMillion) &&
        isRecord(value.cost) &&
        isMoney(value.cost.min) &&
        isMoney(value.cost.max)
    );
}

export function isPriceQuote(value: unknown): value is PriceQuote {
    return (
        isRecord(value) &&
        Array.isArray(value.lines) &&
        value.lines.every(isQuoteLine) &&
        isEstimate(value.providerCost) &&
        isEstimate(value.markup) &&
        isEstimate(value.charge) &&
        typeof value.markupPercent === 'number'
    );
}

export function isQuoteResponse(value: unknown): value is QuoteResponse {
    return (
        isPriceQuote(value) &&
        isRecord(value) &&
        isRecord(value.credits) &&
        isMoney(value.credits.available) &&
        typeof value.credits.sufficient === 'boolean'
    );
}
