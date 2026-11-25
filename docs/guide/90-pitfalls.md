# 90 Pitfalls

This journal is the part of the guide that the Microsoft documentation does not cover. The API calls are documented there; where you lose three hours is not.

Entries are written during implementation, not afterwards. An entry is created the moment something takes longer than expected, with symptom, cause and what actually fixed it. Reconstructing from memory gives half the truth. If you hit something new while using the package, a pull request with an entry is welcome.

## Format

```markdown
### <short title>
- Symptom: what you see
- Cause: what was actually going on
- Fix: what helped
- Cost: rough, in hours
- Area: Bot / Activity feed / Tab / Setup
```

## Experienced in production

### 403 "Missing role permissions" on the first delivery
- Symptom: `sendActivityNotification` answers `403 Forbidden`, "API requires one of 'TeamsActivity.Send, TeamsActivity.Send.User'. Roles on the request ''. Resource specific consent grants on the request ''." The app token arrives fine and the RSC permission is in the manifest.
- Cause: RSC is granted when the app is installed, not when it is registered. Before the first installation there is no grant, and the empty string in the error message says exactly that.
- Fix: build the package, install it in Teams, send again. The order registration, manifest, installation, delivery is mandatory. `TeamsActivityChannel` installs the app before the first delivery for this reason.
- Cost: a few minutes, if you read the message correctly.
- Area: Activity feed, Setup

### The desktop client shows an old version and never asks for a new one
- Symptom: the tab works in the web client, the desktop client shows an earlier version of the page. Removing the app, restarting the client, clearing the cache, uploading a new package to the catalog: nothing changes.
- Cause: the client keeps the app definition in its Edge WebView storage. It does not resolve it again and no longer requests the content URL at all. During the whole search, the web server's access log showed not a single request from it.
- Fix: with the client closed, delete the `EBWebView` directory (macOS: `~/Library/Containers/com.microsoft.teams2/Data/Library/Application Support/Microsoft/MSTeams/EBWebView`). The `Caches` directory next to it is not enough; the web content is not there. Before that, raise the manifest version and change the content URLs (`?v=2`). Teams keys its definition by version, and a new URL cannot be answered from an old snapshot.
- Cost: about two hours, most of it spent looking in the wrong place.
- What would have saved time: check the access log first to see whether the client requests the page at all. If nothing arrives, every server-side explanation is ruled out, and you stop poking at CSP, cache headers or delivery. A second useful cross-check: `GET /users/{id}/teamwork/installedApps?$expand=teamsAppDefinition` shows which version is actually installed for the user. If it matches the catalog and the client still shows something else, the problem is purely local.
- Area: Tab, Setup

### Removing the app in the client fails with "problem removing"
- Symptom: the user cannot uninstall the app. The message asks to try again, and the second attempt fails too.
- Cause: the app was installed for the user by policy or through Graph. What the user did not install, the user cannot remove.
- Fix: uninstall through the Teams admin center (Manage apps > app > edit installations) or through Graph.
- Cost: not recorded.
- Area: Bot, Activity feed, Tab

### The tab shows the application's start page instead of the tab
- Symptom: the tab briefly loads white and then shows the normal website.
- Cause: usually the web server does not deliver the SPA entry file for the tab path at all, but falls back to the start page, because there is no rule of its own for the path and the catch-all rule serves `index.html`. It looks like an application bug but is server configuration.
- Fix: a separate location for the tab path with fallback to the SPA entry file. Checking the size of the delivered file is enough to confirm it.
- Cost: not recorded.
- Area: Tab

### CSP header missing despite correct nginx configuration
- Symptom: the location has `add_header Content-Security-Policy`, the response does not.
- Cause: when `try_files` ends with a URI, nginx performs an internal redirect. The response is then produced in the fallback location, and `add_header` from the original location no longer applies.
- Fix: `try_files $uri $uri/ /200.html =404;`. With `=404` at the end, the file is served from the same location.
- Cost: half an hour, because the header also served as a deploy indicator and its absence looked like a deploy that had not arrived.
- Area: Tab

