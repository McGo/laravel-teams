<?php

namespace MirkoHaaser\Teams\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\SanctumServiceProvider;
use MirkoHaaser\Teams\Auth\BotFrameworkTokenValidator;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\TeamsServiceProvider;
use MirkoHaaser\Teams\Tests\Fixtures\Keys;
use MirkoHaaser\Teams\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const TENANT = '00000000-0000-0000-0000-00000000000a';

    public const CLIENT = '00000000-0000-0000-0000-00000000000c';

    public const BOT = '00000000-0000-0000-0000-00000000000b';

    public const APP = '00000000-0000-0000-0000-0000000000ad';

    public const SERVICE_URL = 'https://smba.trafficmanager.net/emea/';

    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, TeamsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('teams', array_replace_recursive($app['config']->get('teams'), [
            'enabled' => true,
            'tenant_id' => self::TENANT,
            'client_id' => self::CLIENT,
            'client_secret' => 'secret',
            'allowed_tenants' => [self::TENANT],
            'app_id' => self::APP,
            'catalog_app_id' => 'catalog-id',
            'bot' => ['app_id' => self::BOT, 'app_password' => 'bot-secret', 'login_url' => 'https://login.microsoftonline.com/'.self::TENANT, 'typing' => false],
            'user_model' => User::class,
            'manifest' => [
                'host' => 'app.contoso.com',
                'tabs' => ['home' => ['name' => 'Home', 'path' => '/teams/home'], 'items' => ['name' => 'Items', 'path' => '/teams/items']],
            ],
        ]));
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/create_teams_tables.php.stub')->up();
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/sanctum/database/migrations');
    }

    /**
     * @param  list<string>|null  $endorsements
     */
    protected function fakeMicrosoft(?array $endorsements = ['msteams']): void
    {
        Http::fake([
            BotFrameworkTokenValidator::OPENID => Http::response(['jwks_uri' => 'https://login.botframework.com/v1/.well-known/keys']),
            'login.botframework.com/v1/.well-known/keys' => Http::response(Keys::jwks($endorsements)),
            'login.microsoftonline.com/*/discovery/v2.0/keys' => Http::response(Keys::jwks()),
            'login.microsoftonline.com/*/oauth2/v2.0/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            '*' => Http::response(['id' => 'created'], 201),
        ]);
    }

    protected function botToken(array $overrides = []): string
    {
        return Keys::sign($overrides + ['iss' => BotFrameworkTokenValidator::ISSUER, 'aud' => self::BOT, 'serviceurl' => self::SERVICE_URL]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function activity(array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'message',
            'id' => 'activity-1',
            'serviceUrl' => self::SERVICE_URL,
            'from' => ['id' => '29:user', 'aadObjectId' => 'aad-user-1', 'name' => 'Jane'],
            'recipient' => ['id' => '28:'.self::BOT],
            'conversation' => ['id' => 'a:conversation-1', 'conversationType' => 'personal', 'tenantId' => self::TENANT],
            'channelData' => ['tenant' => ['id' => self::TENANT]],
            'text' => 'hello',
        ], $overrides);
    }

    protected function postActivity(array $activity, ?string $token = null): TestResponse
    {
        return $this->postJson('/api/teams/bot/messages', $activity, ['Authorization' => 'Bearer '.($token ?? $this->botToken())]);
    }

    protected function linkedUser(): User
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@contoso.com']);
        TeamsIdentity::create([
            'user_id' => $user->id, 'tenant_id' => self::TENANT, 'aad_object_id' => 'aad-user-1', 'upn' => 'jane@contoso.com',
        ]);

        return $user;
    }
}
