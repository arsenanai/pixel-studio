/**
 * Money always arrives from the server as exact nanodollars in a string plus a
 * display string. The browser renders `display` and never does arithmetic on it.
 */
export interface Money {
    nano: string;
    display: string;
}

export interface Range<T> {
    min: T;
    max: T;
}

export interface Estimate<T> extends Range<T> {
    typical: T;
}

export type TokenType =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';

export interface QuoteLine {
    type: TokenType;
    label: string;
    tokens: Range<number>;
    ratePerMillion: Money;
    cost: Range<Money>;
}

export interface PriceQuote {
    lines: QuoteLine[];
    providerCost: Estimate<Money>;
    markup: Estimate<Money>;
    charge: Estimate<Money>;
    markupPercent: number;
}

export interface Option {
    value: string;
    label: string;
    hint?: string;
}
