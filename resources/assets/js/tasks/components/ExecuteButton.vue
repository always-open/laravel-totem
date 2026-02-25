<template>
  <transition mode="out-in">
    <button type="button" class="uk-button" :class="buttonClasses" @click="execute" :disabled="running">
      <span v-if="iconName && !running" uk-icon="icon: check; ratio: 1"></span>
      <span v-else>
          <span v-if="!running">Execute</span>
      </span>
      <div v-if="running" uk-spinner="ratio: 1"></div>
    </button>
  </transition>
</template>

<script setup>
import { ref, computed } from 'vue';
import { takeAtLeast } from '../../utils/takeAtLeast.js';

const props = defineProps({
    dataTask: {},
    url: { type: String, required: true },
    iconName: { type: String, default: null },
    buttonClass: { type: String, default: 'uk-button-small' },
});

const emit = defineEmits(['taskExecuted']);

const running = ref(false);
const task = ref(props.dataTask);

const buttonClasses = computed(() => running.value ? 'uk-spinner uk-icon' : props.buttonClass);

async function execute() {
    running.value = true;
    try {
        const response = await takeAtLeast(axios.get(props.url), 500);
        task.value = response.data;
        emit('taskExecuted', task.value);
    } finally {
        running.value = false;
    }
}
</script>
