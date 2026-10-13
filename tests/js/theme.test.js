import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// theme.js is a self-contained IIFE inlined into the dashboard's <head>. Each
// test re-imports it after vi.resetModules() so the IIFE re-runs against fresh state.

const SCRIPT_PATH = '../../resources/assets/js/theme.js';
const KEY = 'totem-theme';

/** Stub `matchMedia('(prefers-color-scheme: dark)')` with a controllable OS setting. */
function stubOsDarkMode(matches) {
    const listeners = [];
    const query = {
        matches,
        addEventListener: (type, listener) => {
            if (type === 'change') {
                listeners.push(listener);
            }
        },
    };

    vi.spyOn(window, 'matchMedia').mockImplementation((media) => {
        if (media !== '(prefers-color-scheme: dark)') {
            throw new Error(`unexpected media query: ${media}`);
        }

        return query;
    });

    return {
        fireChange(next) {
            query.matches = next;
            listeners.forEach((listener) => listener());
        },
    };
}

/** Add a switcher button after the script ran, as Totem's Vue mount re-renders the sidebar. */
function addOption(preference) {
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.totemTheme = preference;
    button.append(document.createElement('span'));
    document.body.append(button);

    return button;
}

async function loadScript() {
    await import(SCRIPT_PATH);
}

/** Collect errors thrown by event listeners, which `click()` reports to `window` instead of rethrowing. */
function collectListenerErrors() {
    const errors = [];
    const onError = (event) => {
        errors.push(event.error ?? event.message);
        event.preventDefault();
    };
    window.addEventListener('error', onError);

    return { errors, stop: () => window.removeEventListener('error', onError) };
}

const root = document.documentElement;
let documentListeners = [];

