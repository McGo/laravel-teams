# 04 Activity feed

The entry under the bell in Teams, plus a toast on desktop and mobile. The sender is the app's identity, the text is personal per recipient, and a click leads to a URL of your choice.

Everything here is in Graph v1.0, nothing in beta. Each call answers `204 No Content`.

## With the package

Notifications go through Laravel's notification system:

```php
class ReportReady extends Notification
{
    public function via(object $notifiable): array
    {
        return [TeamsActivityChannel::class];
    }

    public function toTeamsActivity(object $notifiable): TeamsActivityMessage
    {
        // activity type from manifest.activity_types, template parameters,
        // preview text and the target tab built with DeepLink::toTab()
    }
}
```

The channel takes care of three things this page describes in detail. It makes sure the app is installed for the recipient before sending, because the RSC grant only exists after installation. It blocks duplicate delivery through the `teams_notifications` table. And with `enabled` set to false it logs and sends nothing. The notifiable is any model with a Teams identity, not a fixed user class.

To try it on a real account, `php artisan teams:send-test {user}` sends a test notification to the feed and a test card to the bot chat. In tests, `Teams::fake()` records feed notifications, and `assertActivityNotified()` checks them.

## Endpoints

| Target | Call |
|---|---|
| One user | `POST /v1.0/users/{userId\|upn}/teamwork/sendActivityNotification` |
| Chat | `POST /v1.0/chats/{chatId}/sendActivityNotification` |
| Team | `POST /v1.0/teams/{teamId}/sendActivityNotification` |
| Several users | `POST /v1.0/teamwork/sendActivityNotificationToRecipients` |

The token comes from the client credentials flow with scope `https://graph.microsoft.com/.default`, using `tenant_id`, `client_id` and `client_secret`.

## Payload

There are two forms of `topic`, and the choice is not arbitrary.

`entityUrl` requires a Graph resource. In the user context that can only be the recipient's app installation:

```json
{
  "topic": {
    "source": "entityUrl",
    "value": "https://graph.microsoft.com/v1.0/users/{userId}/teamwork/installedApps/{installationId}"
  },
  "activityType": "<declared in the manifest>",
  "previewText": { "content": "<short text>" },
  "templateParameters": [ { "name": "<param>", "value": "<value>" } ]
}
```

`text` is the form for your own targets. `webUrl` is then mandatory, and that is where the deep link goes:

```json
{
  "topic": {
    "source": "text",
    "value": "<heading>",
    "webUrl": "https://teams.microsoft.com/l/entity/{appId}/{entityId}?context=…"
  },
  "activityType": "<type>",
  "previewText": { "content": "<short text>" },
  "chainId": 1605223780000,
  "templateParameters": [ { "name": "<param>", "value": "<value>" } ]
}
```

What the fields mean:

- `activityType` has to be in the manifest. The exception is the reserved type `systemDefault`, which allows free text and for that reason must not be in the manifest.
- `chainId` (Int64): the same value replaces an earlier notification instead of adding a second one next to it. For recurring reminders about the same thing, that is the behaviour you want.
- `previewText` is an `itemBody`, so `{ "content": "…" }`. Teams shows the first 150 characters. ⚠ A server-side hard limit is not documented.
- `teamsAppId` is only needed if you share one Entra app ID across several Teams apps. Better not to share.
- `iconId` only works if the value is declared under `allowedIconIds` in the manifest; otherwise validation fails.

## Bulk

```json
{
  "topic": { "source": "entityUrl",
             "value": "https://graph.microsoft.com/v1.0/appCatalogs/teamsApps/{teamsAppId}" },
  "activityType": "<type>",
  "previewText": { "content": "<text>" },
  "recipients": [
    { "@odata.type": "microsoft.graph.aadUserNotificationRecipient", "userId": "<guid|upn>" }
  ],
  "templateParameters": [ … ]
}
```

At most 100 recipients per call. The bulk endpoint only accepts `aadUserNotificationRecipient`, and everyone gets the same text. Personalised content therefore means individual delivery. Note that `{teamsAppId}` is the catalog ID (`catalog_app_id`), not the manifest ID.

The other recipient types (`chatMembersNotificationRecipient`, `teamMembersNotificationRecipient`, `channelMembersNotificationRecipient`) belong to the chat and team endpoints and must match the ID in the URL there.

## Permissions

| Endpoint | Delegated | Application, minimal |
|---|---|---|
| User, bulk | `TeamsActivity.Send` | `TeamsActivity.Send.User` (RSC) |
| Chat | `TeamsActivity.Send` | `TeamsActivity.Send.Chat` (RSC) |
| Team | `TeamsActivity.Send` | `TeamsActivity.Send.Group` (RSC) |

Personal Microsoft accounts are not supported.

## Throttling

| Call | per app and tenant | per app across all tenants |
|---|---|---|
| User / team | 5 rps | 50 rps |
| Chat | 5 rps (1 rps per resource) | 50 rps |
| Bulk | 2 rps | 20 rps |

On top of that: at most 20 notifications per user per minute, retention in the feed for 30 days, and team-wide delivery only for teams with up to 10,000 members. Exceeding a limit returns `429` with `Retry-After`; the limits cannot be raised.

The practical consequence is that 5 requests per second set the schedule of a mass send. Ten thousand personalised reminders take a good half hour through individual delivery. Build the send as a throttled queue, not as a loop. Since `TeamsActivityChannel` runs through Laravel's notification system, queue the notification and put a rate limit on the queue it runs on.

## Deep links

```
https://teams.microsoft.com/l/entity/{appId}/{entityId}?context={"subEntityId":"<value>"}
```

The `context` parameter is URL-encoded. `{appId}` is the manifest ID (`app_id`). `entityId` is the tab from the manifest, `subEntityId` the concrete target within the tab. The tab reads the value from the context on start (in TeamsJS v2 the field is `context.page.subPageId`; `useTeams()` exposes it).

Keep the total length under 2048 characters. On Windows that is a hard limit.

Put link building into one helper, not at the call site. Every notification delivers its target as a pair of tab and sub-entity, and the URL is created in exactly one place. In the package that place is `DeepLink::toTab()`, which only accepts tabs from `manifest.tabs`.

## What does not work

Delivery without an installed app. There is no error you could handle in a meaningful way; installation is the prerequisite, see [02 Entra setup](02-entra-setup.md#distribution-in-the-tenant).

Polling for changes. A GET on the same resource to detect changes is limited to once a day; anything more violates the terms of use. Change notifications exist for that purpose.
