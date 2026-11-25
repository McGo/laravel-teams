// User-facing texts of the layer. Override them in your app's app.config.ts.
export default defineAppConfig({
    teams: {
        // How long to wait for the Teams host before giving up.
        initTimeoutMs: 5000,
        messages: {
            loading: 'Loading …',
            notInTeams: 'This page only works as a tab in Microsoft Teams.',
            loadFailed: 'The tab could not be loaded.',
            signInFailed: 'Sign-in failed.',
            invalidToken: 'Microsoft Teams sent a token the server did not accept.',
            noAccount: 'There is no account linked to your Microsoft identity.',
            consentPending: 'Asking for your consent …',
        },
    },
})
