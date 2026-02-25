<template>
    <span>
        <button class="uk-button uk-button-link" @click="showModal = true">
            <span uk-icon="icon: more"></span>
        </button>
        <uikit-modal :show="showModal" @close="showModal = false">
            <div class="uk-modal-header">
                <h3>Output</h3>
            </div>
            <div class="uk-modal-body uk-overflow-auto uk-height-max-large">
                <pre style="white-space: pre-wrap; word-break: break-word;">{{ output }}</pre>
            </div>
            <div class="uk-modal-footer">
                <div class="uk-flex uk-flex-right">
                    <button class="uk-button uk-button-small uk-button-primary" @click="showModal = false">Close</button>
                </div>
            </div>
        </uikit-modal>
    </span>
</template>
<script setup>
import { ref } from 'vue';
import UIKitModal from '../../components/UIKitModal.vue';

const props = defineProps({
    output: { type: String, default: '' },
});

const showModal = ref(false);
</script>
