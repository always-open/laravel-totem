<template>
  <span class="js-upload" uk-form-custom>
    <input type="file">
    <button class="uk-icon-button uk-button-primary uk-hidden@m" uk-icon="icon: cloud-upload" type="button"></button>
    <button class="uk-button uk-button-primary uk-button-small uk-visible@m" type="button">
      <span v-if="importing" uk-spinner="ratio: 1"></span>
      <span v-else>Import</span>
    </button>
  </span>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import UIkit from 'uikit';

const props = defineProps({
    url: { type: String, required: true },
});

const importing = ref(false);

onMounted(() => {
    UIkit.upload('.js-upload', {
        url: props.url,
        method: 'POST',
        name: 'tasks',
        beforeSend(environment) {
            environment.headers['X-CSRF-TOKEN'] = window.axios.defaults.headers.common['X-CSRF-TOKEN'];
        },
        beforeAll() {
            importing.value = true;
        },
        completeAll() {
            importing.value = false;
            window.location.reload(true);
        },
    });
});
</script>