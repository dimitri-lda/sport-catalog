import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const https = env.VITE_HTTPS === 'true';
    const host = env.VITE_HOST ?? 'sport-catalog.local';
    const port = Number(env.VITE_PORT ?? 5173);

    return {
        plugins: [
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            https: https ? {
                key: env.VITE_HTTPS_KEY ?? './docker/certs/sport-catalog.local-key.pem',
                cert: env.VITE_HTTPS_CERT ?? './docker/certs/sport-catalog.local.pem',
            } : undefined,
            allowedHosts: ['sport-catalog.local', 'admin.sport-catalog.local'],
            cors: {
                origin: [`${https ? 'https' : 'http'}://sport-catalog.local`, `${https ? 'https' : 'http'}://admin.sport-catalog.local`],
            },
            hmr: {
                host,
                port,
                protocol: https ? 'wss' : 'ws',
            },
        },
    };
});
