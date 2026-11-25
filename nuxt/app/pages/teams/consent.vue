<script setup lang="ts">
import { app, authentication } from '@microsoft/teams-js'

/**
 * Target of the consent popup. Teams opens it when silent SSO fails. The
 * page sends the user to Entra and reports the result back to the tab.
 */
definePageMeta({ layout: false })

const config = useRuntimeConfig().public.teams
const { messages } = useAppConfig().teams

onMounted(async () => {
    // teams-js v2 needs initialize() in the popup too, before notify*().
    await app.initialize()

    const params = new URLSearchParams(window.location.search)

    if (params.has('error')) {
        authentication.notifyFailure(params.get('error_description') ?? params.get('error') ?? 'consent_failed')
        return
    }

    if (params.has('code') || params.has('admin_consent')) {
        authentication.notifySuccess()
        return
    }

    if (!config.clientId) {
        authentication.notifyFailure('runtimeConfig.public.teams.clientId is not set')
        return
    }

    // Single-tenant apps cannot use /common. Take the tenant from config,
    // then from the Teams context.
    let tenant = config.tenantId
    if (!tenant) {
        try {
            tenant = (await app.getContext()).user?.tenant?.id ?? ''
        } catch {
            tenant = ''
        }
    }

    const url = new URL(`https://login.microsoftonline.com/${tenant || 'organizations'}/oauth2/v2.0/authorize`)
    url.searchParams.set('client_id', config.clientId)
    url.searchParams.set('response_type', 'code')
    url.searchParams.set('response_mode', 'query')
    url.searchParams.set('scope', 'openid profile offline_access')
    url.searchParams.set('redirect_uri', `${window.location.origin}/teams/consent`)
    url.searchParams.set('prompt', 'consent')

    window.location.assign(url.toString())
})
</script>

<template>
    <p>{{ messages.consentPending }}</p>
</template>
