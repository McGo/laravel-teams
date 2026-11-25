# Guide: Microsoft Teams for a Laravel + Nuxt application

This guide covers what it takes to connect your own web application to Microsoft Teams: notifications in the activity feed, a personal app with tabs and single sign-on, and a bot that receives messages and card actions. It explains the Microsoft side in full, including the raw HTTP calls, and points out where `mirkohaaser/teams` and `nuxt-ms-teams` take over.

Technical facts are as of July 2026 unless a page says otherwise. Microsoft changes this area often. The Office 365 connectors were switched off in May 2026, and the Bot Framework SDK was archived at the end of 2025. Places marked ⚠ are the ones most likely to change; check them against the current Microsoft documentation before you rely on them.

The package is pre-release. Config keys, commands and class names follow the package concept and can still change before 1.0.

## Order

The pages follow the order in which the work makes sense. The first steps happen outside the code and need lead time with the administrator of the target tenant, so start them in week one.

| # | Page | Contents |
|---|---|---|
| 01 | [Decisions](01-decisions.md) | which route serves which purpose, and what each one costs |
| 02 | [Entra and tenant setup](02-entra-setup.md) | app registration, permissions, pre-authorized client IDs, distribution, bot resource |
| 03 | [App manifest](03-manifest.md) | template, generation with `teams:manifest`, limits |
| 04 | [Activity feed](04-activity-feed.md) | endpoints, payloads, throttling, deep links |
| 05 | [Tab with SSO](05-tab-sso.md) | TeamsJS, token validation, identity, framing |
| 06 | [Bot](06-bot.md) | inbound validation, proactive messages, cards with actions |
| 07 | [Admin checklist](07-admin-checklist.md) | the page you forward to the tenant administrator |
| 08 | [Local development](08-local-development.md) | tunnel, playground, `Teams::fake()` |
| 90 | [Pitfalls](90-pitfalls.md) | the journal: what cost time, and what fixed it |

If you only want to notify users, you need 02, 03 and 04. Add the tab (05) when a notification should lead somewhere. The bot (06) pays off once people are supposed to make decisions inside Teams.

## Where the package fits

| Manual work in this guide | Package replacement |
|---|---|
| Copying IDs and secrets into your code | `config/teams.php`, filled from the environment |
| Editing a manifest template with placeholders | `manifest.tabs` and `manifest.activity_types` in the config, `php artisan teams:manifest` |
| Installing the app per user via Graph | `php artisan teams:install {user}` and the install step inside `TeamsActivityChannel` |
| Validating Bot Connector tokens | `POST {route_prefix}/bot/messages` |
| Validating the tab SSO token and issuing a session | `POST {route_prefix}/auth/sso` |
| Routing messages and card actions | `Teams::onMessage()`, `Teams::command()`, `Teams::action()` |
| Sending feed notifications and bot messages | Laravel notifications via `TeamsActivityChannel` (`toTeamsActivity()`) and `TeamsBotChannel` (`toTeamsBot()`) |
| TeamsJS init, token exchange, consent retry, CSP | Nuxt layer `nuxt-ms-teams` with `useTeams()` |
| Checking a fresh setup | `php artisan teams:check`, `php artisan teams:send-test {user}` |
| Linking a user by hand | `php artisan teams:link {user} {upn}` |

`{route_prefix}` stands for the config key `routes.prefix`, default `api/teams`. Every value in `config/teams.php` is read from the environment (`TEAMS_TENANT_ID`, `TEAMS_CLIENT_ID`, `TEAMS_CLIENT_SECRET` and so on); the published config file lists the variable names.
