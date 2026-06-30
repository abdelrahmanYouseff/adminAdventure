import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useStoreAuth() {
    const page = usePage();

    const isLoggedIn = computed(() => Boolean(page.props.auth?.user));

    function requireLogin(onAuthed: () => void, openLogin: () => void) {
        if (isLoggedIn.value) {
            onAuthed();
            return;
        }
        openLogin();
    }

    return { isLoggedIn, requireLogin };
}
