<script setup lang="ts">
interface Props {
  placeholder?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <span class="block truncate">
    <slot>{{ placeholder }}</slot>
  </span>
</template>
