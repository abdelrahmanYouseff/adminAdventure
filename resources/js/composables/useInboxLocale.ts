import { computed, onMounted, ref } from 'vue';

export type InboxLocale = 'en' | 'ar';

const locale = ref<InboxLocale>('ar');

function readStored(): InboxLocale {
    if (typeof window === 'undefined') {
        return 'ar';
    }

    return window.localStorage.getItem('inbox_locale') === 'en' ? 'en' : 'ar';
}

export function useInboxLocale() {
    onMounted(() => {
        locale.value = readStored();
    });

    const dir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));

    function setLocale(value: InboxLocale) {
        locale.value = value;
        if (typeof window !== 'undefined') {
            window.localStorage.setItem('inbox_locale', value);
        }
    }

    return { locale, dir, setLocale };
}
