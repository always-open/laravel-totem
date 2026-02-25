<template>
    <div class="uk-margin">
        <div class="uk-grid">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <div class="uk-form-label">Type</div>
                <div class="uk-text-meta">Choose whether to define a cron expression or to add frequencies</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m uk-form-controls-text">
                <label>
                    <input type="radio" name="type" v-model="type" value="expression"> Expression
                </label><br>
                <label>
                    <input type="radio" name="type" v-model="type" value="frequency"> Frequencies
                </label>
            </div>
        </div>

        <div class="uk-grid" v-if="isCron">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <label class="uk-form-label">Cron Expression</label>
                <div class="uk-text-meta">Add a cron expression for your task</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m">
                <input
                    class="uk-input"
                    placeholder="e.g * * * * * to run this task all the time"
                    name="expression"
                    id="expression"
                    :value="expressionValue"
                    type="text"
                >
                <p v-if="expressionError" class="uk-text-danger">{{ expressionError }}</p>
            </div>
        </div>

        <div class="uk-grid" v-if="managesFrequencies">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <label class="uk-form-label">Frequencies</label>
                <div class="uk-text-meta">Add frequencies to your task. These will be converted into a cron expression while scheduling.</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m">
                <a class="uk-button uk-button-small uk-button-link" @click.prevent="showModal = true">Add Frequency</a>

                <!-- Add Frequency Modal -->
                <uikit-modal :show="showModal" @close="closeModal">
                    <div class="uk-modal-header">
                        <h3>Add Frequency</h3>
                    </div>
                    <div class="uk-modal-body">
                        <fieldset class="uk-fieldset">
                            <div class="uk-margin">
                                <select id="frequency" class="uk-select" v-model="selected">
                                    <option :value="placeholder" disabled>Select a type of frequency</option>
                                    <option v-for="freq in frequenciesConfig" :key="freq.interval" :value="freq">
                                        {{ freq.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="selected.parameters">
                                <div class="uk-margin" v-for="parameter in selected.parameters" :key="parameter.name">
                                    <input
                                        type="text"
                                        v-model="parameter.value"
                                        :name="parameter.name"
                                        :placeholder="parameter.label"
                                        class="uk-input"
                                    >
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="uk-modal-footer">
                        <div class="uk-flex uk-flex-right">
                            <button class="uk-button uk-button-small uk-button-primary" @click.prevent="addFrequency">Add</button>
                        </div>
                    </div>
                </uikit-modal>

                <table class="uk-table uk-table-divider uk-margin-remove">
                    <thead>
                        <tr>
                            <th class="uk-padding-remove-left">Frequency</th>
                            <th class="uk-padding-remove-left">Parameters</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(frequency, index) in frequencies" :key="index">
                            <td class="uk-padding-remove-left">
                                {{ frequency.label }}
                                <input type="hidden" :name="'frequencies[' + index + '][interval]'" v-model="frequency.interval">
                                <input type="hidden" :name="'frequencies[' + index + '][label]'" v-model="frequency.label">
                            </td>
                            <td class="uk-padding-remove-left">
                                <span v-if="frequency.parameters && frequency.parameters.length > 0">
                                    <span v-for="(parameter, key) in frequency.parameters" :key="key">
                                        {{ parameter.value }}
                                        <span v-if="frequency.parameters.length > 1 && key < frequency.parameters.length - 1">,</span>
                                        <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key + '][name]'" v-model="parameter.name">
                                        <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key + '][value]'" v-model="parameter.value">
                                    </span>
                                </span>
                                <span v-else>No Parameters</span>
                            </td>
                            <td>
                                <a class="uk-button uk-button-link" @click="remove(index)">
                                    <span uk-icon="icon: close"></span>
                                </a>
                            </td>
                        </tr>
                        <tr v-if="frequencies.length === 0">
                            <td colspan="3" class="uk-padding-remove-left">No Frequencies Found</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="frequenciesError" class="uk-text-danger">{{ frequenciesError }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import UIKitModal from '../../components/UIKitModal.vue';

const props = defineProps({
    current: {
        type: String,
        default: 'frequency',
    },
    existing: {
        type: Array,
        default: () => [],
    },
    expressionValue: {
        type: String,
        default: '',
    },
    expressionError: {
        type: String,
        default: '',
    },
    frequenciesError: {
        type: String,
        default: '',
    },
    frequenciesConfig: {
        type: Array,
        default: () => [],
    },
});

const placeholder = { label: 'Please select a frequency', interval: false, parameters: false };

const type = ref(props.current);
const frequencies = ref(props.existing ? [...props.existing] : []);
const showModal = ref(false);
const selected = ref({ ...placeholder });

const isCron = computed(() => type.value === 'expression');
const managesFrequencies = computed(() => type.value === 'frequency');

function addFrequency() {
    if (selected.value.interval) {
        frequencies.value.push({ ...selected.value, parameters: selected.value.parameters ? selected.value.parameters.map(p => ({ ...p })) : [] });
        closeModal();
    }
}

function closeModal() {
    selected.value = { ...placeholder };
    showModal.value = false;
}

function remove(index) {
    frequencies.value.splice(index, 1);
}
</script>