describe('totem-theme', () => {
    beforeEach(() => {
        vi.resetModules();
        localStorage.clear();
        root.className = '';
        delete root.dataset.themePreference;
        delete root.dataset.totemThemeKey;
        document.body.innerHTML = '';

        documentListeners = [];
        const addEventListener = document.addEventListener.bind(document);
        vi.spyOn(document, 'addEventListener').mockImplementation((type, listener, options) => {
            documentListeners.push([type, listener]);
            addEventListener(type, listener, options);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        documentListeners.forEach(([type, listener]) => document.removeEventListener(type, listener));
    });

    describe('on load', () => {
        it.each([
            { stored: 'dark', osDark: false, dark: true, preference: 'dark' },
            { stored: 'dark', osDark: true, dark: true, preference: 'dark' },
            { stored: 'light', osDark: false, dark: false, preference: 'light' },
            { stored: 'light', osDark: true, dark: false, preference: 'light' },
            { stored: 'system', osDark: true, dark: true, preference: 'system' },
            { stored: 'system', osDark: false, dark: false, preference: 'system' },
            { stored: null, osDark: true, dark: true, preference: 'system' },
            { stored: null, osDark: false, dark: false, preference: 'system' },
            { stored: 'sepia', osDark: true, dark: true, preference: 'system' },
            { stored: 'sepia', osDark: false, dark: false, preference: 'system' },
        ])(
            'stored $stored with OS dark $osDark → dark class $dark, preference $preference',
            async ({ stored, osDark, dark, preference }) => {
                if (stored !== null) {
                    localStorage.setItem(KEY, stored);
                }
                stubOsDarkMode(osDark);

                await loadScript();

                expect(root.classList.contains('dark')).toBe(dark);
                expect(root.dataset.themePreference).toBe(preference);
            }
        );
    });

    describe('OS color scheme changes', () => {
        it('follows the OS while the preference is system', async () => {
            const os = stubOsDarkMode(false);
            await loadScript();
            expect(root.classList.contains('dark')).toBe(false);

            os.fireChange(true);
            expect(root.classList.contains('dark')).toBe(true);

            os.fireChange(false);
            expect(root.classList.contains('dark')).toBe(false);
        });

        it.each([
            { stored: 'light', dark: false },
            { stored: 'dark', dark: true },
        ])('ignores the OS while the preference is $stored', async ({ stored, dark }) => {
            localStorage.setItem(KEY, stored);
            const os = stubOsDarkMode(!dark);
            await loadScript();

            os.fireChange(dark);
            expect(root.classList.contains('dark')).toBe(dark);

            os.fireChange(!dark);
            expect(root.classList.contains('dark')).toBe(dark);
        });
    });

    it('parses as a classic script, as the layout inlines it', () => {
        // The layout inlines the file raw; module syntax would be a SyntaxError there.
        const source = readFileSync(resolve(process.cwd(), 'resources/assets/js/theme.js'), 'utf8');

        expect(() => new Function(source)).not.toThrow();
    });

    describe('switcher clicks', () => {
        it('registers one delegated click listener', async () => {
            stubOsDarkMode(false);
            await loadScript();

            expect(documentListeners.filter(([type]) => type === 'click')).toHaveLength(1);
        });

        it.each([
            { from: 'system', to: 'dark', osDark: false, dark: true },
            { from: 'dark', to: 'light', osDark: true, dark: false },
            { from: 'light', to: 'system', osDark: true, dark: true },
            { from: 'dark', to: 'system', osDark: false, dark: false },
        ])('$from → $to saves and applies it (OS dark $osDark)', async ({ from, to, osDark, dark }) => {
            localStorage.setItem(KEY, from);
            stubOsDarkMode(osDark);
            await loadScript();

            addOption(to).click();

            expect(localStorage.getItem(KEY)).toBe(to);
            expect(root.dataset.themePreference).toBe(to);
            expect(root.classList.contains('dark')).toBe(dark);
        });

        it('handles a click on an element inside the option', async () => {
            stubOsDarkMode(false);
            await loadScript();

            addOption('dark').querySelector('span').click();

            expect(localStorage.getItem(KEY)).toBe('dark');
            expect(root.classList.contains('dark')).toBe(true);
        });

        it('ignores clicks outside the switcher', async () => {
            localStorage.setItem(KEY, 'light');
            stubOsDarkMode(true);
            await loadScript();

            const other = document.createElement('button');
            document.body.append(other);
            other.click();

            expect(localStorage.getItem(KEY)).toBe('light');
            expect(root.dataset.themePreference).toBe('light');
            expect(root.classList.contains('dark')).toBe(false);
        });

        it('a system choice made by click follows later OS changes', async () => {
            localStorage.setItem(KEY, 'light');
            const os = stubOsDarkMode(false);
            await loadScript();

            addOption('system').click();
            os.fireChange(true);

            expect(root.classList.contains('dark')).toBe(true);
        });
    });

    describe('storage key', () => {
        it('reads and writes the key named on <html>', async () => {
            root.dataset.totemThemeKey = 'theme';
            localStorage.setItem('theme', 'dark');
            localStorage.setItem(KEY, 'light');
            stubOsDarkMode(false);
            await loadScript();

            expect(root.classList.contains('dark')).toBe(true);

            addOption('light').click();

            expect(localStorage.getItem('theme')).toBe('light');
            expect(localStorage.getItem(KEY)).toBe('light');
            expect(root.classList.contains('dark')).toBe(false);
        });

        it('falls back to totem-theme when <html> names no key', async () => {
            stubOsDarkMode(false);
            await loadScript();

            addOption('dark').click();

            expect(localStorage.getItem(KEY)).toBe('dark');
        });
    });

    describe.each([
        {
            layer: 'Storage methods throw',
            block: () => {
                vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
                    throw new DOMException('blocked', 'SecurityError');
                });
                vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
                    throw new DOMException('blocked', 'SecurityError');
                });
            },
        },
        {
            layer: 'window.localStorage throws',
            block: () => {
                vi.spyOn(window, 'localStorage', 'get').mockImplementation(() => {
                    throw new DOMException('blocked', 'SecurityError');
                });
            },
        },
    ])('blocked storage ($layer)', ({ block }) => {
        beforeEach(() => {
            block();
        });

        it.each([
            { osDark: true, dark: true },
            { osDark: false, dark: false },
        ])('falls back to browser default (OS dark $osDark)', async ({ osDark, dark }) => {
            stubOsDarkMode(osDark);

            await loadScript();

            expect(root.dataset.themePreference).toBe('system');
            expect(root.classList.contains('dark')).toBe(dark);
        });

        it('still applies a clicked option to the page without a listener error', async () => {
            stubOsDarkMode(false);
            await loadScript();
            const listenerErrors = collectListenerErrors();

            addOption('dark').click();
            listenerErrors.stop();

            expect(listenerErrors.errors).toEqual([]);
            expect(root.dataset.themePreference).toBe('dark');
            expect(root.classList.contains('dark')).toBe(true);
        });
    });
});
