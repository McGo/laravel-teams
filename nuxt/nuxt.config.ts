// Hosts that may frame the tabs: Teams, Microsoft 365 and Outlook (web and
// desktop). Microsoft is moving hosts to *.cloud.microsoft while the old
// domains keep running, so both are listed.
const frameAncestors = [
    "'self'",
    'https://teams.microsoft.com',
    'https://*.teams.microsoft.com',
    'https://*.cloud.microsoft',
    'https://*.office.com',
    'https://outlook.office.com',
    'https://outlook.office365.com',
    'https://outlook-sdf.office.com',
    'https://outlook-sdf.office365.com',
    'https://*.microsoft365.com',
    'https://*.skype.com',
]

export default defineNuxtConfig({
    runtimeConfig: {
        public: {
            teams: {
                // Base URL for API calls. Empty means same origin.
                apiBase: '',
                // POST {token} -> {token, expires_at, user}. Resolved against apiBase.
                ssoEndpoint: '/api/teams/auth/sso',
                // Entra app (client) ID. Only needed for the consent popup.
                clientId: '',
                // Entra tenant ID for the consent popup. Falls back to the tenant
                // from the Teams context, then to "organizations".
                tenantId: '',
                // Teams app ID from the manifest. Only needed for openTab().
                appId: '',
            },
        },
    },

    routeRules: {
        // Tabs depend on the SSO token, so render them in the browser only.
        // This also keeps them out of prerendering in SSG apps.
        '/teams/**': {
            ssr: false,
            headers: {
                'Content-Security-Policy': `frame-ancestors ${frameAncestors.join(' ')}`,
                // Teams caches tab content aggressively. The entry HTML is small
                // and changes with every deploy.
                'Cache-Control': 'no-store',
            },
        },
    },
})
