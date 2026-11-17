const integer = new Intl.NumberFormat('en-US');

export function formatTokens(count: number): string {
    return integer.format(count);
}

export function formatTokenRange(min: number, max: number): string {
    return min === max
        ? formatTokens(min)
        : `${formatTokens(min)}–${formatTokens(max)}`;
}

export function formatMoneyRange(min: string, max: string): string {
    return min === max ? min : `${min}–${max}`;
}
