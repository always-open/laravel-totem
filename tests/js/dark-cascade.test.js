import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';

// Resolves colors through the real cascade: the compiled UIkit theme, then
// dark.css, in the order the layout loads them. Each case is a surface
// where a dark override ties or beats a UIkit rule it must not replace.

const PACKAGE_CSS = resolve(process.cwd(), 'public/css/app.css');
const DARK_CSS = resolve(process.cwd(), 'public/css/dark.css');

const root = document.documentElement;
const styles = [];

function render(html) {
    document.body.innerHTML = html;

    return document.body.firstElementChild;
}

describe('dark.css over the UIkit theme', () => {
    beforeAll(() => {
        for (const file of [PACKAGE_CSS, DARK_CSS]) {
            const style = document.createElement('style');
            style.textContent = readFileSync(file, 'utf8');
            document.head.append(style);
            styles.push(style);
        }
    });

    afterAll(() => {
        styles.forEach((style) => style.remove());
        root.className = '';
        delete root.dataset.themePreference;
    });

    beforeEach(() => {
        root.className = '';
        delete root.dataset.themePreference;
        document.body.innerHTML = '';
    });

    it.each([
        { preference: 'light', dark: false },
        { preference: 'dark', dark: true },
        { preference: 'system', dark: true },
        { preference: 'system', dark: false },
    ])('the active $preference option keeps its accent colors (dark $dark)', ({ preference, dark }) => {
        root.classList.toggle('dark', dark);
        root.dataset.themePreference = preference;
        const option = render(
            `<div class="totem-theme-switcher"><button class="uk-button uk-button-small" data-totem-theme="${preference}">x</button></div>`
        ).querySelector('button');

        const style = getComputedStyle(option);
        expect(style.color).toBe('rgb(255, 255, 255)');
        expect(style.borderTopColor).toBe('rgb(88, 114, 181)');
    });

    it('an inactive option takes the dark switcher colors', () => {
        root.classList.add('dark');
        root.dataset.themePreference = 'dark';
        const option = render(
            '<div class="totem-theme-switcher"><button class="uk-button uk-button-small" data-totem-theme="light">x</button></div>'
        ).querySelector('button');

        expect(getComputedStyle(option).color).toBe('rgb(212, 212, 212)');
    });

    describe('in dark mode', () => {
        beforeEach(() => {
            root.classList.add('dark');
        });

        it('recolors a plain link', () => {
            expect(getComputedStyle(render('<a href="#">x</a>')).color).toBe('rgb(125, 156, 236)');
        });

        it('leaves an anchor primary button white', () => {
            const button = render('<a href="#" class="uk-button uk-button-primary">x</a>');

            expect(getComputedStyle(button).color).toBe('rgb(255, 255, 255)');
        });

        it('leaves a primary icon button on the primary fill', () => {
            const button = render('<a href="#" class="uk-icon-button uk-button-primary">x</a>');

            expect(getComputedStyle(button).backgroundColor).toBe('rgb(88, 114, 181)');
            expect(getComputedStyle(button).color).toBe('rgb(255, 255, 255)');
        });

        it('recolors an outline secondary button', () => {
            const button = render('<a href="#" class="uk-button uk-button-secondary">x</a>');

            expect(getComputedStyle(button).color).toBe('rgb(125, 156, 236)');
        });

        it.each([
            { type: 'success', color: 'rgb(75, 199, 143)' },
            { type: 'danger', color: 'rgb(217, 94, 96)' },
            { type: 'warning', color: 'rgb(247, 199, 125)' },
            { type: 'primary', color: 'rgb(125, 156, 236)' },
        ])('keeps the $type alert color', ({ type, color }) => {
            expect(getComputedStyle(render(`<div class="uk-alert uk-alert-${type}">x</div>`)).color).toBe(color);
        });

        it('darkens the modal footer border', () => {
            expect(getComputedStyle(render('<div class="uk-modal-footer">x</div>')).borderTopColor).toBe(
                'rgb(64, 64, 64)'
            );
        });
    });

    it('in light mode, leaves a plain link on the package color', () => {
        expect(getComputedStyle(render('<a href="#">x</a>')).color).toBe('rgb(84, 102, 196)');
    });
});
