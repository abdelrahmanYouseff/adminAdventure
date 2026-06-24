<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { MapPin, Navigation, Loader2, Map } from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const mapOpen = ref(false);
const locating = ref(false);
const mapReady = ref(false);
const locationError = ref('');

let mapInstance: L.Map | null = null;
let markerInstance: L.Marker | null = null;

const DEFAULT_CENTER: [number, number] = [24.7136, 46.6753];

const markerIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

async function reverseGeocode(lat: number, lng: number): Promise<string> {
    const url = new URL('https://nominatim.openstreetmap.org/reverse');
    url.searchParams.set('lat', String(lat));
    url.searchParams.set('lon', String(lng));
    url.searchParams.set('format', 'json');
    url.searchParams.set('accept-language', 'ar');

    const res = await fetch(url.toString(), {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error('geocode_failed');
    const data = await res.json();
    return (data.display_name as string) || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}

function setAddressFromCoords(lat: number, lng: number) {
    reverseGeocode(lat, lng)
        .then((address) => emit('update:modelValue', address))
        .catch(() => emit('update:modelValue', `${lat.toFixed(5)}, ${lng.toFixed(5)}`));
}

function updateMarker(lat: number, lng: number) {
    if (!mapInstance) return;
    if (markerInstance) {
        markerInstance.setLatLng([lat, lng]);
    } else {
        markerInstance = L.marker([lat, lng], { icon: markerIcon, draggable: true }).addTo(mapInstance);
        markerInstance.on('dragend', () => {
            const pos = markerInstance!.getLatLng();
            setAddressFromCoords(pos.lat, pos.lng);
        });
    }
    mapInstance.setView([lat, lng], Math.max(mapInstance.getZoom(), 15));
}

function initMap() {
    if (!mapContainer.value || mapInstance) return;

    mapReady.value = false;
    mapInstance = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: true,
    }).setView(DEFAULT_CENTER, 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(mapInstance);

    mapInstance.on('click', (e) => {
        updateMarker(e.latlng.lat, e.latlng.lng);
        setAddressFromCoords(e.latlng.lat, e.latlng.lng);
    });

    setTimeout(() => mapInstance?.invalidateSize(), 100);
    mapReady.value = true;
}

function destroyMap() {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
        markerInstance = null;
    }
}

async function toggleMap() {
    mapOpen.value = !mapOpen.value;
    if (mapOpen.value) {
        await initMap();
    } else {
        destroyMap();
    }
}

function locateMe() {
    locationError.value = '';
    if (!navigator.geolocation) {
        locationError.value = 'المتصفح لا يدعم تحديد الموقع.';
        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            const { latitude, longitude } = pos.coords;
            if (!mapOpen.value) {
                mapOpen.value = true;
                initMap();
            }
            updateMarker(latitude, longitude);
            setAddressFromCoords(latitude, longitude);
            locating.value = false;
        },
        () => {
            locating.value = false;
            locationError.value = 'تعذّر الوصول لموقعك. فعّل خدمة الموقع أو اختر من الخريطة.';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
}

watch(
    () => props.modelValue,
    () => {
        locationError.value = '';
    },
);

onBeforeUnmount(() => destroyMap());
</script>

<template>
    <div class="space-y-3">
        <div class="relative">
            <textarea
                :value="modelValue"
                rows="3"
                class="w-full rounded-xl border border-neutral-200 bg-[#f4f6f8] py-3 pl-14 pr-4 text-sm leading-relaxed text-neutral-900 placeholder:text-neutral-400 transition focus:border-[#3b89d2] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#3b89d2]/20"
                placeholder="العنوان الكامل للتوصيل أو مكان الفعالية"
                @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
            />
            <button
                type="button"
                class="absolute left-3 top-3 flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#3b89d2] shadow-sm ring-1 ring-neutral-200 transition hover:bg-[#3b89d2] hover:text-white hover:ring-[#3b89d2] disabled:opacity-60"
                :disabled="locating"
                title="حدد موقعي تلقائياً"
                @click="locateMe"
            >
                <Loader2 v-if="locating" class="h-5 w-5 animate-spin" />
                <Navigation v-else class="h-5 w-5" />
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 transition hover:border-[#3b89d2]/40 hover:text-[#3b89d2]"
                @click="toggleMap"
            >
                <Map class="h-4 w-4 shrink-0" />
                {{ mapOpen ? 'إخفاء الخريطة' : 'اختر من الخريطة' }}
            </button>
            <span class="inline-flex items-center gap-1.5 text-xs text-neutral-500">
                <MapPin class="h-3.5 w-3.5 text-[#3b89d2]" />
                اضغط أيقونة الموقع أو حدّد النقطة على الخريطة
            </span>
        </div>

        <p v-if="locationError" class="text-sm text-red-600">{{ locationError }}</p>

        <div
            v-show="mapOpen"
            class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm"
        >
            <div ref="mapContainer" class="h-56 w-full sm:h-64" />
            <p v-if="!mapReady && mapOpen" class="border-t border-neutral-100 px-4 py-3 text-center text-xs text-neutral-500">
                جاري تحميل الخريطة...
            </p>
            <p v-else class="border-t border-neutral-100 px-4 py-2.5 text-center text-[11px] text-neutral-400">
                انقر على الخريطة أو اسحب الدبوس لتحديد العنوان
            </p>
        </div>
    </div>
</template>
