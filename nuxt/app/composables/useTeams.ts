import { app, authentication, pages } from '@microsoft/teams-js'

/**
 * Personal tab inside Microsoft Teams: single sign-on, Teams theme and the
 * target from a deep link (subPageId).
 *
 * The tab gets its own short-lived API token from the SSO exchange. It does
 * not share a session with the rest of the app.
 */

export type TeamsTheme = 'default' | 'dark' | 'contrast'

/** Response of the SSO endpoint. */
export interface TeamsSession<TUser = unknown> {
    token: string
    expires_at: string
    user: TUser
}

type FetchOptions = NonNullable<Parameters<typeof $fetch>[1]>

// After consent, Entra needs a moment before silent SSO works.
const CONSENT_RETRIES = 3
const CONSENT_DELAY_MS = 1000

const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

function describe(e: unknown): string {
    if (typeof e === 'string') return e
    if (e instanceof Error) return e.message
    return String(e)
}

function statusOf(e: unknown): number | undefined {
    const err = e as { status?: number, statusCode?: number, response?: { status?: number } } | null
    return err?.status ?? err?.statusCode ?? err?.response?.status
}

function normalizeTheme(raw: string | null | undefined): TeamsTheme {
    if (raw === 'dark') return 'dark'
    if (raw === 'contrast') return 'contrast'
    return 'default'
}

export function useTeams<TUser = unknown>() {
    const nuxtApp = useNuxtApp()
    const config = useRuntimeConfig().public.teams
    const { messages, initTimeoutMs } = useAppConfig().teams

    const token = useState<string | null>('teams.token', () => null)
    const expiresAt = useState<string | null>('teams.expiresAt', () => null)
    const user = useState<TUser | null>('teams.user', () => null)
    const theme = useState<TeamsTheme>('teams.theme', () => 'default')
    const subPageId = useState<string | null>('teams.subPageId', () => null)
    const error = useState<string | null>('teams.error', () => null)
    const ready = useState<boolean>('teams.ready', () => false)
    const started = useState<boolean>('teams.started', () => false)
    const hostInfo = useState<string | null>('teams.hostInfo', () => null)
    const lastError = useState<string | null>('teams.lastError', () => null)

    // Shown in the tab itself: the dev tools of the desktop client are hard
    // to reach, so this line is often the only clue.
    const diagnose = computed(() => {
        const parts = [hostInfo.value ?? 'no Teams host']
        if (lastError.value) parts.push(`last error: ${lastError.value}`)
        return parts.join(' · ')
    })

    function fail(message: string, cause?: unknown): void {
        error.value = message
        lastError.value = cause === undefined ? message : describe(cause)
    }

    function setTheme(next: TeamsTheme): void {
        theme.value = next
        // Follow Teams in @nuxtjs/color-mode if the app uses it.
        const colorMode = (nuxtApp as unknown as { $colorMode?: { preference: string } }).$colorMode
        if (colorMode) colorMode.preference = next === 'default' ? 'light' : 'dark'
    }

    /**
     * Initialises the tab: reads the Teams context, gets the SSO token and
     * exchanges it for an API token. Never throws; errors end up in `error`.
     */
    async function start(): Promise<void> {
        if (started.value) return
        started.value = true

        try {
            // Outside Teams, initialize() never resolves. Without a timeout
            // the tab would show "loading" forever.
            try {
                await Promise.race([
                    app.initialize(),
                    sleep(initTimeoutMs).then(() => {
                        throw new Error(`no answer from a Teams host within ${initTimeoutMs} ms`)
                    }),
                ])
            } catch (e) {
                fail(messages.notInTeams, e)
                return
            }

            const context = await app.getContext()
            setTheme(normalizeTheme(context.app.theme))
            subPageId.value = context.page.subPageId ?? null
            hostInfo.value = `host ${context.app.host.name}, client ${context.app.host.clientType}, page ${context.page.id}`

            app.registerOnThemeChangeHandler(next => setTheme(normalizeTheme(next)))

            // Tell Teams the page is up before signing in. Otherwise a later
            // notifyFailure() replaces our UI with the Teams error screen.
            app.notifySuccess()

            await authenticate()
        } catch (e) {
            if (!error.value) fail(messages.loadFailed, e)
        } finally {
            ready.value = true
        }
    }

    async function authenticate(): Promise<void> {
        let entraToken: string
        try {
            entraToken = await authentication.getAuthToken()
        } catch (e) {
            // Silent SSO fails without consent. Ask once, then retry.
            lastError.value = describe(e)
            await requestConsent()
            entraToken = await withRetry(() => authentication.getAuthToken())
        }

        const session = await exchange(entraToken)
        token.value = session.token
        expiresAt.value = session.expires_at
        user.value = session.user
    }

    async function requestConsent(): Promise<void> {
        await authentication.authenticate({
            url: new URL('/teams/consent', window.location.origin).toString(),
            width: 600,
            height: 535,
        })
    }

    async function withRetry<T>(fn: () => Promise<T>): Promise<T> {
        let last: unknown
        for (let i = 0; i < CONSENT_RETRIES; i++) {
            try {
                return await fn()
            } catch (e) {
                last = e
                if (i < CONSENT_RETRIES - 1) await sleep(CONSENT_DELAY_MS)
            }
        }
        throw last
    }

    async function exchange(entraToken: string): Promise<TeamsSession<TUser>> {
        try {
            return await $fetch<TeamsSession<TUser>>(config.ssoEndpoint, {
                baseURL: config.apiBase || undefined,
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: { token: entraToken },
            })
        } catch (e) {
            const status = statusOf(e)
            if (status === 401) fail(messages.invalidToken, e)
            else if (status === 403) fail(messages.noAccount, e)
            else fail(messages.signInFailed, e)
            throw e
        }
    }

    /**
     * API call with the tab's bearer token. On 401 the token is refreshed
     * once through SSO and the call is repeated.
     */
    async function api<T = unknown>(path: string, opts: FetchOptions = {}): Promise<T> {
        const call = () => $fetch<T>(path, {
            baseURL: config.apiBase || undefined,
            ...opts,
            headers: {
                Accept: 'application/json',
                ...(opts.headers as Record<string, string> | undefined),
                ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
            },
        }) as Promise<T>

        try {
            return await call()
        } catch (e) {
            if (statusOf(e) !== 401 || !token.value) throw e
            token.value = null
            await authenticate()
            return await call()
        }
    }

    /** Switches to another tab of the same Teams app. Needs `teams.appId`. */
    async function openTab(pageId: string, sub?: string): Promise<void> {
        await pages.navigateToApp({ appId: config.appId, pageId, subPageId: sub })
    }

    /**
     * Opens a URL in the system browser. A plain link inside the Teams iframe
     * is swallowed by the client.
     */
    async function openLink(url: string): Promise<void> {
        try {
            await app.openLink(url)
        } catch {
            window.open(url, '_blank', 'noopener')
        }
    }

    return {
        token,
        expiresAt,
        user,
        theme,
        subPageId,
        error,
        ready,
        diagnose,
        start,
        api,
        openTab,
        openLink,
    }
}
