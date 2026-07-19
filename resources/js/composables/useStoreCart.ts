import { ref, computed, watch } from 'vue';

const STORAGE_KEY = 'store_cart';

export interface CartItem {
    product_id: number;
    product_name: string;
    price: number;
    /** مبلغ التأمين للوحدة الواحدة (بدون ضريبة) */
    insurance_amount: number;
    quantity: number;
    duration: number; // rental days
    image: string | null;
}

function loadCart(): CartItem[] {
    if (typeof window === 'undefined') return [];
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const data = JSON.parse(raw);
        if (!Array.isArray(data)) return [];
        return data.map((i: CartItem) => ({
            ...i,
            duration: i.duration ?? 1,
            image: i.image ?? null,
            insurance_amount: Number(i.insurance_amount ?? 0),
        }));
    } catch {
        return [];
    }
}

function saveCart(items: CartItem[]) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

const cartItems = ref<CartItem[]>(loadCart());

export function useStoreCart() {
    const count = computed(() =>
        cartItems.value.reduce((sum, item) => sum + item.quantity, 0),
    );

    /** price × quantity × duration */
    const total = computed(() =>
        cartItems.value.reduce(
            (sum, item) => sum + item.price * item.quantity * item.duration,
            0,
        ),
    );

    /** insurance_amount × quantity (بدون مدة وبدون ضريبة) */
    const insuranceTotal = computed(() =>
        cartItems.value.reduce(
            (sum, item) => sum + (Number(item.insurance_amount) || 0) * item.quantity,
            0,
        ),
    );

    watch(cartItems, (val) => saveCart(val), { deep: true });

    function syncFromStorage() {
        cartItems.value = loadCart();
    }

    /** Add item — duration, image, quantity, insurance_amount */
    function addItem(
        productId: number,
        productName: string,
        price: number,
        duration = 1,
        image: string | null = null,
        quantity = 1,
        insuranceAmount = 0,
    ) {
        const insurance = Math.max(0, Number(insuranceAmount) || 0);
        const existing = cartItems.value.find((i) => i.product_id === productId);
        if (existing) {
            existing.quantity += quantity;
            if (duration !== 1) existing.duration = duration;
            if (image) existing.image = image;
            if (insurance > 0) existing.insurance_amount = insurance;
        } else {
            cartItems.value.push({
                product_id: productId,
                product_name: productName,
                price,
                insurance_amount: insurance,
                quantity,
                duration,
                image,
            });
        }
    }

    function setQuantity(productId: number, quantity: number) {
        const item = cartItems.value.find((i) => i.product_id === productId);
        if (!item) return;
        if (quantity <= 0) {
            cartItems.value = cartItems.value.filter((i) => i.product_id !== productId);
        } else {
            item.quantity = quantity;
        }
    }

    function setDuration(productId: number, duration: number) {
        const item = cartItems.value.find((i) => i.product_id === productId);
        if (!item) return;
        item.duration = Math.max(1, duration);
    }

    function removeItem(productId: number) {
        cartItems.value = cartItems.value.filter((i) => i.product_id !== productId);
    }

    function clearCart() {
        cartItems.value = [];
    }

    return {
        cartItems,
        count,
        total,
        insuranceTotal,
        syncFromStorage,
        addItem,
        setQuantity,
        setDuration,
        removeItem,
        clearCart,
    };
}
