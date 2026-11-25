// Minimal app that extends the layer. Run it behind a dev tunnel and point
// the Teams manifest's static tabs at /teams/home and /teams/items.
export default defineNuxtConfig({
    extends: ['..'],
    compatibilityDate: '2026-05-05',
    devtools: { enabled: false },
    vite: {
        // Allow the dev tunnel host.
        server: { allowedHosts: true },
    },
})
