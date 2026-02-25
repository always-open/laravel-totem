<template>
    <Teleport to="body">
        <transition mode="out-in">
            <div
                v-if="show"
                class="uk-modal uk-flex-top uk-open uk-display-block"
                @click="close"
            >
                <div class="uk-modal-dialog uk-margin-auto-vertical" @click.stop>
                    <button class="uk-button uk-button-link uk-modal-close-default" @click="close">
                        <span uk-icon="icon: close"></span>
                    </button>
                    <slot></slot>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

function handleKeydown(e) {
    if (props.show && e.key === 'Escape') {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>
