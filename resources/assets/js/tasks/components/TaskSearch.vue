<template>
  <form
    accept-charset="UTF-8"
    method="GET"
    :action="action"
    id="totem__search__form"
    class="uk-display-inline uk-search uk-search-default"
    @submit.prevent="search"
  >
    <span uk-search-icon></span>
    <input
      v-model="query"
      placeholder="Search..."
      name="q"
      type="text"
      class="uk-search-input"
      @input="queueSearch"
    >
  </form>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';
import { searchTasks } from '../search';

const props = defineProps({
    action: { type: String, required: true },
    value: { type: String, default: '' },
    debounce: { type: Number, default: 250 },
});

const query = ref(props.value);
let timer = null;

function queueSearch() {
    clearTimeout(timer);
    timer = setTimeout(search, props.debounce);
}

function search() {
    clearTimeout(timer);

    const url = new URL(window.location.href);
    const q = query.value.trim();

    if (q) {
        url.searchParams.set('q', q);
    } else {
        url.searchParams.delete('q');
    }
    url.searchParams.delete('page');

    searchTasks(url)
        .then(() => window.history.replaceState(null, '', url))
        .catch((error) => {
            if (error.name !== 'AbortError') {
                window.location.assign(url);
            }
        });
}

onBeforeUnmount(() => clearTimeout(timer));
</script>
