import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    // Live docroot is /public → assets must be /build/...
    // For local MAMP subdirectory installs only, set VITE_BASE=/DRM/public/build/
    // Do NOT derive base from APP_URL — that bakes local paths into production builds.
    const base = (env.VITE_BASE || '/build/').replace(/\/?$/, '/');

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