### Tab stuck at "Loading" forever (Nuxt)
- Symptom: the frame appears, the content never does. No error message, no console output.
- Cause: two traps in a row. First, `definePageMeta({ layout: … })` only takes effect if `app.vue` wraps the page in `<NuxtLayout>`; otherwise the setting is silently ignored. Second, if the layout hides the page content until sign-in is done, a page that starts sign-in in its own `onMounted` is never mounted. Chicken and egg.
- Fix: `<NuxtLayout>` in `app.vue`, and start sign-in in the layout, not in the pages. The `teams` layout of `nuxt-ms-teams` does this; `app.vue` remains your responsibility.
- Cost: not recorded.
- Area: Tab

### Outside Teams, `app.initialize()` never answers
- Symptom: the page hangs when opened in a normal browser.
- Cause: without a Teams parent window the promise never resolves.
- Fix: put a timeout in front and show a readable message. Otherwise you debug a browser state that does not exist in Teams. `useTeams()` does this.
- Cost: not recorded.
- Area: Tab

### The RSC grant is per user, not per tenant
- Symptom: delivery works for the first user. For the second it is `403` again, with empty "Resource specific consent grants".
- Cause: consent is created with the installation, and the installation is per user. That the app is in the catalog and rolled out "to everyone" by policy is not enough.
- Fix: call `POST /users/{id}/teamwork/installedApps` once per recipient before the first delivery. The call is idempotent; Graph acknowledges an existing installation with `409`, which is not an error.
- Consequence for the rollout: installation belongs in the delivery path as a step of its own, not in a one-time setup. Otherwise new employees never get the app. This is why `TeamsActivityChannel` installs on demand, and why `teams:install --all` exists for the initial rollout.
- Cost: not recorded.
- Area: Bot, Activity feed

### Installation via Graph fails although the permission is granted
- Symptom: `POST /users/{id}/teamwork/installedApps` answers `403` ("To grant consent during app installation, the caller must have one of these permissions: …AndConsent…"), and without `consentedPermissionSet` it answers `400` ("The required permissions have not been consented to by the caller"). The token contains `TeamsAppInstallation.ReadWriteSelfForUser.All`.
- Cause: if the manifest declares RSC permissions, the caller has to be allowed to grant them. The plain installation permission does not cover that, and leaving out the consent is not possible either, because the app needs it.
- Fix: `TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All` (application) with admin consent. The customer then grants exactly one consent, because it covers the RSC grant as well.
- Cost: not recorded.
- Area: Activity feed, Setup

### The catalog ID is not the manifest ID
- Symptom: `teamsApp@odata.bind` with the ID from the manifest leads nowhere.
- Cause: the binding expects the catalog ID from `/appCatalogs/teamsApps`, a different GUID that differs per tenant.
- Fix: either grant `AppCatalog.Read.All` and resolve it at runtime, or copy the ID once from the address bar of the app's detail page in the Teams admin center and put it into `catalog_app_id`. With a customer, the second option is cheaper: one consent less. `teams:check` reports a missing `catalog_app_id`.
- Cost: not recorded.
- Area: Setup

### Sideload or catalog, and what the difference really is
- Symptom: after uploading as a custom app, the RSC grant stays empty, and the obvious guess is that sideloading is not enough for it.
- Cause: that guess is wrong. The documentation says explicitly that RSC permissions are granted "as part of the app installation process", and a Microsoft employee confirmed for `TeamsActivity.Send.User`: "It should work in a sideloaded application installed in the personal scope of a user." The catalog is required for proactive installation via Graph, not for RSC.
- Fix: if you distribute through the catalog anyway, the question resolves itself, but do it on purpose and not as a supposed necessity. Two uploads sit close together in the client: "Upload a custom app" (just for yourself) and "Upload an app to your org's app catalog" (catalog, only visible to administrators; in the Teams admin center under Teams apps > Manage apps).
- Cost: not recorded.
- Area: Setup

