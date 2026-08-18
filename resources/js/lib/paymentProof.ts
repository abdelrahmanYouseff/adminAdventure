export const PAYMENT_PROOF_ACCEPT =
    'image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf';

export function isPdfFile(file: File | undefined | null): boolean {
    if (!file) {
        return false;
    }

    return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
}

export function isPdfUrl(url: string): boolean {
    return /\.pdf(?:$|[?#])/i.test(url);
}

export function paymentProofSelectedLabel(count: number): string {
    return count > 0 ? `${count} ملف محدد — اضغط لإضافة المزيد` : 'اختر صورة أو ملف PDF';
}
