# 06 Bot

The route for messages that someone has to act on. Also the most expensive piece: an Azure resource, a permanently reachable endpoint, validation of inbound requests and bookkeeping of conversation IDs.

## SDK or not

The Bot Framework SDK was archived in December 2025; support ended on 31 December 2025. For new projects Microsoft recommends the Teams SDK (Teams only) or the Microsoft 365 Agents SDK, with the Agents Toolkit v5 as tooling. TeamsFx is deprecated.

For an application that is not written in .NET or Node, little changes. The SDK was archived, not the protocol. The Azure Bot resource, the connector service and the `/v3/conversations` endpoints are still documented and running, and no end date has been announced. Writing three HTTP calls yourself is manageable and ties you to nothing that might be archived tomorrow. This package does exactly that.

⚠ That is an assessment with an expiry date. Before the next project, check whether the REST interface is still maintained.

## Where the bot runs

The Azure Bot resource runs no code. It is a registration with an app ID and a messaging URL; the connector service relays messages between Teams and that URL. The bot therefore lives in your own application. With this package that means one route, a few classes, your existing queue and your existing database. No second deployment, no additional runtime.

All you need is a permanently reachable HTTPS endpoint. If you run one anyway, the infrastructure side is done.

## With the package

Register handlers in a service provider:

```php
Teams::onMessage(FallbackHandler::class);
Teams::command('status', StatusCommand::class);
Teams::action('report.approve', ApproveReport::class);
```

The classes implement `HandlesMessage`, `HandlesCommand` and `HandlesCardAction` and are resolved through the container. A command or verb registered twice throws an exception at boot.

`command()` is a thin router on top of `onMessage()`. The first word of the message picks the command; `@botname` mentions are stripped before routing. Anything that matches no command goes to the general handler, or, if there is none, gets a help reply listing the commands.

Text messages run through the queue. The endpoint acknowledges with 200 straight away, the job `HandleIncomingMessage` calls the handler, and the handler's `Reply` (text or card) goes back into the same conversation. A handler may therefore be slow, for example when it asks a language model. Card actions run synchronously, because the response card replaces the old one in place and Teams only waits briefly. An action that takes longer returns an interim card immediately and sends the result from a job.

If the sender is not linked to a user of your application, no handler is called. The bot answers with `bot.unlinked_reply`, by default a hint with a deep link into the first tab, where signing in creates the link. The package never creates a user.

Every command and card action is logged in `teams_actions` with name, duration and result.

To send on your own initiative, use a notification with `TeamsBotChannel` and return the text or card from `toTeamsBot()`. A missing personal conversation is created and stored. In tests, `Teams::fake()` records outgoing messages and offers `assertMessageSent()`, `assertCardSent()` and `assertNothingSent()`, plus a helper that pushes an inbound activity through the endpoint without a token.

The rest of this page describes what happens underneath.

## Validating inbound requests

The messaging endpoint is configurable in the bot resource; `/api/messages` is a convention from the SDK templates, not a rule. The package uses `POST {route_prefix}/bot/messages`, without CSRF middleware and without session auth. It answers 404 when `bot.app_id` is empty and 401 for an invalid token.

Metadata (static, may be hard-coded):

```
GET https://login.botframework.com/v1/.well-known/openidconfiguration
→ issuer   https://api.botframework.com
  jwks_uri https://login.botframework.com/v1/.well-known/keys
  alg      RS256
```

Seven checks, all required:

1. Bearer header present
2. valid JWT
3. `iss == https://api.botframework.com`
4. `aud ==` your Microsoft app ID (`bot.app_id`)
5. within the validity period (five minutes of leeway)
6. signature against a key from the JWKS
7. `serviceUrl` in the token equals `serviceUrl` in the activity root

Number 7 is the one that gets lost when copying from somewhere, and it is the reason `serviceUrl` can be trusted at all.

Teams-specific: the keys carry an `endorsements` field. If the `channelId` (`msteams`) requires an endorsement and it is missing, reject with 403.

Refresh the JWKS cache at least daily. The package caches it for 24 hours.

## Sending proactively

Fetch a token (single-tenant bot):

```http
POST https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id=<bot-app-id>
&client_secret=<bot-app-password>
&scope=https%3A%2F%2Fapi.botframework.com%2F.default
```

The token is valid for 3600 seconds. Put it unchanged into `Authorization: Bearer …`; do not escape it.

