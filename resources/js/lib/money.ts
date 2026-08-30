/** Money helpers — work in integer cents to avoid float display bugs like 1400.01. */

export function toCents(value: number | string | null | undefined): number {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return 0;
    }

    return Math.round((n + Number.EPSILON) * 100);
}

export function fromCents(cents: number): number {
    return Number((Math.round(cents) / 100).toFixed(2));
}

export function roundMoney(value: number | string | null | undefined): number {
    return fromCents(toCents(value));
}

/** 15% VAT in cents from a subtotal expressed in cents. */
export function vatCentsFromSubtotal(subtotalCents: number): number {
    return Math.round((Math.round(subtotalCents) * 15) / 100);
}
