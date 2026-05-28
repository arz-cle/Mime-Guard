import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/js/addon.js'),
            formats: ['iife'],
            name: 'MimeGuardAddon',
            fileName: () => 'cp.js',
        },
        rollupOptions: {
            external: ['vue'],
            output: {
                globals: { vue: 'Vue' },
            },
        },
        outDir: resolve(__dirname, 'resources/dist'),
        emptyOutDir: true,
    },
});