The base URI is always the `serviceUrl` of the most recently received activity. Without an activity, the documentation names `https://smba.trafficmanager.net/teams/`, with the explicit note not to hard-code it.

Create a conversation:

```http
POST {serviceUrl}/v3/conversations
Authorization: Bearer <token>

{
  "bot":     { "id": "28:<botAppId>", "name": "<bot name>" },
  "members": [ { "id": "<aadObjectId of the user>" } ],
  "channelData": { "tenant": { "id": "<tenantId>" } }
}
→ { "id": "a:1qhNLqp…" }
```

The returned ID is the personal chat. Store it and reuse it; the package keeps it in `teams_conversations`. This only works in the personal scope and only when the app is installed for the user, otherwise you get `403 ForbiddenOperationException`.

Send, reply, update:

```http
POST {serviceUrl}/v3/conversations/{conversationId}/activities
POST {serviceUrl}/v3/conversations/{conversationId}/activities/{activityId}
PUT  {serviceUrl}/v3/conversations/{conversationId}/activities/{activityId}
```

```json
{
  "type": "message",
  "from": { "id": "28:<botAppId>", "name": "<bot name>" },
  "conversation": { "id": "<conversationId>" },
  "text": "<text>"
}
```

Blocked or uninstalled: `403` with `errorCode 209`, `subCode MessageWritesBlocked`. The package raises `TeamsRequestFailed` with status and body for errors and `TeamsThrottled` with `Retry-After` for a 429.

## Cards with actions

Attachment type `application/vnd.microsoft.card.adaptive`, the card goes under `content`.

Settle the version question first. Teams supports Adaptive Cards up to 1.5, but the mobile app only up to 1.2. If you expect cards to be read on the go, and for reminders that is the rule, stay at 1.2. The package's `Cards` builder (text, fact list, buttons, result card) produces version 1.2 by default; for anything else, pass the card array directly.

`Action.Execute` belongs to the Universal Action Model and needs card version 1.4 or later. It does not replace `Action.Submit`; both exist side by side. Microsoft recommends putting `Action.Execute` into an `ActionSet` with a `fallback` to `Action.Submit`.

In the deployment this package comes from, the Teams client did not forward `Action.Execute` to the bot: the button rendered, the invoke never arrived. `Action.Submit` arrived every time, as a message activity, and works with card version 1.2. That is why `Cards::submit()` is the default button. `Cards::execute()` exists for clients that do deliver the invoke; it adds the `Action.Submit` fallback, and the card then needs `Cards::card($body, $actions, Cards::VERSION_UNIVERSAL_ACTIONS)`.

Automatic refresh:

```json
"refresh": {
  "action": { "type": "Action.Execute", "title": "Refresh", "verb": "<verb>" },
  "userIds": ["29:<mri>"]
}
```

Without `userIds` the card does not refresh by itself but shows a button. At most 60 IDs.

A button press arrives as an invoke activity:

```json
{
  "type": "invoke",
  "name": "adaptiveCard/action",
  "value": {
    "action": { "type": "Action.Execute", "id": "…", "verb": "…", "data": { } },
    "trigger": "automatic | manual"
  }
}
```

The response is HTTP 200 with an envelope:

| statusCode | type | value |
|---|---|---|
| 200 | `application/vnd.microsoft.card.adaptive` | new card, replaces the old one |
| 200 | `application/vnd.microsoft.activity.message` | text, shown to the user |
| 400 / 500 | `application/vnd.microsoft.error` | error object |
| 401 | `application/vnd.microsoft.activity.loginRequest` | OAuthCard |
| 412 | `application/vnd.microsoft.error.preconditionFailed` | error object |

`Action.Submit` arrives differently: as a `message` activity with the card payload in `value`. There the original card is replaced via a PUT on its `replyToId`. The package handles both forms and routes them to the action registered with `Teams::action()` under the verb. An unknown verb yields an error card, not an exception.

Replacing the card with its result state is not cosmetic. Otherwise a button stays there that can be pressed a second time.

And the rule to keep in mind while building: the card is not proof of authorisation. Having the card does not mean being allowed to perform the action. Check permissions at execution time, against the same domain logic as in the web UI.

## Installation event

When the bot is added, an `installationUpdate` or `conversationUpdate` activity arrives. It carries the conversation ID and the `serviceUrl`. Store both, or the application can never write on its own initiative. Reset them on uninstall.

The package stores the conversation from every activity, not only the install event, and fires `BotInstalled` and `BotUninstalled` so your application can react.