### You cannot prove the missing RSC grant app-only
- Symptom: you want to check whether the grant exists and cannot get at it.
- Cause: `GET /beta/users/{id}/permissionGrants` itself requires one of the `TeamsAppInstallation.*` permissions, and `GET /beta/teamwork/teamsAppSettings` answers app-only with `412 PreconditionFailed`, "Requested API is not supported in application-only context". To prove that one permission is missing, you would first need another one.
- Fix: either grant a diagnostic permission with admin consent, or take the detour through a delegated call. For plain troubleshooting it is usually cheaper to redo the installation.
- Cost: not recorded.
- Area: Activity feed, Setup

### RSC is not updated on a manifest update
- Symptom: permissions added to the manifest, app updated, grant stays empty.
- Cause: an update does not add new RSC permissions to existing installations. Reported several times, never conclusively answered by Microsoft.
- Fix: uninstall the app completely and install it again; do not update.
- Cost: not recorded.
- Area: Activity feed

### App token works, delivery does not, and everything was done right
- Symptom: the client credentials flow returns a token (just under 2,000 characters), the first real call fails.
- Cause: the token only proves the registration, not the permission. Roles are claims in the token; without a grant they are empty.
- Fix: treat fetching the token and delivering as two separate checks. The token test is still valuable, because it separates "secret or tenant wrong" from "permission missing". `teams:check` and `teams:send-test` are split along this line.
- Cost: not recorded.
- Area: Setup

### Links from the tab stay in the tab instead of opening the browser
- Symptom: a link in the personal tab that points to a part of your own website (say `/app/…`) does not open the browser but navigates the tab frame itself. Several such links run into each other, and you keep ending up in the tab. Links to other hosts (an issue tracker, for example) open the browser correctly, even through the same `app.openLink`.
- Cause: the tab content loads from the production domain, which is therefore listed in `validDomains`. Teams treats every URL on a `validDomains` domain as part of the app and shows it in the tab or stage frame, including via `app.openLink`. Because website and tab share the domain, no internal link can get out. Other hosts are not in `validDomains` and therefore escape.
- Fix, option A (recommended): serve the tab from its own subdomain (for example `teams.contoso.com`) and list only that one in `validDomains`. The website domain (`www.contoso.com`) is then foreign to Teams, and links to it open in the system browser via `app.openLink` or `window.open`. This is the clean solution and the default for new projects. It costs a DNS record, a server block and certificate for the subdomain, switching `contentUrl`, `validDomains` and the application ID URI (which follows the tab host) in the manifest, and a new manifest version that users pick up once (see "The desktop client shows an old version" above).
- Fix, option B (workaround without a subdomain): open website links deliberately with `window.open(url, '_blank')` instead of `app.openLink`; the Teams clients hand that to the external browser. Behaviour differs per client (desktop, web, mobile), so use it only as a transition and verify it in the real clients. One deployment used option B on purpose; another used option A from the start.
- Cost: about an hour for the subdomain, plus the usual round of manifest redistribution.
- Area: Tab

## From research, not yet hit

The following points are known from the documentation and preliminary research, but have not been experienced first-hand. When one of them strikes during implementation, it gets a symptom and real costs.

### Delivery without effect, no error
- Cause: Teams app not installed for the recipient. Without installation there is neither notification nor bot message.
- Fix: distribution by policy or proactive installation via Graph. The latter requires publication in the org catalog; sideloading is not enough.
- Area: Bot, Activity feed

### White tab
- Cause: usually one of three. Framing headers (`X-Frame-Options` set or `frame-ancestors` incomplete), a missing `validDomains` entry, or a failed silent SSO without fallback.
- Fix: check in that order. The browser's network tab shows the framing case immediately.
- Area: Tab

