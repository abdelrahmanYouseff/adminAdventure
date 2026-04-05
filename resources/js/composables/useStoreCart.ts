import { ref, computed, watch } from 'vue';

const STORAGE_KEY = 'store_cart';

export interface CartItem {
    product_id: number;
    product_name: string;
    price: number;
    quantity: number;
    duration: number;  // rental days
    image: string | null;
}

function loadCart(): CartItem[] {
    if (typeof window === 'undefined') return [];
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const data = JSON.parse(raw);
        if (!Array.isArray(data)) return [];
        // ensure duration + image exist on old entries
        return data.map((i: CartItem) => ({ ...i, duration: i.duration ?? 1, image: i.image ?? null }));
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

    watch(cartItems, (val) => saveCart(val), { deep: true });

    function syncFromStorage() {
        cartItems.value = loadCart();
    }

    /** Add item — pass duration (rental days) as 4th arg, image as 5th, quantity as 6th */
    function addItem(
        productId: number,
        productName: string,
        price: number,
        duration = 1,
        image: string | null = null,
        quantity = 1,
    ) {
        const existing = cartItems.value.find((i) => i.product_id === productId);
        if (existing) {
            existing.quantity += quantity;
            if (duration !== 1) existing.duration = duration;
            if (image) existing.image = image;
        } else {
            cartItems.value.push({ product_id: productId, product_name: productName, price, quantity, duration, image });
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
        syncFromStorage,
        addItem,
        setQuantity,
        setDuration,
        removeItem,
        clearCart,
    };
}
