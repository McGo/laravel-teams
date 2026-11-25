<?php

use MirkoHaaser\Teams\Identity\MailIdentityResolver;
use MirkoHaaser\Teams\Sessions\SanctumTabSession;

/*
 * Microsoft Teams integration: bot, activity feed and personal tabs.
 *
 * One installation belongs to exactly one Entra app registration and one
 * Teams app, registered in the tenant where the users are. Nothing here
 * defaults to a real tenant, client or host.
 *
 * With `enabled` false, channels, commands and endpoints run without side
 * effects: no request leaves the application.
 */

return [

    'enabled' => filter_var(env('TEAMS_ENABLED', false), FILTER_VALIDATE_BOOL),

    // Entra app registration (single tenant).
    'tenant_id' => env('TEAMS_TENANT_ID', ''),
    'client_id' => env('TEAMS_CLIENT_ID', ''),
    'client_secret' => env('TEAMS_CLIENT_SECRET'),

    // Tenants whose user tokens are accepted (tid claim). Defaults to the
    // registration tenant. Comma separated in .env.
    'allowed_tenants' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TEAMS_ALLOWED_TENANTS', (string) env('TEAMS_TENANT_ID', ''))),
    ))),

    // Application ID URI from the registration. With a bot in the app it has
    // the form api://{host}/botid-{client_id}.
    'app_id_uri' => env('TEAMS_APP_ID_URI'),

    // Teams app id from the manifest (used in deep links) and the id of the
    // same app in the tenant's catalog (different GUID, per tenant, shown in
    // the Teams admin center URL of the app).
    'app_id' => env('TEAMS_APP_ID', ''),
    'catalog_app_id' => env('TEAMS_CATALOG_APP_ID', ''),

    'bot' => [
        // Without an app id the messaging endpoint answers 404.
        'app_id' => env('TEAMS_BOT_APP_ID', ''),
        'app_password' => env('TEAMS_BOT_APP_PASSWORD'),

        // Single-tenant bots get their token from their own tenant.
        'login_url' => rtrim((string) env('TEAMS_BOT_LOGIN_URL', 'https://login.microsoftonline.com/'.env('TEAMS_TENANT_ID', '')), '/'),

        // Text messages are handled on the queue.
        'queue_connection' => env('TEAMS_QUEUE_CONNECTION'),
        'queue' => env('TEAMS_QUEUE'),

        // Show the typing indicator before a message handler runs.
        'typing' => true,

        // Answer to users without a linked account. {link} is replaced with
        // a deep link into the first tab.
        'unlinked_reply' => 'Your Teams account is not linked yet. Open the app once to sign in: {link}',
    ],

    'graph' => [
        'base_url' => rtrim((string) env('TEAMS_GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/'),
        'login_url' => rtrim((string) env('TEAMS_LOGIN_BASE_URL', 'https://login.microsoftonline.com'), '/'),
        'timeout' => (int) env('TEAMS_HTTP_TIMEOUT', 15),
        // Renew app tokens this many seconds before they expire.
        'token_leeway' => (int) env('TEAMS_TOKEN_LEEWAY', 120),
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'api/teams',
        'middleware' => ['api'],
        // Named rate limiter for the SSO endpoint, registered by the package.
        'sso_per_minute' => 20,
    ],

    // Model the identities belong to and the key type of its table
    // (bigint, uuid or ulid). The key type is read by the migration.
    'user_model' => env('TEAMS_USER_MODEL', 'App\\Models\\User'),
    'user_key_type' => 'bigint',
    // Column compared with the UPN by the mail identity resolver.
    'user_email_column' => 'email',

    'identity_resolver' => MailIdentityResolver::class,
    'session_issuer' => SanctumTabSession::class,

    'session' => [
        'ability' => 'teams',
        'ttl_hours' => 8,
    ],

    /*
     * The Teams app itself. `php artisan teams:manifest` builds the upload
     * package from this section, and DeepLink only accepts tabs listed here.
     */
    'manifest' => [
        'version' => '1.0.0',
        'host' => env('TEAMS_HOST', ''),
        'name' => ['short' => env('APP_NAME', 'Laravel'), 'full' => env('APP_NAME', 'Laravel')],
        'description' => ['short' => '', 'full' => ''],
        'developer' => [
            'name' => '',
            'website_url' => '',
            'privacy_url' => '',
            'terms_url' => '',
        ],
        'accent_color' => '#FFFFFF',
        'icons' => [
            // 192x192 full colour and 32x32 white on transparent.
            'color' => resource_path('teams/color.png'),
            'outline' => resource_path('teams/outline.png'),
        ],

        // entityId => tab. `path` is relative to https://{host}.
        'tabs' => [
            // 'home' => ['name' => 'Home', 'path' => '/teams/home', 'website_path' => '/'],
        ],

        // activityType => description and templateText (max 128 characters).
        'activity_types' => [
            // 'reportReady' => ['description' => 'Report ready', 'template' => '{actor}: report {name} is ready'],
        ],

        // Bot command menu, title => description. Empty: built from the
        // commands registered with Teams::command().
        'bot_commands' => [],

        'output' => storage_path('app/teams'),
    ],
];
