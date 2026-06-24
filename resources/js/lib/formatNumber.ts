/** Western (English) digits for prices, amounts, and dates */
const LOCALE = 'en-US';

export function formatPrice(n: number): string {
    return Number(n).toLocaleString(LOCALE, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatAmount(n: number): string {
    return Number(n).toLocaleString(LOCALE, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
}

export function formatInteger(n: number): string {
    return Number(n).toLocaleString(LOCALE, {
        maximumFractionDigits: 0,
    });
}

export function formatCurrency(amount: number, currency = 'SAR'): string {
    return new Intl.NumberFormat(LOCALE, {
        style: 'currency',
        currency,
    }).format(Number(amount) || 0);
}

export function formatDate(date: string | Date): string {
    return new Date(date).toLocaleDateString(LOCALE, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(date: string | Date): string {
    return new Date(date).toLocaleString(LOCALE, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatTime(date: string | Date): string {
    return new Date(date).toLocaleTimeString(LOCALE, {
        hour: '2-digit',
        minute: '2-digit',
    });
}
