# @mirkohaaser/nuxt-teams

Nuxt 4 layer for Microsoft Teams personal tabs. It brings:

- `useTeams<TUser>()`: initialises `@microsoft/teams-js`, gets the SSO token, exchanges it at your API, asks for consent once if silent SSO fails, reads the `subPageId` from deep links and follows the Teams theme.
- the layout `teams` without app navigation,
- the page `/teams/consent` for the consent popup,
- route rules for `/teams/**`: client-only rendering, `Content-Security-Policy: frame-ancestors` for the Teams, Microsoft 365 and Outlook hosts, and `Cache-Control: no-store`.

No dependency on Nuxt UI. If the app uses `@nuxtjs/color-mode`, the Teams themes `dark` and `contrast` switch it to dark.

## Install

```bash
npm install @mirkohaaser/nuxt-teams
```

```ts
// nuxt.config.ts
export default defineNuxtConfig({
    extends: ['@mirkohaaser/nuxt-teams'],
    runtimeConfig: {
        public: {
            teams: {
                apiBase: 'https://api.contoso.com',
                ssoEndpoint: '/api/teams/auth/sso',
                clientId: '00000000-0000-0000-0000-000000000000',
                tenantId: '00000000-0000-0000-0000-000000000000',
                appId: '00000000-0000-0000-0000-000000000000',
            },
        },
    },
})
```

Each value can also come from the environment, e.g. `NUXT_PUBLIC_TEAMS_API_BASE`.

| Key | Default | Purpose |
|---|---|---|
| `apiBase` | `''` (same origin) | Base URL for the SSO exchange and `api()` |
| `ssoEndpoint` | `/api/teams/auth/sso` | `POST {token}` returns `{token, expires_at, user}`; 401 invalid token, 403 no linked account |
| `clientId` | `''` | Entra app ID, used by the consent popup |
| `tenantId` | `''` | Entra tenant for the consent popup; falls back to the tenant from the Teams context |
| `appId` | `''` | Teams app ID, used by `openTab()` |

## A tab page

Tabs are pages of your app under `/teams/…`. The layout signs in and renders the page once the user is known.

```vue
<script setup lang="ts">
definePageMeta({ layout: 'teams' })

interface User { id: number, name: string }

const { user, subPageId, api } = useTeams<User>()
const items = ref(await api<{ id: number, title: string }[]>('/api/items'))
</script>

<template>
    <h1>Hello {{ user?.name }}</h1>
    <p v-if="subPageId">Opened from a deep link: {{ subPageId }}</p>
    <ul><li v-for="item in items" :key="item.id">{{ item.title }}</li></ul>
</template>
```

`useTeams()` returns `user`, `token`, `expiresAt`, `subPageId`, `theme`, `ready`, `error`, `diagnose`, `start()`, `api()`, `openTab()` and `openLink()`. `api()` sends the bearer token and refreshes it once on 401.

Outside Teams the layout shows an error after `initTimeoutMs` instead of loading forever. Below the error it prints a diagnose line with host, client type and the last error, because the dev tools of the desktop client are hard to reach.

## Texts and styling

Override the texts in your `app.config.ts`:

```ts
export default defineAppConfig({
    teams: {
        initTimeoutMs: 5000,
        messages: {
            loading: 'Wird geladen …',
            notInTeams: 'Diese Seite läuft nur als Registerkarte in Microsoft Teams.',
        },
    },
})
```

The layout has `loading` and `error` slots (the latter gets `{ error, diagnose }`). Colours are CSS custom properties on `.teams-shell` (`--teams-bg`, `--teams-fg`, `--teams-accent`, …) and switch with `data-theme`.

## Static hosting

With `nuxi generate` the route rules do not reach your web server. Set the CSP header for `/teams` there yourself and serve the SPA fallback for that path. Do not send `X-Frame-Options`; it allows only one origin and blanks the tab.

## Playground

```bash
cd nuxt
npm install
cp playground/.env.example playground/.env
npm run dev
```

Expose the dev server through a tunnel and point the static tabs of your Teams manifest at `/teams/home` and `/teams/items`.

## License

MIT
