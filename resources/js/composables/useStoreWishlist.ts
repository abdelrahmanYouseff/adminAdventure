import { ref } from 'vue';

const STORAGE_KEY = 'store_wishlist';

function loadWishlist(): number[] {
    if (typeof window === 'undefined') return [];
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const data = JSON.parse(raw);
        return Array.isArray(data) ? data.filter((id) => typeof id === 'number') : [];
    } catch {
        return [];
    }
}

function saveWishlist(ids: number[]) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
}

const wishlistIds = ref<number[]>(loadWishlist());

export function useStoreWishlist() {
    function syncFromStorage() {
        wishlistIds.value = loadWishlist();
    }

    function isInWishlist(productId: number): boolean {
        return wishlistIds.value.includes(productId);
    }

    function toggle(productId: number) {
        const idx = wishlistIds.value.indexOf(productId);
        if (idx >= 0) {
            wishlistIds.value.splice(idx, 1);
        } else {
            wishlistIds.value.push(productId);
        }
        saveWishlist(wishlistIds.value);
    }

    return { wishlistIds, syncFromStorage, isInWishlist, toggle };
}
