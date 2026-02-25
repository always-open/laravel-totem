<template>
    <click-to-close :do="close">
        <div class="search-select">
            <input type="text" name="command" ref="input" readonly @click="open" class="uk-input" v-model="selected" placeholder="Select a command"/>
            <div v-show="isOpen" class="uk-card uk-card-default uk-padding-small uk-box-shadow-large">
                <div class="uk-search uk-search-default uk-width-1-1">
                    <span class="uk-search-icon-flip" uk-search-icon></span>
                    <label>
                        <input class="uk-input"
                               type="search"
                               v-model="searchText"
                               ref="search"
                               @keydown.esc="close"
                               @keydown.down="highlightNext"
                               @keydown.up="highlightPrev"
                               @keydown.enter.prevent="selectHighlighted"
                               @keydown.tab.prevent>
                    </label>
                </div>

                <ul ref="options" v-show="filteredOptions.length > 0" class="uk-list uk-list-striped uk-height-max-medium uk-position-relative uk-overflow-auto">
                    <li class="search-select-option" :class="{ 'uk-text-bold': index === highlightedIndex }"
                        v-for="(option, index) in filteredOptions"
                        :key="option.name"
                        @click="select(option)">
                        {{ option.name }}
                        <em class="uk-padding-small uk-padding-remove-top uk-padding-remove-bottom uk-padding-remove-right">
                            {{option.description}}
                        </em>
                    </li>
                </ul>
                <div v-show="filteredOptions.length <= 0" class="uk-padding-small">
                    No results found for "{{ searchText }}"
                </div>
            </div>
        </div>
    </click-to-close>
</template>

<style scoped>
    .search-select-option {
        background: transparent;
    }
    .search-select-option:hover {
        cursor: pointer;
    }
</style>

<script setup>
import { ref, computed, nextTick } from 'vue';
import ClickToClose from '../../components/ClickToClose.vue';

const props = defineProps({
    command: { type: String, default: '' },
    commands: { type: [Array, Object], default: () => [] },
});

const input = ref(null);
const search = ref(null);
const options = ref(null);

const selected = ref(decodeURI(props.command));
const allOptions = ref(Object.values(props.commands));
const isOpen = ref(false);
const searchText = ref('');
const highlightedIndex = ref(0);

const filteredOptions = computed(() =>
    allOptions.value.filter(option => option.name.toLowerCase().includes(searchText.value.toLowerCase()))
);

function open() {
    if (isOpen.value) return;
    isOpen.value = true;
    highlightedIndex.value = allOptions.value.findIndex(o => o.name === selected.value);
    nextTick(() => {
        search.value?.focus();
        scrollToHighlighted();
    });
}

function close() {
    if (!isOpen.value) return;
    isOpen.value = false;
    input.value?.focus();
}

function select(option) {
    selected.value = option.name;
    searchText.value = '';
    highlightedIndex.value = 0;
    close();
}

function selectHighlighted() {
    select(filteredOptions.value[highlightedIndex.value]);
}

function scrollToHighlighted() {
    options.value?.children[highlightedIndex.value]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
}

function highlight(index) {
    highlightedIndex.value = index;
    if (highlightedIndex.value < 0) highlightedIndex.value = filteredOptions.value.length - 1;
    if (highlightedIndex.value > filteredOptions.value.length - 1) highlightedIndex.value = 0;
    scrollToHighlighted();
}

function highlightNext() { highlight(highlightedIndex.value + 1); }
function highlightPrev() { highlight(highlightedIndex.value - 1); }
</script>
