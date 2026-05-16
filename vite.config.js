import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

const packageRoot = dirname(fileURLToPath(import.meta.url));

const defaultSyncTarget = resolve(packageRoot, '../../public/vendor/deck/deck.css');

function syncDeckStylesheet(target = process.env.DECK_ASSETS_SYNC ?? defaultSyncTarget) {
    return {
        name: 'sync-deck-stylesheet',
        writeBundle() {
            if (! target) {
                return;
            }

            const built = resolve(packageRoot, 'resources/dist/deck.css');

            mkdirSync(dirname(target), { recursive: true });
            copyFileSync(built, target);
        },
    };
}

export default defineConfig({
    plugins: [tailwindcss(), syncDeckStylesheet()],
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/css/deck.css',
            output: {
                assetFileNames: 'deck.[ext]',
            },
        },
    },
});
