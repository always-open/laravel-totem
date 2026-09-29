<template>
  <component :is="results" v-if="results" />
  <slot v-else />
</template>

<script setup>
import { computed } from 'vue';
import { regions } from '../search';

const props = defineProps({
    name: { type: String, required: true },
});

// The fetched markup is the same Blade output Vue compiles on page load, so
// compile it as a template to mount the task rows and other components in it.
const results = computed(() => {
    const html = regions[props.name];
    return html === undefined ? null : { template: html };
});
</script>
