<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';
import { formatCurrency } from '@/lib/formatNumber';

export interface SearchableProduct {
    id: number;
    product_name: string;
    description: string;
    price: number;
}

const props = defineProps<{
    products: SearchableProduct[];
    modelValue: number | null;
    inputId?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const rootRef = ref<HTMLElement | null>(null);
const query = ref('');
const open = ref(false);
const activeIndex = ref(0);

function normalizeArabic(text: string): string {
    return text
        .toLowerCase()
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/\s+/g, ' ')
        .trim();
}

function productScore(product: SearchableProduct, normalizedQuery: string): number {
    if (!normalizedQuery) {
        return 1;
    }

    const name = normalizeArabic(product.product_name);
    const description = normalizeArabic(product.description || '');
    let score = 0;

    if (name === normalizedQuery) {
        score += 200;
    }
    if (name.startsWith(normalizedQuery)) {
        score += 120;
    }
    if (name.includes(normalizedQuery)) {
        score += 70;
    }

    for (const word of name.split(' ')) {
        if (word.startsWith(normalizedQuery)) {
            score += 40;
        }
    }

    if (description.includes(normalizedQuery)) {
        score += 25;
    }

    return score;
}

const filteredProducts = computed(() => {
    const normalizedQuery = normalizeArabic(query.value);

    return [...props.products]
        .map((product) => ({
            product,
            score: productScore(product, normalizedQuery),
        }))
        .filter(({ score }) => score > 0)
        .sort((a, b) => {
            if (b.score !== a.score) {
                return b.score - a.score;
            }

            return a.product.product_name.localeCompare(b.product.product_name, 'ar');
        })
        .slice(0, 10)
        .map(({ product }) => product);
});

const completionSuffix = computed(() => {
    const current = query.value;
    const trimmed = current.trim();

    if (!trimmed || !open.value || filteredProducts.value.length === 0) {
        return '';
    }

    const first = filteredProducts.value[0];
    const name = first.product_name;

    if (
        normalizeArabic(name).startsWith(normalizeArabic(trimmed))
        && name.length > trimmed.length
    ) {
        return name.slice(trimmed.length);
    }

    return '';
});

function syncQueryFromModel() {
    if (props.modelValue == null) {
        return;
    }

    const product = props.products.find((item) => item.id === props.modelValue);
    if (product) {
        query.value = product.product_name;
    }
}

function selectProduct(product: SearchableProduct) {
    emit('update:modelValue', product.id);
    query.value = product.product_name;
    open.value = false;
}

function clearSelection() {
    emit('update:modelValue', null);
}

function onInputFocus() {
    open.value = true;
    activeIndex.value = 0;
}

function onInput() {
    const selected = props.modelValue
        ? props.products.find((item) => item.id === props.modelValue)
        : null;

    if (selected && selected.product_name !== query.value) {
        clearSelection();
    }

    open.value = true;
    activeIndex.value = 0;
}

function acceptCompletion() {
    if (completionSuffix.value) {
        query.value += completionSuffix.value;
        const match = filteredProducts.value[0];
        if (match) {
            selectProduct(match);
        }
        return true;
    }

    if (filteredProducts.value[activeIndex.value]) {
        selectProduct(filteredProducts.value[activeIndex.value]);
        return true;
    }

    return false;
}

function onKeydown(event: KeyboardEvent) {
    if (!open.value && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        open.value = true;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, filteredProducts.value.length - 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }

    if (event.key === 'Tab' && query.value.trim() && filteredProducts.value.length > 0) {
        event.preventDefault();
        acceptCompletion();
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        if (filteredProducts.value[activeIndex.value]) {
            selectProduct(filteredProducts.value[activeIndex.value]);
        }
        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
}

watch(
    () => props.modelValue,
    (value) => {
        if (value == null) {
            query.value = '';
            return;
        }
        syncQueryFromModel();
    },
    { immediate: true },
);

watch(filteredProducts, () => {
    activeIndex.value = 0;
});

onClickOutside(rootRef, () => {
    open.value = false;
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                :id="inputId"
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="ابحث باسم المنتج..."
                class="h-11 rounded-xl ps-10"
                @focus="onInputFocus"
                @input="onInput"
                @keydown="onKeydown"
            />
            <div
                v-if="open && completionSuffix && query.trim()"
                class="pointer-events-none absolute inset-y-0 flex items-center overflow-hidden ps-10 text-sm"
                aria-hidden="true"
            >
                <span class="invisible whitespace-pre">{{ query }}</span>
                <span class="whitespace-pre text-muted-foreground/50">{{ completionSuffix }}</span>
            </div>
        </div>

        <p v-if="query.trim() && open" class="mt-1.5 text-[11px] text-muted-foreground">
            Tab لإكمال الاسم · Enter للاختيار
        </p>

        <div
            v-if="open && query.trim()"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-border bg-popover p-1 shadow-lg"
        >
            <button
                v-for="(product, index) in filteredProducts"
                :key="product.id"
                type="button"
                class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2.5 text-start text-sm transition-colors hover:bg-accent"
                :class="index === activeIndex ? 'bg-accent' : ''"
                @mousedown.prevent
                @click="selectProduct(product)"
            >
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-foreground">{{ product.product_name }}</p>
                    <p v-if="product.description" class="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
                        {{ product.description }}
                    </p>
                </div>
                <span class="shrink-0 text-xs font-semibold tabular-nums text-primary" dir="ltr">
                    {{ formatCurrency(product.price) }}
                </span>
            </button>

            <p
                v-if="filteredProducts.length === 0"
                class="px-3 py-4 text-center text-sm text-muted-foreground"
            >
                لا توجد منتجات مطابقة لـ «{{ query }}»
            </p>
        </div>

        <div
            v-else-if="open && !query.trim() && products.length > 0"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-border bg-popover p-1 shadow-lg"
        >
            <p class="px-3 py-2 text-xs text-muted-foreground">ابدأ بالكتابة لعرض المنتجات المتاحة</p>
            <button
                v-for="(product, index) in products.slice(0, 8)"
                :key="product.id"
                type="button"
                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-start text-sm transition-colors hover:bg-accent"
                :class="index === activeIndex ? 'bg-accent' : ''"
                @mousedown.prevent
                @click="selectProduct(product)"
            >
                <span class="truncate font-medium">{{ product.product_name }}</span>
                <span class="shrink-0 text-xs tabular-nums text-muted-foreground" dir="ltr">
                    {{ formatCurrency(product.price) }}
                </span>
            </button>
        </div>
    </div>
</template>
