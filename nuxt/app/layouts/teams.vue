<script setup lang="ts">
/**
 * Bare frame for Teams tabs: one column, no app navigation (Teams draws the
 * tab bar from the manifest), colours from the Teams theme.
 *
 * Sign-in starts here, not in the pages: the layout hides its content until
 * sign-in is done, so a page that started it would never be mounted.
 *
 * Slots: default, `loading`, `error` ({ error, diagnose }).
 */
const { theme, ready, error, diagnose, start } = useTeams()
const { messages } = useAppConfig().teams

onMounted(start)
</script>

<template>
    <div :data-theme="theme" class="teams-shell">
        <main class="teams-main">
            <slot v-if="!ready" name="loading">
                <p class="teams-hint">{{ messages.loading }}</p>
            </slot>
            <slot v-else-if="error" name="error" :error="error" :diagnose="diagnose">
                <p class="teams-error">{{ error }}</p>
                <p class="teams-hint teams-diagnose">{{ diagnose }}</p>
            </slot>
            <slot v-else />
        </main>
    </div>
</template>

<style>
/* Teams-like tokens, scoped to the tab shell. Override them in your own CSS. */
.teams-shell {
    --teams-bg: #ffffff;
    --teams-fg: #242424;
    --teams-muted: #616161;
    --teams-line: #e0e0e0;
    --teams-accent: #5b5fc7;
    --teams-card: #f5f5f5;
    --teams-danger: #c4314b;

    min-height: 100vh;
    background: var(--teams-bg);
    color: var(--teams-fg);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    font-size: 14px;
}

.teams-shell[data-theme='dark'] {
    --teams-bg: #1f1f1f;
    --teams-fg: #ffffff;
    --teams-muted: #adadad;
    --teams-line: #383838;
    --teams-accent: #7f85f5;
    --teams-card: #292929;
    --teams-danger: #f9526b;
}

.teams-shell[data-theme='contrast'] {
    --teams-bg: #000000;
    --teams-fg: #ffffff;
    --teams-muted: #ffffff;
    --teams-line: #ffffff;
    --teams-accent: #ffff01;
    --teams-card: #000000;
    --teams-danger: #ffff01;
}

.teams-main {
    padding: 1rem;
    max-width: 46rem;
}

.teams-hint {
    color: var(--teams-muted);
}

.teams-error {
    color: var(--teams-danger);
}

.teams-diagnose {
    font-size: 12px;
}
</style>
