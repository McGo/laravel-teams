<?php

namespace MirkoHaaser\Teams\Sessions;

use MirkoHaaser\Teams\Exceptions\TeamsConfigurationError;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Default session: a short-lived Sanctum token with its own ability, so tab
 * access can be limited and revoked apart from the web front end.
 */
class SanctumTabSession implements IssuesTabSession
{
    public function issue(TeamsIdentity $identity): array
    {
        $user = $identity->user;

        if ($user === null || ! method_exists($user, 'createToken')) {
            throw new TeamsConfigurationError(
                'SanctumTabSession needs laravel/sanctum and the HasApiTokens trait on '.config('teams.user_model')
                .'. Bind your own '.IssuesTabSession::class.' otherwise.'
            );
        }

        $expires = now()->addHours((int) config('teams.session.ttl_hours', 8));
        $token = $user->createToken('teams-tab', [(string) config('teams.session.ability', 'teams')], $expires);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute((string) config('teams.user_email_column', 'email')),
            ],
        ];
    }
}
