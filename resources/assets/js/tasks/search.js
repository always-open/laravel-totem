import { reactive } from 'vue';

// Latest server-rendered HTML for each <task-search-region>, keyed by name.
// Empty until the first live search replaces the initial page content.
export const regions = reactive({});

let controller = null;

/**
 * Fetch the tasks page for the given URL and store the HTML of each
 * search region so the matching <task-search-region> can re-render it.
 */
export function searchTasks(url) {
    if (controller) {
        controller.abort();
    }
    controller = new AbortController();

    return fetch(url, { signal: controller.signal, headers: { Accept: 'text/html' } })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }
            return response.text();
        })
        .then((html) => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            doc.querySelectorAll('task-search-region').forEach((region) => {
                regions[region.getAttribute('name')] = region.innerHTML;
            });
        });
}
