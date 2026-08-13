import { computed, onMounted, ref } from 'vue';

export type AdminTheme = 'default' | 'salla';

const THEME_KEY = 'admin_theme';

function setCookie(name: string, value: string, days = 365) {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

export function applyAdminTheme(value: AdminTheme) {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    root.classList.toggle('theme-salla', value === 'salla');
    root.dataset.adminTheme = value;
}

export function getStoredAdminTheme(): AdminTheme {
    if (typeof window === 'undefined') {
        return 'default';
    }

    const stored = localStorage.getItem(THEME_KEY);
    return stored === 'salla' ? 'salla' : 'default';
}

export function initializeAdminTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    applyAdminTheme(getStoredAdminTheme());
}

const adminTheme = ref<AdminTheme>(getStoredAdminTheme());

export function useAdminTheme() {
    onMounted(() => {
        adminTheme.value = getStoredAdminTheme();
        applyAdminTheme(adminTheme.value);
    });

    function updateAdminTheme(value: AdminTheme) {
        adminTheme.value = value;
        localStorage.setItem(THEME_KEY, value);
        setCookie(THEME_KEY, value);
        applyAdminTheme(value);
    }

    return {
        adminTheme,
        isSalla: computed(() => adminTheme.value === 'salla'),
        updateAdminTheme,
    };
}
