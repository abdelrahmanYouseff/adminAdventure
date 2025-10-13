<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  value?: string;
  class?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  'update:value': [value: string];
}>();

const updateValue = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  emit('update:value', target.value);
};
</script>

<template>
  <select
    :value="value"
    @change="updateValue"
    :class="[
      'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    ]"
  >
    <slot />
  </select>
</template>
