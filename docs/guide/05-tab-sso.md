# 05 Tab with SSO

A page of your own that Teams loads in an iframe and that recognises the signed-in user without a second login.

## Front end

The package is `@microsoft/teams-js`, version 2.19 or later. Older versions are no longer accepted for new apps and updates.

```js
import { app, authentication } from "@microsoft/teams-js";

await app.initialize();

const token   = await authentication.getAuthToken();
const context = await app.getContext();

// exchange the Entra token for a session of your own application
await fetch("/api/teams/auth/sso", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ token }),
});
```

### With the Nuxt layer

`nuxt-ms-teams` wraps this. Add it to `extends` in `nuxt.config.ts` and set the runtime config `teams.apiBase` and `teams.ssoEndpoint`. `useTeams()` then initialises TeamsJS, fetches the SSO token, exchanges it at `POST {route_prefix}/auth/sso`, retries after consent (three attempts, one second apart), reads `subPageId` from the deep link and maps the Teams theme onto the colour mode. The user type is generic (`useTeams<User>()`).

Outside Teams, `app.initialize()` never resolves. `useTeams()` puts a timeout in front and shows a clear message instead of loading forever. A diagnostic line with host, client type and last error is shown in the tab itself, because hardly anyone can reach the developer tools of the desktop client.

The layer also brings a `teams` layout without your application's navigation, a page `/teams/consent` that completes the consent dialog, and a route rule with the `frame-ancestors` header on `/teams/**`. Your tabs are ordinary pages of your application under `/teams/…`. The routes are client-only, so statically generated sites can use the layer too.

Start the sign-in in the layout, not in the pages, and wrap pages in `<NuxtLayout>` in `app.vue`. The pitfall journal has the reason (tab stuck at "Loading").

### Context fields

Field names changed from v1 to v2. These are the ones people trip over:

| v1 | v2 |
|---|---|
| `userObjectId` | `context.user.id` |
| `tid` | `context.user.tenant.id` |
| `upn` | `context.user.userPrincipalName` |
| `entityId` | `context.page.id` |
| `subEntityId` | `context.page.subPageId` |
| `hostClientType` | `context.app.host.clientType` |

### Reach of the token

`getAuthToken()` returns a token consented only for the basic claims (profile, email, `offline_access`, OpenID). For Graph calls on behalf of the user (`User.Read`, `Mail.Read`) you need the on-behalf-of flow. If your application keeps its own data, you do not; the token is then a pure proof of identity, and the OBO section below does not apply. The package uses the token that way.

`getAuthToken` fails for anonymous users.

## Token validation in the back end

The package does this in `POST {route_prefix}/auth/sso`. The endpoint answers 401 for an invalid token and 403 when no user can be matched; the reason only goes to the log. It is rate-limited through a named limiter.

Metadata and keys:

```
https://login.microsoftonline.com/{tenant}/v2.0/.well-known/openid-configuration
https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys
```

What to check:

- The signature, via `kid` from the header, RS256. v2.0 tokens only carry `kid`, no `x5t`.
- `aud` equals your client ID or the `api://` URI (`client_id` or `app_id_uri`). Accepting tokens issued for another resource builds you a confused deputy.
- `iss` equals `https://login.microsoftonline.com/{tid}/v2.0`, where `{tid}` exactly matches the `tid` claim. With tenant-independent metadata the endpoint returns a template containing `{tenantid}`, which you replace first and then compare exactly.
- `tid` against the list of allowed tenants (`allowed_tenants`).
- `exp` / `nbf` with the usual five minutes of leeway.

Typical claims of a Teams SSO token: `aud`, `iss`, `iat/nbf/exp`, `azp`, `name`, `oid`, `preferred_username`, `scp`, `sub`, `tid`, `ver: "2.0"`.

Things that go wrong:

- Key rollover. Refresh the JWKS cache at least daily, or access breaks at the next rollover.
- ⚠ Do not check `azp` against the Teams client IDs. Nothing requires it, and it breaks as soon as Microsoft adds hosts.
- Checking `scp == access_as_user` makes sense but is not documented as required. ⚠ The scope name itself is a convention.
- If the UPN is missing from the token, add it in Entra as an optional claim.

A decoded token is not authentication. Without signature validation you have an open endpoint that accepts any self-made JWT.

## Session

After validation the endpoint hands out a session for your application. The class that issues it is replaceable (`IssuesTabSession`, bound through `session_issuer`). The default issues a Sanctum token with the ability `teams` and a lifetime of eight hours. Sanctum is therefore suggested, not required; without Sanctum and without your own binding, the endpoint stops with an explicit message.

## Resolving the identity

Build the mapping from Entra user to your own user as a replaceable strategy, not hard-wired. An order that has held up:

1. `oid` + `tid`. Stable, survives name and mail changes.
2. `preferred_username` / `upn` against your own email address. Only on first contact; after that, store the `oid`.
3. A business claim, if the target system has its own person key (an employee number or similar). That has to be configured in Entra as an optional claim, which is a request to someone else's IT and should be made early.

No match means no access. Do not create an account on suspicion.

In the package the strategy is the interface `ResolvesTeamsIdentity`, bound through `identity_resolver`. The bundled `MailIdentityResolver` implements step 2 (case-insensitive, email column configurable). Once matched, the identity is stored in `teams_identities` with `tenant_id` and `aad_object_id`, which is step 1 from then on. For step 3, bind your own class. To link an account by hand, use `php artisan teams:link {user} {upn}`.

## On-behalf-of, if you do need Graph data

```http
POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
Content-Type: application/x-www-form-urlencoded

grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer
&client_id=<client-id>
&client_secret=<secret>
&assertion=<token from getAuthToken()>
&scope=https://graph.microsoft.com/user.read+offline_access
&requested_token_use=on_behalf_of
```

Never return the OBO token to the client. If consent is missing, the answer is `invalid_grant` or `interaction_required` (`AADSTS65001`). Only the client can resolve it, by opening `authentication.authenticate` with `?prompt=consent&scope=…`.

After that there is a propagation window of a few seconds. Microsoft's own sample retries three times with growing waits, starting at one second. Without the retry, the user sees an error on the first attempt and not on the second, which takes a long time to track down.

Apps with their own signing keys cannot serve as middle-tier API in the OBO flow.

The package does not implement OBO. If you need it, add it in your application.

## Framing

`X-Frame-Options` must not be set on the tab route. Use this instead:

```
Content-Security-Policy: frame-ancestors 'self'
  https://*.cloud.microsoft
  https://teams.microsoft.com https://*.teams.microsoft.com
  https://*.microsoft365.com https://*.office.com
  https://outlook.office.com https://outlook.office365.com
  https://outlook-sdf.office.com https://outlook-sdf.office365.com;
```

`*.cloud.microsoft` is the target domain of an ongoing migration; the others stay for compatibility. The formerly common `*.skype.com` is no longer in the documentation. ⚠ No separate domain is named for Teams mobile, and the list is nowhere explicitly declared complete.

The Nuxt layer sets this header on `/teams/**`. If a reverse proxy sits in front, make sure it neither adds `X-Frame-Options` nor drops the header (see the nginx entry in the pitfall journal).

Second rule: sign-in pages do not render in an iframe (clickjacking protection). A tab authenticates by token, never by redirecting to a login page. If you overlook this, you search for the error in SSO instead of in framing.

A white tab almost always has one of three causes: framing headers, a missing `validDomains` entry, or a failed silent SSO without fallback.
