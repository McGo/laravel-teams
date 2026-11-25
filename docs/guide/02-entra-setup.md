# 02 Entra and tenant setup

Three objects need to be kept apart. Mixing them up is one of the most common sources of errors.

1. The Entra app registration holds identity and permissions: client ID, secret, Graph permissions, the scope exposed for SSO.
2. The Teams app (manifest package) is what users install. It points to the Entra app through `webApplicationInfo`.
3. The Azure Bot resource exists only if you build a bot (route 3).

One installation of the package belongs to exactly one Entra app registration and one Teams app, both created in the tenant of the users. If several applications use the package, they share the code, never a registration.

## App registration

Register the app as single-tenant. A multi-tenant registration brings consent effort and tenant separation that you rarely need, and the package does not separate data per tenant.

Keep the client secret (or certificate) out of the repository. In the package it goes into the environment and ends up in `client_secret`; `tenant_id` and `client_id` come from the app's overview page.

Set `requestedAccessTokenVersion` to `2` in the app manifest of the registration (in older portal versions: `accessTokenAcceptedVersion`). Otherwise v1 tokens arrive and validation against the v2.0 metadata fails.

### Application ID URI

```
without bot:  api://<fully-qualified-domain>/<client-id>
with bot:     api://<fully-qualified-domain>/botid-<client-id>
```

Write the domain in lower case, one domain per app, no wildcards. If you add a bot later, you have to change the URI and rewire SSO. Ask "will there be a bot?" before registering, and when in doubt use the bot form right away. The value goes into `app_id_uri`.

Example for a tab served from `teams.contoso.com`:

```
api://teams.contoso.com/botid-00000000-0000-0000-0000-000000000000
```

Under "Expose an API", add a scope. `access_as_user` is the usual name; it is a convention, not a requirement.

### Pre-authorized client IDs

For Teams to issue a token silently, the exposed scope has to be pre-authorized for the host applications. There are more than the two obvious ones. If you only add Teams, the tab stays white as soon as someone opens the app in the Microsoft 365 app or in Outlook.

| Host | Client ID |
|---|---|
| Teams desktop, mobile | `1fec8e78-bce4-4aaf-ab1b-5451cc387264` |
| Teams web | `5e3ce6c0-2b1f-4285-8d4b-75ee78787346` |
| Microsoft 365 web | `4765445b-32c6-49b0-83e6-1d93765276ca` |
| Microsoft 365 desktop | `0ec893e0-5785-4de6-99da-4ed124e5296c` |
| Microsoft 365 mobile, Outlook desktop | `d3590ed6-52b3-4102-aeff-aad2292ab01c` |
| Outlook web | `bc59ab01-8403-45c6-8796-ac3ef710b3e3` |
| Outlook mobile | `27922004-5251-4030-b22d-91ecd9a37ea4` |

⚠ This list grows when Microsoft adds hosts. Compare it with the current documentation before each project.

### One Entra app per Teams app

Do not share an Entra app ID across several Teams apps. Otherwise a notification can no longer be attributed unambiguously and has to carry an extra `teamsAppId`.

## Permissions

| Purpose | Permission | Type | Consent |
|---|---|---|---|
| Notification to a user | `TeamsActivity.Send.User` | RSC, application | with the installation |
| Notification to a chat / team | `TeamsActivity.Send.Chat` / `.Group` | RSC, application | with the installation |
| Tenant-wide fallback | `TeamsActivity.Send` | application (Graph) | admin |
| Install the app for users, if it declares RSC | `TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All` | application (Graph) | admin |
| Install an app without RSC | `TeamsAppInstallation.ReadWriteSelfForUser.All` | application (Graph) | admin |
| Resolve the catalog ID at runtime (optional) | `AppCatalog.Read.All` | application (Graph) | admin |
| Look up a user's object ID for `teams:link` | `User.Read.All` | application (Graph) | admin |
| Tab SSO | exposed scope, usually `access_as_user` | delegated | user |

The point that matters when you negotiate with someone else's IT department: the three `TeamsActivity.Send.*` permissions are basic RSC permissions and are consented as part of installing the app. They need no consent round of their own.

As soon as the app is to be distributed programmatically rather than by hand, and with user counts in the thousands everyone wants that, the caller needs the installation permission in its consent variant. That becomes the one admin consent you collect from the customer, and it covers both. The package's `teams:install` and the install step in `TeamsActivityChannel` expect exactly this permission; if it is missing, the error message names it.

`User.Read.All` is only needed if you use `teams:link` to look up object IDs. If users always sign in through the tab first, the identity is created there and the permission is not required.

## Distribution in the tenant

Without an installed app there is no delivery, for notifications and bot alike. Three ways, in increasing convenience for the user:

1. Everyone installs the app from the catalog themselves.
2. The administrator distributes and pins it with an app setup policy.
3. The application installs it for the user via Graph.

Option 3 requires the app to be published in the tenant's org catalog (or in the public Teams store). The `teamsAppId` you need is the catalog ID from `/appCatalogs/teamsApps`, not the ID from the manifest. In the package the two live side by side: `app_id` is the manifest ID, `catalog_app_id` the catalog ID.

Sideloading ("Upload a custom app") is not the same as uploading to the catalog ("Upload an app to your org's app catalog", in the Teams admin center under Teams apps > Manage apps > Upload new app). Sideloading is enough for RSC; for proactive installation it is not. In a real rollout there is no reason to use the sideload path, and one reason against it: the client shows both menu entries close together, and afterwards you do not know which installation took effect.

