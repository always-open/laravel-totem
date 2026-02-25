<template>
    <tr :class="task.is_active ? '' : 'uk-text-danger'">
        <td>
            <a :href="showHref">
                {{ description }}
            </a>
            <span class="uk-float-right uk-hidden@s uk-text-muted">Command</span>
        </td>
        <td>
            {{ task.average_runtime ? averageDurationInSeconds : 0 }} seconds
            <span class="uk-float-right uk-hidden@s uk-text-muted">Avg. Runtime</span>
        </td>
        <td>
            {{ task.last_result ? lastRunDate : 'N/A' }}
            <span class="uk-float-right uk-hidden@s uk-text-muted">Last Run</span>
        </td>
        <td>
            {{task.upcoming}}
            <span class="uk-float-right uk-hidden@s uk-text-muted">Next Run</span>
        </td>
        <td class="uk-text-center@m">
            <execute-button
                :data-task="task"
                :url="executeHref"
                v-on:taskExecuted="refreshTask"
                icon-name="play"
                button-class="uk-button-link"
            />
        </td>
    </tr>
</template>

<script setup>
import { ref, computed } from 'vue';
import dayjs from 'dayjs';
import ExecuteButton from './ExecuteButton.vue';

const props = defineProps({
    dataTask: {},
    showHref: { type: String, default: '' },
    executeHref: { type: String, default: '' },
});

const task = ref(props.dataTask);

const description = computed(() => task.value.description.substring(0, 29));
const averageDurationInSeconds = computed(() =>
    task.value.average_runtime > 0 ? (task.value.average_runtime / 1000).toFixed(2) : 0
);
const lastRunDate = computed(() =>
    dayjs(task.value.last_result.ran_at).format('YYYY-MM-DD HH:mm:ss')
);

function refreshTask(updatedTask) {
    task.value = updatedTask;
}
</script>
