# 03 App manifest

Microsoft now calls it the "Microsoft 365 app manifest"; the older name "Teams app manifest" still shows up in older samples. The current schema version is 1.29 (as of July 2026).

Minimum versions, in case someone needs an older one: activity feed from 1.7, application RSC from 1.6, delegated RSC from 1.12, running outside Teams from 1.13. Picking an old version without a reason saves nothing.

## Generating it with the package

The package builds the manifest from `config/teams.php` instead of a template with placeholders:

```bash
php artisan teams:manifest
```

It reads the `manifest` section (name, description, publisher, colours, `manifest.tabs` with entity ID, name and path, `manifest.activity_types` with their `templateText`) together with `app_id`, `client_id`, `app_id_uri` and `bot.app_id`. It writes schema 1.29, checks the limits before writing (at most one bot, `templateText` at most 128 characters, icons 192×192 and 32×32) and packs manifest and icons into `storage/app/teams/{name}-{version}.zip`.

A few rules follow from the config. Without `bot.app_id` there is no `bots` entry. Without activity types there is no RSC permission. As soon as a bot is configured, `resource` takes the form `api://{host}/botid-{client-id}`. The deep link helper `DeepLink::toTab()` only accepts tabs listed in `manifest.tabs`, so a typo in a tab name fails in your code rather than in Teams.

The section looks like this (excerpt; the published config has every key):

```php
'manifest' => [
    'version' => '1.0.0',
    'host' => env('TEAMS_HOST', ''),      // tab host, e.g. teams.contoso.com

    // entityId => tab, path relative to https://{host}
    'tabs' => [
        'overview' => ['name' => 'Overview', 'path' => '/teams/overview', 'website_path' => '/overview'],
        'settings' => ['name' => 'Settings', 'path' => '/teams/settings'],
    ],

    // activityType => description and templateText (max 128 characters)
    'activity_types' => [
        'reportReady' => ['description' => 'Report ready', 'template' => '{actor}: {title} is ready'],
    ],
],
```

Raise `version` every time you upload a new package. The reason is in the pitfall about the desktop client showing an old state.

## Template

For reference, this is what the generated file looks like. Values in angle brackets are placeholders. Everything that changes per environment (tunnel host in development, real domain in production) belongs in the substitution, not in the file.

```jsonc
{
  "$schema": "https://developer.microsoft.com/json-schemas/teams/v1.29/MicrosoftTeams.schema.json",
  "manifestVersion": "1.29",
  "version": "1.0.0",
  "id": "00000000-0000-0000-0000-000000000000",
  "developer": {
    "name": "Contoso Ltd",
    "websiteUrl": "https://www.contoso.com",
    "privacyUrl": "https://www.contoso.com/privacy",
    "termsOfUseUrl": "https://www.contoso.com/terms"
  },
  "name":        { "short": "<max 30 chars>", "full": "<max 100>" },
  "description": { "short": "<max 80 chars>", "full": "<max 4000>" },
  "icons": { "color": "color.png", "outline": "outline.png" },
  "accentColor": "#000000",

  "staticTabs": [
    {
      "entityId": "overview",
      "name": "Overview",
      "contentUrl": "https://teams.contoso.com/teams/overview",
      "websiteUrl": "https://www.contoso.com/overview",
      "scopes": ["personal"],
      "context": ["personalTab"]
    }
  ],

  "bots": [
    {
      "botId": "00000000-0000-0000-0000-000000000000",
      "scopes": ["personal"],
      "isNotificationOnly": false,
      "supportsFiles": false
    }
  ],

  "webApplicationInfo": {
    "id": "00000000-0000-0000-0000-000000000000",
    "resource": "api://teams.contoso.com/botid-00000000-0000-0000-0000-000000000000"
  },

  "activities": {
    "activityTypes": [
      {
        "type": "reportReady",
        "description": "Report ready",
        "templateText": "{actor}: {title} is ready"
      }
    ]
  },

  "authorization": {
    "permissions": {
      "resourceSpecific": [
        { "type": "Application", "name": "TeamsActivity.Send.User" }
      ]
    }
  },

  "validDomains": ["teams.contoso.com"],
  "permissions": ["identity", "messageTeamMembers"]
}
```

The package is a ZIP of `manifest.json` and the two icons, nothing else.

## The individual blocks

`staticTabs` holds one entry per tab, each with its own `entityId`. The `entityId` is the anchor for deep links, so make it readable and stable. Static tabs are meant for the personal scope; in a channel Teams only shows the first declared entry.

`bots` allows exactly one entry per app.

`webApplicationInfo` must be filled in even if you do not use SSO (with a dummy in that case). Wildcards and top-level domains are not allowed.

`activities.activityTypes` must list every notification type you later use in `activityType`. The reserved type `systemDefault` must explicitly not be listed. It allows free text without a manifest entry, but costs the fine-grained opt-out: the user can then only mute all notifications of the app at once.

`{actor}` is a special parameter. For delegated calls it is the user's name, for app-only calls the name of the app. Teams handles localisation based on `templateText`.

`validDomains` lists every domain that is loaded or navigated to inside the tab. If one is missing, navigation stops without a message.

There is a side effect to plan for. Teams opens links to a `validDomains` domain inside the tab, not in the browser, and that includes `app.openLink`. If the tab links to your own website (say `/app/…`), serve the tab from its own subdomain (for example `teams.contoso.com`) and list only that one in `validDomains`. The website domain then stays foreign to Teams, and links to it open in the system browser. In this layout `contentUrl`, `validDomains` and the application ID URI in `webApplicationInfo.resource` follow the tab host, while `websiteUrl` and the `developer` block point to the website. Details and the workaround without a subdomain are in [90 Pitfalls](90-pitfalls.md#links-from-the-tab-stay-in-the-tab-instead-of-opening-the-browser).

## Limits

| Field | Limit |
|---|---|
| `staticTabs` | 16 entries |
| `staticTabs[].entityId` | 64 characters |
| `staticTabs[].name` | 128 characters |
| `contentUrl`, `websiteUrl` | 2048 characters, `http(s)://` |
| `bots` | 1 entry |
| `bots[].commandLists` | 3, each with at most 12 commands |
| `activityTypes` | 128 entries |
| `activityTypes[].type` | 64 characters |
| `activityTypes[].templateText` | 128 characters |
| `validDomains` | 100 entries of 2048 characters |
| `name.short` / `name.full` | 30 / 100 |
| `description.short` / `.full` | 80 / 4000 |

The 128 characters for `templateText` shape the design more than it first seems. A notification is a teaser. Everything else belongs on the card or in the tab.

## What changed in 2026

Store submissions for channel-capable apps need manifest 1.25 or later plus `"supportsChannelFeatures": "tier1"`. Deprecated: `packageName` (since 1.17), `callingSidePanel`, `isBroadcast` (Teams Live Events moved to the Virtual Event APIs). Publisher verification is a prerequisite for store submission.

⚠ Both are snapshots. Check the schema reference before submitting.
