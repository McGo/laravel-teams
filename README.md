# mirkohaaser/teams

[![tests](https://github.com/McGo/laravel-teams/actions/workflows/tests.yml/badge.svg)](https://github.com/McGo/laravel-teams/actions/workflows/tests.yml)

Microsoft Teams for Laravel apps with a Nuxt front end: a bot that receives messages and card actions and writes on its own, notifications in the Teams activity feed, and a personal app with several tabs and single sign-on.

The code is extracted from an application that has run this setup in production since July 2026. It talks to the Bot Connector and Microsoft Graph over plain HTTP. There is no Bot Framework SDK for PHP, and this package does not need one.

**Status:** pre-release. Not on Packagist or npm yet; install from Git until `v0.1.0`.

## What you get

- `mirkohaaser/teams` (Composer): token validation for the Bot Connector and for tab SSO, a messaging endpoint with a registry for commands and card actions, a bot client for proactive messages, notification channels for the activity feed and the bot chat, manifest generation from config, Artisan commands for setup and diagnosis, and `Teams::fake()` for your tests.
- `nuxt-ms-teams` (npm, in [`nuxt/`](nuxt/)): a Nuxt layer with `useTeams()`, a consent page, a bare `teams` layout and the CSP header that lets Teams frame your tabs.

Single-tenant registrations only, one Entra app and one Teams app per installation. Microsoft stopped creating multi-tenant bots on 31 July 2025. Posting to channels through Workflows or Power Automate is out of scope.

## Quick start

Requires PHP 8.3+, Laravel 12 or 13. Sanctum is the default session issuer for tabs.

```bash
composer config repositories.teams vcs https://github.com/McGo/laravel-teams
composer require mirkohaaser/teams:dev-main
php artisan vendor:publish --tag=teams-config --tag=teams-migrations
php artisan migrate
```

Minimal `.env` (the values come from your app registration, see [the admin checklist](docs/guide/07-admin-checklist.md)):

```dotenv
TEAMS_ENABLED=true
TEAMS_TENANT_ID=00000000-0000-0000-0000-000000000000
TEAMS_CLIENT_ID=00000000-0000-0000-0000-000000000000
TEAMS_CLIENT_SECRET=...
TEAMS_APP_ID=00000000-0000-0000-0000-000000000000
TEAMS_APP_ID_URI=api://app.contoso.com/botid-00000000-0000-0000-0000-000000000000
TEAMS_BOT_APP_ID=00000000-0000-0000-0000-000000000000
TEAMS_BOT_APP_PASSWORD=...
TEAMS_HOST=app.contoso.com
```

Describe the app in `config/teams.php`:

```php
'manifest' => [
    'name' => ['short' => 'Contoso Desk', 'full' => 'Contoso Desk'],
    'description' => ['short' => 'Contoso in Teams', 'full' => 'Reports and approvals of Contoso in Teams.'],
    'tabs' => [
        'home' => ['name' => 'Home', 'path' => '/teams/home'],
        'reports' => ['name' => 'Reports', 'path' => '/teams/reports'],
    ],
    'activity_types' => [
        'reportReady' => ['description' => 'Report ready', 'template' => '{actor}: report {name} is ready'],
    ],
    // ...
],
```

Register what the bot does, in a service provider:

```php
use MirkoHaaser\Teams\Facades\Teams;

Teams::command('status', StatusCommand::class);        // "status" as first word
Teams::action('report.approve', ApproveReport::class); // button on a card
Teams::onMessage(EverythingElse::class);               // optional, any other text
```

A command answers with text or a card:

```php
use MirkoHaaser\Teams\Bot\Contracts\HandlesCommand;
use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Cards\Cards;
use MirkoHaaser\Teams\Models\TeamsIdentity;

class StatusCommand implements HandlesCommand
{
    public function description(): string
    {
        return 'Shows the latest report';
    }

    public function handle(string $arguments, IncomingActivity $activity, TeamsIdentity $identity): ?Reply
    {
        $report = Report::latest()->first();

        return Reply::card(Cards::card(
            [Cards::text($report->title, bold: true), Cards::facts(['Status' => $report->status])],
            [Cards::submit('Approve', 'report.approve', ['report' => $report->id])],
        ));
    }
}
```

Text messages and commands run on the queue, so a handler may take its time. Card actions run synchronously, because their return value replaces the clicked card.

Notify a user in the activity feed or the bot chat:

```php
use MirkoHaaser\Teams\Notifications\TeamsActivityChannel;
use MirkoHaaser\Teams\Notifications\TeamsActivityMessage;

public function via($user): array
{
    return ['mail', TeamsActivityChannel::class];   // or 'teams-bot' with toTeamsBot()
}

public function toTeamsActivity($user): TeamsActivityMessage
{
    return new TeamsActivityMessage(
        type: 'reportReady',
        subjectKey: "report:{$this->report->id}",
        previewText: "{$this->report->title} is ready",
        parameters: ['name' => $this->report->title],
        tab: 'reports',
        subEntityId: (string) $this->report->id,
    );
}
```

Build and upload the app:

```bash
php artisan teams:check      # walks through the configuration
php artisan teams:manifest   # writes storage/app/teams/contoso-desk-1.0.0.zip
```

The tabs are pages of your Nuxt app under `/teams/…`; see [`nuxt/README.md`](nuxt/README.md).

## Documentation

The [guide](docs/guide/README.md) covers the Entra registration, the manifest, the activity feed, tab SSO and the bot, with the raw protocol next to what the package does for you. [The pitfalls journal](docs/guide/90-pitfalls.md) collects what cost time in practice.

Concepts and epics in `docs/concept` and `docs/epics` are in German.

## License

MIT
