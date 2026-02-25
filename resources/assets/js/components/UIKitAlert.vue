<template>
  <div :class="classes" v-if="show">
    <a class="uk-alert-close uk-close uk-icon" @click="show = false">
      <span uk-icon="icon: close"></span>
    </a>
    <slot></slot>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  type: { type: String, default: 'primary' },
  important: { type: Boolean, default: false },
  timeout: { default: 5000 },
});

const show = ref(true);

const classes = computed(() => 'uk-alert uk-alert-' + props.type);

onMounted(() => {
  if (!props.important) {
    setTimeout(() => { show.value = false; }, props.timeout);
  }
});
</script>
