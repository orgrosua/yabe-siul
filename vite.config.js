import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { v4wp } from '@kucrut/vite-for-wp';
// import { wp_scripts } from '@kucrut/vite-for-wp/plugins';

export default defineConfig({
    plugins: [
        v4wp({
            input: {
                admin: 'assets/admin/main.js',
                'integration/bricks': 'assets/integration/bricks/main.js',
                // 'integration/oxybender': 'assets/integration/oxygen/main.js',
            },
            outDir: 'build',
        }),
        // wp_scripts(),
        vue(),
        {
            name: 'override-config',
            config: () => ({
                build: {
                    // ensure that manifest.json is not in ".vite/" folder
                    manifest: 'manifest.json',

                    // disable sourcemap
                    sourcemap: false,
                },
            }),
        },
    ],
    css: {
        lightningcss: true,
    },
    build: {
        // target: 'esnext',
        target: 'modules',
    },
    publicDir: 'assets/static',
});