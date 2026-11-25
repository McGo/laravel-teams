# 01 Decisions

There are five ways to get from an external application into Teams. They do not build on each other. Three share a common foundation, two stand apart.

```
 Foundation:  Teams app (manifest)  +  Entra app registration
                                   │
              ┌────────────────────┼─────────────────────┐
              ▼                    ▼                     ▼
          Bot (3)          Activity feed (4)       Personal tab (5)
   + Azure Bot resource     + app installed            + SSO
                                   └── deep link ────────►

 Separate, no foundation:
   (1) Workflow → channel      since May 2026 only via Power Automate
   (2) Graph chat message      delegated only, i.e. on behalf of a person
```

The package covers routes 3, 4 and 5. Routes 1 and 2 are out of scope for the reasons below.

## Which route for which purpose

| Purpose | Route |
|---|---|
| Post into a channel, same content for everyone | 1 |
| Personal reminder under the app's identity | 4 |
| Show your own UI inside Teams | 5 |
| Let people decide in Teams (accept, approve) | 3 |
| Automated message on behalf of a user | not workable, see below |

## What each route costs

Route 4 is the cheapest. It needs no Azure resource and has no fees. The Teams Graph APIs have not been metered since 25 August 2025, and `sendActivityNotification` was never billed. With resource-specific consent (RSC) the admin consent round can even be skipped. The trade-off: the app has to be installed for the recipient, and the text is a teaser, not a stage.

Route 5 costs configuration rather than code. On the server it is two things, validating a token and setting a CSP header. The effort sits in the Entra registration: application ID URI, token version, seven pre-authorized client IDs.

Route 3 is the heaviest piece in effort, not in money. The Azure Bot resource is free for Teams-only use (standard channel, unlimited messages, no base fee), and the bot code runs inside your own application, not in Azure. The work is in a permanently reachable endpoint, validation of inbound requests, keeping track of conversation IDs and cards with actions. If you only want to notify, skip it entirely.

In the package, `TeamsActivityChannel` is route 4, the `/auth/sso` endpoint plus the Nuxt layer is route 5, and the messaging endpoint plus `TeamsBotChannel` is route 3. You can enable them independently: without `bot.app_id` the messaging endpoint answers 404 and the manifest has no `bots` entry.

## Two routes you can drop

Route 1 used to be two lines of code (an incoming webhook into a channel). The Office 365 connectors were switched off in May 2026. The successor via Power Automate works, but three properties are worth knowing. Everything appears as the Flow bot rather than under your own identity. Cards render without post-back buttons. And the flow belongs to a user account; when that person leaves the organisation, the flow is orphaned.

Route 2 (Graph writes a chat or channel message) only works with delegated permissions. Application permissions for it are only granted for migration scenarios. For automated notifications from a backend, the route is not available.

## The question to ask first

The first question is not which route, but whether the app will reach the recipients' devices. Routes 3 and 4 only deliver when the Teams app is installed for the recipient. That can be solved by an admin policy or via Graph, and both require the app to be in the tenant's org catalog. A sideloaded package is not enough for programmatic installation.

That chain (package, org catalog, installation, delivery) is where projects get stuck, and every link in it belongs to someone else. Put it in the first project week, not the last. [07 Admin checklist](07-admin-checklist.md) is written so you can hand it to the tenant administrator on day one.
