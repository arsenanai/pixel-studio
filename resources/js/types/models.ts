export interface Money {
    nano: string;
    display: string;
    cents: string;
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

export interface QuoteResponse extends PriceQuote {
    credits: { available: Money; sufficient: boolean };
}

export interface Option {
    value: string;
    label: string;
    hint?: string;
    width?: number;
    height?: number;
    level?: number;
}

export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export interface Generation {
    id: string;
    prompt: string;
    size: { value: string; label: string; dimensions: string };
    quality: { value: string; label: string };
    referenceImageCount: number;
    status: GenerationStatus;
    statusLabel: string;
    estimate: Estimate<Money>;
    charge: Money | null;
    image: { url: string | null; expiresAt: string | null } | null;
    error: string | null;
    createdAt: string;
    completedAt: string | null;
}

export interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PageLink[];
}

export interface Paginated<T> {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: PageLink[];
    };
}

export interface SpendTotals {
    succeeded: number;
    failed: number;
    charged: Money;
    providerCost: Money;
    profit: Money;
    margin: string | null;
}

export interface ReportUser {
    id: number;
    name: string;
    email: string;
}

export interface ReconciledDay {
    date: string;
    ourGenerations: number;
    theirGenerations: number;
    ourCost: Money;
    theirCost: Money;
    difference: Money;
    matches: boolean;
}

export type Reconciliation =
    | { available: false; days: []; mismatches: 0 }
    | {
          available: true;
          checkedDays: number;
          mismatches: number;
          days: ReconciledDay[];
      };
