# mirkohaaser/teams

Microsoft Teams for Laravel apps with a Nuxt front end: a bot that receives messages and card actions and can write on its own, notifications in the Teams activity feed, and a personal app with several tabs and single sign-on.

The code is extracted from an application that has run this setup in production since July 2026. It talks to the Bot Connector and Microsoft Graph over plain HTTP. There is no Bot Framework SDK for PHP, and this package does not need one.

**Status:** concept stage. Nothing is released yet. See [`docs/concept/00-ueberblick.md`](docs/concept/00-ueberblick.md) (German).

## What you get

- `mirkohaaser/teams` (Composer): token validation for the Bot Connector and for tab SSO, a messaging endpoint with a registry for commands and card actions, a bot client for proactive messages, notification channels for the activity feed and the bot chat, manifest generation from config, and a test fake.
- `@mirkohaaser/nuxt-teams` (npm): a Nuxt layer with `useTeams()`, a consent page, a bare `teams` layout and the CSP header that lets Teams frame your tabs.

## Scope

Single-tenant registrations only, one Entra app and one Teams app per installation. Microsoft stopped creating multi-tenant bots on 31 July 2025. Posting to channels through Workflows/Power Automate is out of scope.

## License

MIT
