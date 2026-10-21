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

/** Keep a percentage discount inside 0–100, rounded to 2 decimals. */
export function clampDiscountPercent(value: number | string | null | undefined): number {
    const n = Number(value);
    if (!Number.isFinite(n)) {
        return 0;
    }

    return Math.min(100, Math.max(0, Math.round(n * 100) / 100));
}

export function formatDiscountPercent(value: number | string | null | undefined): string {
    return clampDiscountPercent(value).toFixed(2).replace(/\.?0+$/, '');
}

/** Percentage of a cent subtotal, rounded to the nearest cent. */
export function percentDiscountCents(subtotalCents: number, percent: number | string | null | undefined): number {
    const basisPoints = Math.round(clampDiscountPercent(percent) * 100);

    return Math.round((Math.round(subtotalCents) * basisPoints) / 10000);
}

export type ItemAmountField = 'quantity' | 'unit_price' | 'discount_amount';

/**
 * Number-input drafts stay as typed ("", "1.", "10").
 * Empty and unfinished values count as 0 for totals only.
 */
export function parseDraftNumber(value: unknown): number {
    if (typeof value === 'string' && value.trim() === '') {
        return 0;
    }

    const n = Number(value);

    return Number.isFinite(n) ? n : 0;
}

export function itemAmountDrafts(
    item: { quantity: unknown; unit_price: unknown; discount_amount: unknown },
    editing?: { field: ItemAmountField; value: string },
): { quantity: number; unitPrice: number; discount: number } {
    const read = (field: ItemAmountField, current: unknown): unknown =>
        editing?.field === field ? editing.value : current;

    const quantity = Math.max(0, parseDraftNumber(read('quantity', item.quantity)));
    const unitPrice = Math.max(0, parseDraftNumber(read('unit_price', item.unit_price)));
    const discount = Math.min(unitPrice, Math.max(0, parseDraftNumber(read('discount_amount', item.discount_amount))));

    return { quantity, unitPrice, discount };
}
