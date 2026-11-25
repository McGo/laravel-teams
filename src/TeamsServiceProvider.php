<?php

namespace MirkoHaaser\Teams;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use MirkoHaaser\Teams\Auth\BotFrameworkTokenValidator;
use MirkoHaaser\Teams\Auth\EntraTokenValidator;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Identity\ResolvesTeamsIdentity;
use MirkoHaaser\Teams\Notifications\TeamsActivityChannel;
use MirkoHaaser\Teams\Notifications\TeamsBotChannel;
use MirkoHaaser\Teams\Sessions\IssuesTabSession;
use MirkoHaaser\Teams\Support\DeepLink;

class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');

        $this->app->singleton(TeamsManager::class);

        $this->app->bind(GraphClient::class, fn () => new GraphClient(
            enabled: (bool) config('teams.enabled'),
            tenantId: (string) config('teams.tenant_id'),
            clientId: (string) config('teams.client_id'),
            clientSecret: config('teams.client_secret'),
            graphBaseUrl: (string) config('teams.graph.base_url'),
            loginBaseUrl: (string) config('teams.graph.login_url'),
            timeout: (int) config('teams.graph.timeout', 15),
            tokenLeeway: (int) config('teams.graph.token_leeway', 120),
        ));

        $this->app->bind(BotClient::class, fn () => new BotClient(
            appId: (string) config('teams.bot.app_id'),
            appPassword: config('teams.bot.app_password'),
            loginUrl: rtrim((string) config('teams.bot.login_url'), '/'),
            tenantId: (string) config('teams.tenant_id'),
            timeout: (int) config('teams.graph.timeout', 15),
            tokenLeeway: (int) config('teams.graph.token_leeway', 120),
        ));

        $this->app->bind(BotFrameworkTokenValidator::class, fn () => new BotFrameworkTokenValidator((string) config('teams.bot.app_id')));

        $this->app->bind(EntraTokenValidator::class, fn () => new EntraTokenValidator(
            clientId: (string) config('teams.client_id'),
            allowedTenants: array_values(array_filter((array) config('teams.allowed_tenants'))),
            loginBaseUrl: (string) config('teams.graph.login_url'),
            appIdUri: config('teams.app_id_uri'),
        ));

        $this->app->bind(DeepLink::class, fn () => new DeepLink(
            (string) config('teams.app_id'),
            array_map('strval', array_keys((array) config('teams.manifest.tabs', []))),
        ));

        $this->app->bind(ResolvesTeamsIdentity::class, fn ($app) => $app->make((string) config('teams.identity_resolver')));
        $this->app->bind(IssuesTabSession::class, fn ($app) => $app->make((string) config('teams.session_issuer')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/teams.php' => config_path('teams.php')], 'teams-config');
            $this->publishes([
                __DIR__.'/../database/migrations/create_teams_tables.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_teams_tables.php'),
            ], 'teams-migrations');

            $this->commands([
                Console\ManifestCommand::class,
                Console\InstallCommand::class,
                Console\LinkCommand::class,
                Console\CheckCommand::class,
                Console\SendTestCommand::class,
            ]);
        }

        RateLimiter::for('teams-sso', fn (Request $request) => Limit::perMinute((int) config('teams.routes.sso_per_minute', 20))->by($request->ip()));

        if (config('teams.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/Http/routes.php');
        }

        Notification::resolved(function ($manager): void {
            $manager->extend('teams', fn ($app) => $app->make(TeamsActivityChannel::class));
            $manager->extend('teams-bot', fn ($app) => $app->make(TeamsBotChannel::class));
        });
    }
}
