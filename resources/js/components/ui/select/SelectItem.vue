<script setup lang="ts">
interface Props {
  value: string;
  class?: string;
}

const props = defineProps<Props>();
</script>

<template>
  <option
    :value="value"
    :class="[
      'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class,
    ]"
  >
    <slot />
  </option>
</template>
