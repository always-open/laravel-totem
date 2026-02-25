<template>
  <transition mode="out-in">
    <button v-if="task.activated" type="button" class="uk-button uk-button-small" :class="{ 'uk-button-primary': !hovering && !working, 'uk-button-danger': hovering && !working, 'uk-spinner uk-icon uk-button-secondary': working}" key="enabled" @mouseenter="hovering = true" @mouseleave="hovering = false" @click="deactivate" :disabled="working">
      <span v-if="!working">{{ activeStatusText }}</span>
      <div v-if="working" uk-spinner="ratio: 1"></div>
    </button>
    <button v-if="existsAndIsInActive" type="button" class="uk-button uk-button-small" :class="{'uk-button-danger': !hovering && !working, 'uk-button-primary': hovering && !working, 'uk-spinner uk-icon uk-button-secondary': working}" key="disabled" @mouseenter="hovering = true" @mouseleave="hovering = false" @click="activate" :disabled="working">
      <span v-if="!working">{{ inActiveStatusText }}</span>
      <div v-if="working" uk-spinner="ratio: 1"></div>
    </button>
  </transition>
</template>

<script setup>
import { ref, computed } from 'vue';
import { takeAtLeast } from '../../utils/takeAtLeast.js';

const props = defineProps({
    dataTask: { type: Object, default: null },
    dataExists: { type: Boolean, default: false },
    activateUrl: { type: String, required: true },
    deactivateUrl: { type: String, required: true },
});

const hovering = ref(false);
const working = ref(false);
const task = ref(props.dataTask);
const exists = ref(props.dataExists);

const inActiveStatusText = computed(() => hovering.value ? 'Enable' : 'Disabled');
const activeStatusText = computed(() => hovering.value ? 'Disable' : 'Enabled');
const existsAndIsInActive = computed(() => !task.value.activated && exists.value);

async function activate() {
    working.value = true;
    try {
        const response = await takeAtLeast(axios.post(props.activateUrl, { task_id: props.dataTask.id }), 500);
        task.value = response.data;
    } finally {
        working.value = false;
        hovering.value = false;
    }
}

async function deactivate() {
    working.value = true;
    try {
        const response = await takeAtLeast(axios.delete(props.deactivateUrl), 500);
        task.value = response.data;
    } finally {
        working.value = false;
        hovering.value = false;
    }
}
</script>
