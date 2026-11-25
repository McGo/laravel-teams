<?php

namespace MirkoHaaser\Teams\Tests\Feature;

use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Tests\Fixtures\Keys;
use MirkoHaaser\Teams\Tests\Fixtures\User;
use MirkoHaaser\Teams\Tests\TestCase;

class SsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMicrosoft();
    }

    private function entraToken(array $overrides = []): string
    {
        return Keys::sign($overrides + [
            'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'aud' => self::CLIENT,
            'tid' => self::TENANT,
            'oid' => 'aad-user-1',
            'preferred_username' => 'Jane@Contoso.com',
        ]);
    }

    public function test_links_by_mail_and_issues_a_session(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@contoso.com']);

        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken()])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.id', $user->id);

        $identity = TeamsIdentity::sole();
        $this->assertSame('aad-user-1', $identity->aad_object_id);
        $this->assertSame(['teams'], $user->tokens()->sole()->abilities);
    }

    public function test_object_id_wins_after_first_contact(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@contoso.com']);
        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken()])->assertOk();

        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken(['preferred_username' => 'jane.renamed@contoso.com'])])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame('jane.renamed@contoso.com', TeamsIdentity::sole()->upn);
    }

    public function test_unknown_users_get_403_and_no_account(): void
    {
        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken()])->assertForbidden();
        $this->assertSame(0, User::count());
    }

    public function test_rejects_foreign_tenant_audience_and_v1_tokens(): void
    {
        User::create(['name' => 'Jane', 'email' => 'jane@contoso.com']);

        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken(['tid' => '00000000-0000-0000-0000-0000000000ff'])])->assertUnauthorized();
        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken(['aud' => 'someone-else'])])->assertUnauthorized();
        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken(['iss' => 'https://sts.windows.net/'.self::TENANT.'/'])])->assertUnauthorized();
        $this->postJson('/api/teams/auth/sso', ['token' => 'not-a-jwt'])->assertUnauthorized();
    }

    public function test_accepts_the_app_id_uri_as_audience(): void
    {
        config(['teams.app_id_uri' => 'api://app.contoso.com/botid-'.self::CLIENT]);
        User::create(['name' => 'Jane', 'email' => 'jane@contoso.com']);

        $this->postJson('/api/teams/auth/sso', ['token' => $this->entraToken(['aud' => 'api://app.contoso.com/botid-'.self::CLIENT])])->assertOk();
    }
}
