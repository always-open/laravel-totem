import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// dark.css loads after app.css and components.css on every dashboard page. Light
// mode must render the package CSS untouched, so every rule is scoped to dark
// mode or to the theme switcher in the sidebar.

const STYLESHEET = resolve(process.cwd(), 'public/css/dark.css');

function stripComments(css) {
    return css.replace(/\/\*[\s\S]*?\*\//g, '');
}

function selectors(css) {
    return [...stripComments(css).matchAll(/([^{}]+)\{[^{}]*\}/g)].flatMap(([, selectorList]) =>
        selectorList.split(',').map((selector) => selector.trim())
    );
}

/** Scoped: under `html.dark`, or inside the switcher (optionally keyed on the preference). */
function isScoped(selector) {
    return (
        /^html\.dark(?![\w-])/.test(selector) ||
        /^(html\[data-theme-preference='\w+'\] )?\.totem-theme-switcher($| (?![~+>]))/.test(selector)
    );
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

    it('treats an unscoped selector as unscoped', () => {
        expect(selectors('html.dark .uk-card, .uk-card { color: red }').filter((s) => !isScoped(s))).toEqual([
            '.uk-card',
        ]);
        expect(isScoped('html.darker .uk-card')).toBe(false);
        expect(isScoped('.totem-theme-switcher-other')).toBe(false);
        expect(isScoped('aside:has(.totem-theme-switcher)')).toBe(false);
        expect(isScoped('.totem-theme-switcher ~ section')).toBe(false);
        expect(isScoped('.uk-card .totem-theme-switcher')).toBe(false);
    });
});
