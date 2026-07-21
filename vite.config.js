import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const assetUrl = env.ASSET_URL || env.APP_URL || '';
    let base = '/build/';

    try {
        if (assetUrl) {
            const pathname = new URL(assetUrl).pathname.replace(/\/$/, '');
            base = `${pathname}/build/`;
        }
    } catch {
        // keep default /build/
    }

    return {
        base,
        plugins: [
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
        resolve: {
            alias: {
                '@': path.resolve('resources/js'),
            },
        },
    };
});
