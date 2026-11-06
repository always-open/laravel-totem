import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// dark.css loads after app.css and components.css on every dashboard page. Light
// mode must render the package CSS untouched, so every rule is scoped to dark
// mode, to the theme switcher in the sidebar, or to the one-off run status
// labels (classes only the Run once pages use).

const STYLESHEET = resolve(process.cwd(), 'public/css/dark.css');

function stripComments(css) {
    return css.replace(/\/\*[\s\S]*?\*\//g, '');
}

function selectors(css) {
    return [...stripComments(css).matchAll(/([^{}]+)\{[^{}]*\}/g)].flatMap(([, selectorList]) =>
        selectorList.split(',').map((selector) => selector.trim())
    );
}

/** Scoped: under `html.dark`, inside the switcher (optionally keyed on the preference), or a run status label. */
function isScoped(selector) {
    return (
        /^html\.dark(?![\w-])/.test(selector) ||
        /^(html\[data-theme-preference='\w+'\] )?\.totem-theme-switcher($| (?![~+>]))/.test(selector) ||
        /^\.totem-run-status-(succeeded|failed)$/.test(selector)
    );
}

/** The declarations of the rule whose selector list is exactly `selector`. */
function declarations(css, selector) {
    for (const [, selectorList, body] of stripComments(css).matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
        if (selectorList.trim() === selector) {
            return Object.fromEntries(
                body
                    .split(';')
                    .map((d) => d.trim())
                    .filter(Boolean)
                    .map((d) => d.split(':').map((part) => part.trim().toLowerCase()))
            );
        }
    }

    return null;
}

describe('dark.css', () => {
    const css = readFileSync(STYLESHEET, 'utf8');

    it('contains no at-rules, so every rule is a plain selector block', () => {
        expect(stripComments(css)).not.toContain('@');
    });

    it('scopes every selector under html.dark or the theme switcher', () => {
        const all = selectors(css);

        expect(all.length).toBeGreaterThan(0);
        expect(all.filter((selector) => !isScoped(selector))).toEqual([]);
    });

    it.each(['light', 'dark', 'system'])('marks the %s option active when it is the preference', (preference) => {
        expect(selectors(css)).toContain(
            `html[data-theme-preference='${preference}'] .totem-theme-switcher [data-totem-theme='${preference}']`
        );
    });

    it.each([
        ['.totem-run-status-succeeded', '#dcf3e6', '#1b5e3f'],
        ['.totem-run-status-failed', '#fbe3e3', '#8f2a2c'],
        ['html.dark .totem-run-status-succeeded', '#173d2b', '#8ee0b5'],
        ['html.dark .totem-run-status-failed', '#4a1d1e', '#f4a9aa'],
    ])('tints the run status label %s', (selector, background, color) => {
        expect(declarations(css, selector)).toEqual({ 'background-color': background, color });
    });

    it('treats an unscoped selector as unscoped', () => {
        expect(selectors('html.dark .uk-card, .uk-card { color: red }').filter((s) => !isScoped(s))).toEqual([
            '.uk-card',
        ]);
        expect(isScoped('html.darker .uk-card')).toBe(false);
        expect(isScoped('.totem-theme-switcher-other')).toBe(false);
        expect(isScoped('aside:has(.totem-theme-switcher)')).toBe(false);
        expect(isScoped('.totem-theme-switcher ~ section')).toBe(false);
        expect(isScoped('.uk-card .totem-theme-switcher')).toBe(false);
        expect(isScoped('.totem-run-status-succeeded .uk-card')).toBe(false);
        expect(isScoped('.totem-run-status')).toBe(false);
    });
});
