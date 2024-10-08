import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { v4wp } from '@kucrut/vite-for-wp';
import { nodePolyfills } from 'vite-plugin-node-polyfills';
import { wp_scripts } from '@kucrut/vite-for-wp/plugins';
import wasm from 'vite-plugin-wasm';
import topLevelAwait from 'vite-plugin-top-level-await';
import path from 'path';

export default defineConfig({
    plugins: [
        wasm(),
        topLevelAwait(),
        nodePolyfills({
            // Override the default polyfills for specific modules.
            overrides: {
                fs: 'memfs', // Since `fs` is not supported in browsers, we can use the `memfs` package to polyfill it.
            },
        }),
        v4wp({
            input: {
                admin: 'assets/admin/main.js',

                // Integrations
                'integration/bricks': 'assets/integration/bricks/main.js',
                'integration/oxygen/iframe': 'assets/integration/oxygen/iframe/main.js',
                'integration/oxygen/editor': 'assets/integration/oxygen/editor/main.js',

                // Tailwind
                'integration/lib/compiler': 'assets/integration/common/compiler-bootstrap.js',
            },
            outDir: 'build',
        }),
        vue(),
        wp_scripts(),
    ],
    build: {
        target: 'modules',
        sourcemap: false,
    },
    publicDir: 'assets/static',
    resolve: {
        alias: {
            '~': path.resolve(__dirname), // root directory
            '@/admin': path.resolve(__dirname, './assets/apps/admin'),
            '@/integration': path.resolve(__dirname, './assets/integration'),
            '@/common': path.resolve(__dirname, './assets/common'),
            // '@/packages': path.resolve(__dirname, './assets/packages'),
        },
    },
});