```http
POST /v1.0/users/{userId}/teamwork/installedApps
Content-Type: application/json

{
  "teamsApp@odata.bind": "https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/{teamsAppId}"
}
```

RSC permissions can be consented in the same call:

```json
{
  "teamsApp@odata.bind": "https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/{teamsAppId}",
  "consentedPermissionSet": {
    "resourceSpecificPermissions": [
      { "permissionValue": "TeamsActivity.Send.User", "permissionType": "Application" }
    ]
  }
}
```

The consented permissions must match those in the app definition exactly. Throttling: 30 requests per second per app and tenant. An existing installation answers with `409`, which is not an error.

The package sends this call from `php artisan teams:install {user}` (or `--all` for every linked identity) and from `TeamsActivityChannel` before the first delivery to a recipient.

## Azure Bot resource

Only for route 3. Create it as single-tenant. Multi-tenant bots cannot be created any more since 31 July 2025. Existing ones keep running, and no shutdown date has been announced. The third option is a user-assigned managed identity. The package supports single-tenant only.

Under Channels, add Microsoft Teams. Set the messaging endpoint to `https://<host>/{route_prefix}/bot/messages` (prefix from `routes.prefix`), with the default prefix `https://api.contoso.com/api/teams/bot/messages`. The app ID goes into `bot.app_id`, the password into `bot.app_password`. If the bot uses the same registration as the tab, `bot.app_id` equals `client_id`, which matches the `botid-<client-id>` form of the application ID URI.

### Two switches that get confused

The app type of the bot resource (`MicrosoftAppType`: `SingleTenant`, `UserAssignedMSI`, `MultiTenant`) and the audience of the Entra app registration (`signInAudience`) are independent. Only the first was deprecated. From the Developer Portal documentation: "A bot configured as single tenant in Azure Bot Service can use a multi tenant application."

The tenant the bot resource carries (`MicrosoftAppTenantId`) is the one against which it fetches its own connector token. It has nothing to do, at first, with the tenant of the users who write to the bot.

### Consequence for a service provider with several customers

There are two models.

In the first, everything lives in the customer tenant. Entra app and bot resource are created there. Nobody disputes it, but the customer's IT has to do the setup and is needed again for every change. This is the model the package is built for.

In the second, the registration lives with the service provider. The bot resource is single-tenant in the provider's tenant, the Entra app is registered as multi-tenant, and the Teams app package is rolled out to the customer's catalog (or through the store). The customer only grants admin consent. The bot logic then has to evaluate `tenantId` from each inbound activity and separate data per tenant. The package does not do that.

⚠ The second model is in the documentation, but briefly. Apart from the quoted paragraph there is little, and Microsoft Q&A answers contradict each other. Try it in a pilot tenant before promising it to a customer.

Not documented and therefore not to be assumed: a mixed form with the registration in the customer tenant and the bot resource at the provider. An Azure subscription belongs to exactly one directory, and this combination is not described anywhere.

Also undocumented is a pattern for several app IDs in one bot application. SDK configuration is single-valued; if you want to run one deployment with n registrations, you resolve credentials per inbound request yourself. The store policies add that the bot ID in the manifest must not change between versions and does not support environment variables.

### Managed identity

A user-assigned managed identity necessarily lives in the directory of its subscription and does not survive a directory change. Whether users from other tenants can talk to such a bot is not documented. For multi-tenant scenarios the type is ruled out; it lacks the lever that a multi-tenant registration offers.

### SDK limitation

The `SingleTenant` and `UserAssignedMSI` types are only supported by the C#, JavaScript and Python SDKs, not by Java, Composer, the Emulator or Dev Tunnels. If you write the REST calls yourself, as this package does, this does not affect you.

### Cost (checked July 2026): none

Azure Bot Service only bills messages, and only on premium channels (Direct Line, Web Chat). Teams is a standard channel; there, messages are unlimited and free in both tiers. There is no time- or instance-based item, so no base fee. If you host the code yourself and only create a registration with a messaging endpoint, you pay nothing. Pick the F0 tier.

Two side notes. When creating the resource, an Application Insights instance can be provisioned along with it. Deselect it; it is the only place where something does get billed. And the difference between F0 and S1 for Teams-only use is a question of SLA and support, not capacity. ⚠ The exact SLA terms are on the pricing page and were not checked for this guide.

The Azure Bot resource needs an Azure subscription linked to the same Entra tenant. A Microsoft 365 subscription alone does not include one; see the section on small tenants in [90 Pitfalls](90-pitfalls.md#small-tenants-microsoft-365-business-basic).

## Development environment

Teams and Graph require reachable HTTPS URLs, so local development needs a tunnel. If the target tenant is not ready yet, a Microsoft 365 developer tenant can serve as a lab, where sideloading and consent work without asking anyone. ⚠ Eligibility for the free developer sandbox has been tightened in the past; check whether you qualify.

Once the values are in the environment, `php artisan teams:check` checks each point on its own line: config complete, Graph token obtainable, bot token obtainable, catalog ID set, manifest buildable. Failures name the likely cause from the pitfall journal.

## Checklist for the conversation with the customer's IT

1. Are custom apps allowed (org-wide setting and app setup policy)?
2. Who uploads the package to the org catalog, and how long does that take?
3. Will the app be distributed by policy, or do we install it via Graph?
4. Who grants admin consent for the Graph permissions?
5. Which claim reliably identifies an employee, and does it have to be added as an optional claim?
6. Is there a works council or employee representation question? Notifications to employees about performance or compliance status can require approval. Clarify that before building, not after.

The administrator's side of this list, with the exact clicks, is in [07 Admin checklist](07-admin-checklist.md).
