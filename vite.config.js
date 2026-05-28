import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

// Inline CSS into JS — no separate CSS file to cache
function cssInjectPlugin() {
    return {
        name: 'css-inject',
        apply: 'build',
        enforce: 'post',
        generateBundle(_, bundle) {
            const cssKey = Object.keys(bundle).find(k => k.endsWith('.css'));
            const jsKey = Object.keys(bundle).find(k => k.endsWith('.js'));
            if (cssKey && jsKey) {
                const css = bundle[cssKey].source.replace(/\\/g, '\\\\').replace(/`/g, '\\`').replace(/\$/g, '\\$');
                bundle[jsKey].code += `\n(function(){var s=document.createElement("style");s.id="mime-guard-styles";s.textContent=\`${css}\`;document.head.appendChild(s)})();`;
                delete bundle[cssKey];
            }
        },
    };
}

export default defineConfig({
    plugins: [vue(), cssInjectPlugin()],
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
