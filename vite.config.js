import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/assets/js/app.js'),
            name: 'TotemApp',
            formats: ['iife'],
            fileName: () => 'app.js',
        },
        outDir: 'public/js',
        emptyOutDir: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                assetFileNames: '../css/components.css',
            },
        },
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
});
