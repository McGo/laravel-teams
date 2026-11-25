# 07 Admin checklist

This page is meant for the administrator of the Microsoft 365 tenant in which the app will run. The developer forwards it; you do not need the rest of the guide to work through it.

The application connects to Microsoft Teams with a bot, notifications in the activity feed and a personal app with tabs and single sign-on. Everything is registered in your tenant and belongs to you. Nothing is shared with other organisations.

The developer will send you three things: the host name of the application (below: `teams.contoso.com` for the tab, `api.contoso.com` for the API), a ZIP file with the Teams app package, and the list of features in use (bot yes/no). Replace the example hosts with the real ones.

Menu labels in the admin centers change from time to time. ⚠ The paths below are as of July 2026.

## Roles you need

| Task | Role |
|---|---|
| App registration in Entra | Application Administrator or Cloud Application Administrator |
| Admin consent for Microsoft Graph application permissions | Privileged Role Administrator or Global Administrator |
| Teams app settings, policies, org catalog upload | Teams Administrator or Global Administrator |
| Azure Bot resource (only if a bot is used) | Contributor on an Azure subscription in this tenant |

## 1. App registration (Entra admin center)

Entra admin center > Identity > Applications > App registrations > New registration.

1. Name: as agreed with the developer. Supported account types: "Accounts in this organizational directory only" (single tenant). No redirect URI.
2. Note the Application (client) ID and the Directory (tenant) ID from the overview page.
3. Certificates & secrets > New client secret. Note the value right away; it is shown only once. Note the expiry date as well, because the app stops working when the secret expires.
4. Expose an API > Application ID URI > Set. With a bot: `api://teams.contoso.com/botid-<client-id>`. Without a bot: `api://teams.contoso.com/<client-id>`. Lower case, no trailing slash.
5. Expose an API > Add a scope: name `access_as_user`, who can consent "Admins and users", state enabled.
6. Expose an API > Add a client application, once for each of these seven IDs, each with the `access_as_user` scope ticked. They are Microsoft's own Teams, Microsoft 365 and Outlook clients.

   ```
   1fec8e78-bce4-4aaf-ab1b-5451cc387264   Teams desktop, mobile
   5e3ce6c0-2b1f-4285-8d4b-75ee78787346   Teams web
   4765445b-32c6-49b0-83e6-1d93765276ca   Microsoft 365 web
   0ec893e0-5785-4de6-99da-4ed124e5296c   Microsoft 365 desktop
   d3590ed6-52b3-4102-aeff-aad2292ab01c   Microsoft 365 mobile, Outlook desktop
   bc59ab01-8403-45c6-8796-ac3ef710b3e3   Outlook web
   27922004-5251-4030-b22d-91ecd9a37ea4   Outlook mobile
   ```

7. Manifest (of the registration): set `requestedAccessTokenVersion` to `2`. Older views call it `accessTokenAcceptedVersion`.
8. Token configuration: only if the developer asks for it, add `upn` or another claim as an optional claim to the access token.

## 2. Permissions and admin consent

App registration > API permissions > Add a permission > Microsoft Graph > Application permissions:

- `TeamsAppInstallation.ReadWriteAndConsentSelfForUser.All`, so the application can install its own Teams app for users and consent its notification permission in the same step.
- `User.Read.All`, only if the developer wants to link accounts by hand.
- `AppCatalog.Read.All`, only if agreed. Usually not needed, because you send the catalog ID instead (step 4).

Then press "Grant admin consent for <your organisation>". The status column must show a green tick for every entry.

The notification permission itself (`TeamsActivity.Send.User`) is not added here. It is declared in the app package and granted per user when the app is installed.

## 3. Allow custom apps in Teams (Teams admin center)

A custom app is any app that is not from the public store. Two settings must both allow it.

1. Teams apps > Manage apps > Actions > Org-wide app settings: under Custom apps, turn on "Let users interact with custom apps".
2. Teams apps > Setup policies > Global (Org-wide default), or the policy that applies to the users: turn on "Upload custom apps" if developers or testers are supposed to sideload test versions. For the rollout through the org catalog the first setting is what matters.

Policy changes can take up to 24 hours to reach all users. Plan for that delay before the first test.

If you use app-centric management or permission policies, make sure the new app is allowed for the intended users after the upload.

## 4. Upload the app to the org catalog

Teams apps > Manage apps > Actions > Upload new app, then select the ZIP file from the developer.

This puts the app into your organisation's app catalog. It is not the same as "Upload a custom app" in the Teams client, which only installs it for one person. The application can only install the app for users when it is in the catalog.

After the upload, open the app's detail page and note its App ID (also visible in the address bar). This catalog ID differs from the ID inside the package, and the developer needs it.

Optional: in the setup policy from step 3, add the app under Installed apps and pin it, so users see it without installing it themselves.

For every new version from the developer, upload the new ZIP on the same detail page (Upload file / Update), not as a new app.

## 5. Azure Bot resource (only with a bot)

Azure portal > Create a resource > Azure Bot.

1. Subscription and resource group in this tenant. Pricing tier: change it to Free (F0). Teams is a standard channel; messages there are free and unlimited.
2. Type of App: Single Tenant. Multi-tenant bots can no longer be created since 31 July 2025.
3. Creation type: Use existing app registration, with the client ID and tenant ID from step 1. The client secret from step 1 is the bot password.
4. Do not create an Application Insights instance unless you want one; it is the only part that is billed.
5. After creation: Settings > Configuration > Messaging endpoint `https://api.contoso.com/api/teams/bot/messages`.
6. Channels > Microsoft Teams > accept the terms > Apply.

The bot needs an Azure subscription linked to this tenant. Microsoft 365 business plans do not include one. A pay-as-you-go subscription is enough; with F0 it stays at zero cost.

## 6. Send back to the developer

| Value | From |
|---|---|
| Directory (tenant) ID | step 1 |
| Application (client) ID | step 1 |
| Client secret and its expiry date | step 1, through a secure channel, never by plain email |
| Application ID URI | step 1 |
| Confirmation that admin consent is granted | step 2 |
| Catalog app ID | step 4 |
| Bot app ID, if a separate registration was used | step 5 |

## Before you start: one question for your organisation

Notifications to employees about performance or compliance status can require approval by a works council or employee representation. If that applies, clarify it before the rollout.
