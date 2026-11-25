# Local development

Teams only loads tabs and calls bots over public HTTPS. For local work you need a tunnel and a manifest that points at it.

## Tunnel

Any HTTPS tunnel works (Dev Tunnels, cloudflared, ngrok). Use a stable host name if your tunnel offers one; every change of host means a new manifest upload and a new Application ID URI in the registration.

```bash
devtunnel host -p 3000 --allow-anonymous
```

Set the host in your `.env` and rebuild the package:

```dotenv
TEAMS_HOST=abc123-3000.euw.devtunnels.ms
TEAMS_APP_ID_URI=api://abc123-3000.euw.devtunnels.ms/botid-00000000-0000-0000-0000-000000000000
```

```bash
php artisan teams:check
php artisan teams:manifest
```

Point the bot's messaging endpoint in the Azure Bot resource at the tunnel as well. A second registration for development keeps production untouched; one Entra app belongs to exactly one Teams app.

## The Nuxt playground

`nuxt/playground` is a minimal app that extends the layer and has two example tabs, `/teams/home` and `/teams/items`. Copy `nuxt/playground/.env.example` to `.env`, fill in the values from your registration and start it:

```bash
cd nuxt
npm install
npm run dev
```

Opened in a normal browser, the tabs report that they are not running inside Teams. That is expected. Upload the manifest to Teams to see them signed in.

## Tests in your application

`Teams::fake()` replaces the bot and Graph clients and records what would have been sent:

```php
$teams = Teams::fake();

$user->notify(new ReportReady($report));

$teams->assertActivityNotified($user, 'reportReady');
$teams->assertCardSent(fn ($card) => $card['body'][0]['text'] === 'Report Q3');
```

To exercise your command and action handlers through the real endpoint, sign a token with your own test key and fake the Bot Framework metadata with `Http::fake()`. The package's own tests in `tests/TestCase.php` show how.