### SSO works in Teams, not in Outlook or the Microsoft 365 app
- Cause: only the two Teams client IDs are pre-authorized.
- Fix: add all seven, see [02 Entra setup](02-entra-setup.md#pre-authorized-client-ids).
- Area: Tab

### First access after consent fails, the second works
- Cause: propagation window of a few seconds after consent is granted.
- Fix: up to three retries with growing waits. `useTeams()` retries three times, one second apart.
- Area: Tab

### The application ID URI has to be changed later
- Cause: bot added later; with a bot the form is `api://<host>/botid-<client-id>`.
- Fix: decide beforehand whether a bot will come, and when in doubt use the bot form right away. Changing it later means rewiring SSO.
- Area: Setup

### A notification cannot be attributed
- Cause: one Entra app ID shared across several Teams apps.
- Fix: one Entra app per Teams app. If unavoidable, send `teamsAppId` in the payload.
- Area: Activity feed

### Access breaks after weeks without a code change
- Cause: key rollover at Entra or Bot Framework, JWKS cache too old.
- Fix: refresh the cache at least daily. The package caches keys for 24 hours.
- Area: Bot, Tab

### Mass send takes longer than planned
- Cause: 5 requests per second per app and tenant, 20 notifications per user per minute.
- Fix: build the send as a throttled queue, not as a loop. Use the bulk endpoint only when everyone gets the same text.
- Area: Activity feed

### Card looks broken on the phone
- Cause: Adaptive Card version above 1.2; Teams mobile does not support more.
- Fix: stay at 1.2 and use `Action.Submit` buttons. The `Cards` builder does both by default. `Action.Execute` needs 1.4 and was not delivered reliably in practice.
- Area: Bot

### The bot resource cannot be created as multi-tenant
- Cause: no longer possible since 31 July 2025.
- Fix: single-tenant or user-assigned managed identity. That is not a reason to create a separate registration per customer: the app type of the bot resource and the audience of the Entra app are separate switches, and according to the documentation a single-tenant bot may use a multi-tenant app. See [02 Entra setup](02-entra-setup.md#two-switches-that-get-confused).
- Area: Bot

### Assuming "everything has to be in the customer tenant, otherwise SSO fails"
- Cause: a reasoning error. For SSO a multi-tenant Entra app with admin consent in the customer tenant is enough; that is the normal SaaS case. The bot deprecation does not force it either.
- Fix: treat the decision as what it is, a trade-off between dependency on someone else and burden of proof, not a technical necessity. The model with the registration at the service provider is thinly documented, though, and belongs in a pilot tenant before any commitment to a customer. The package itself only supports registrations in the users' tenant.
- Area: Setup

### Text is cut off
- Cause: `templateText` in the manifest is limited to 128 characters, the preview shows 150.
- Fix: design the notification as a teaser, put the content in the tab or on the card. `teams:manifest` rejects longer templates.
- Area: Activity feed

## Small tenants (Microsoft 365 Business Basic)

Everything above comes from one production deployment and from planning for a larger customer tenant with thousands of users. A small tenant on Microsoft 365 Business Basic has not been run through end to end yet. The notes below separate what Microsoft documents from what is still open. Nothing in this section has been tested in a Business Basic tenant.

Documented, not tested here:

- Business Basic includes Teams and Microsoft Entra ID Free. App registrations, exposed scopes, pre-authorized client applications and admin consent are part of Entra ID Free, so steps 1 and 2 of the [admin checklist](07-admin-checklist.md) need no additional licence.
- Business Basic does not include an Azure subscription. The Azure Bot resource needs one in the same tenant. A pay-as-you-go subscription works; it asks for a payment method even when the bot stays on F0 and costs nothing. Activity feed and tab do not need Azure at all, so a small tenant without a bot can skip this entirely.
- Changes to Teams app setup policies can take up to 24 hours to reach users. In a small tenant this is often the first time anyone touches these settings, so expect the delay on the first test.
- Granting admin consent for Microsoft Graph application permissions requires Privileged Role Administrator or Global Administrator. In small tenants that usually means the one global admin account. Use it for the setup steps and keep everyday work on a normal account.

Open, check before relying on it:

- Whether "Let users interact with custom apps" is on or off by default in a newly created small tenant. Do not assume either; check step 3 of the admin checklist.
- Whether RSC consent, `sendActivityNotification` and proactive installation behave any differently with Business Basic licences. Nothing in the documentation suggests so, but it has not been tried.
- Business Basic users have the web and mobile Office apps, not the desktop ones. The Teams desktop client is still available to them. Keep all seven pre-authorized client IDs anyway; an unused entry costs nothing.
- How long a new upload to the org catalog takes to show up for users in a small tenant. There are no measurements for it yet.
