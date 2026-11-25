<script setup lang="ts">
import type { DemoUser } from '~/types'

definePageMeta({ layout: 'teams' })

const { user, subPageId, theme, diagnose, openTab } = useTeams<DemoUser>()
</script>

<template>
    <section>
        <h1>Home</h1>
        <p>Signed in as {{ user?.name }} ({{ user?.email }}).</p>
        <p>subPageId: {{ subPageId ?? '(none)' }} · theme: {{ theme }}</p>
        <button type="button" @click="openTab('items', 'from-home')">
            Open items tab
        </button>
        <p class="teams-hint">{{ diagnose }}</p>
    </section>
</template>
