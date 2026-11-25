<script setup lang="ts">
import type { DemoUser } from '~/types'

definePageMeta({ layout: 'teams' })

interface Item {
    id: number
    title: string
}

const { user, subPageId, api } = useTeams<DemoUser>()

const items = ref<Item[]>([])
const failed = ref(false)

// The layout renders this page only after sign-in, so the token is set.
onMounted(async () => {
    try {
        items.value = await api<Item[]>('/api/items')
    } catch {
        failed.value = true
    }
})
</script>

<template>
    <section>
        <h1>Items</h1>
        <p>Signed in as {{ user?.name }}. subPageId: {{ subPageId ?? '(none)' }}</p>
        <p v-if="failed" class="teams-error">Could not load /api/items.</p>
        <ul v-else>
            <li v-for="item in items" :key="item.id">
                {{ item.title }}
            </li>
        </ul>
    </section>
</template>
