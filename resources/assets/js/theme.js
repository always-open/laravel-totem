/**
 * Totem theme switcher
 *
 * Inlined raw into <head> by resources/views/layout.blade.php through
 * Totem::themeScript() (not built by Vite, so it must stay a self-contained
 * script with no imports), so the theme is applied before first paint.
 *
 * The preference is `light`, `dark`, or `system`, stored in localStorage under
 * the key in `<html data-totem-theme-key>` (config `totem.web.theme_storage_key`).
 * Filament's theme switcher uses the same values under the key `theme`, so a
 * host app can share one preference by configuring that key.
 *
 * Sets `dark` on <html> plus `data-theme-preference`, which the sidebar switcher's
 * active state is styled from. Clicks are delegated from `document` because
 * Totem's Vue app recompiles #root on mount, discarding listeners bound to the
 * sidebar's own elements.
 */
(function () {
    'use strict';

    const PREFERENCES = ['light', 'dark', 'system'];
    const root = document.documentElement;
    const storageKey = root.dataset.totemThemeKey || 'totem-theme';
    const osDark = window.matchMedia('(prefers-color-scheme: dark)');

    function normalize(value) {
        return PREFERENCES.includes(value) ? value : 'system';
    }

    function readPreference() {
        try {
            return normalize(window.localStorage.getItem(storageKey));
        } catch {
            return 'system';
        }
    }

    let preference = readPreference();

    function apply() {
        root.dataset.themePreference = preference;
        root.classList.toggle('dark', preference === 'dark' || (preference === 'system' && osDark.matches));
    }

    osDark.addEventListener('change', apply);

    document.addEventListener('click', (event) => {
        const option = event.target instanceof Element ? event.target.closest('[data-totem-theme]') : null;
        if (!option) {
            return;
        }

        preference = normalize(option.dataset.totemTheme);
        try {
            window.localStorage.setItem(storageKey, preference);
        } catch {
            // Storage is blocked: the choice applies to this page only.
        }
        apply();
    });

    apply();
})();
