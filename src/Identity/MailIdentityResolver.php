<?php

namespace MirkoHaaser\Teams\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Object id first, mail address on first contact. The object id survives
 * renames, the UPN does not, so once a user is found by UPN the object id is
 * stored and the mail address is never used for that person again.
 */
class MailIdentityResolver implements ResolvesTeamsIdentity
{
    public function resolve(array $claims): ?TeamsIdentity
    {
        $tenantId = (string) ($claims['tid'] ?? '');
        $objectId = (string) ($claims['oid'] ?? '');

        if ($tenantId === '' || $objectId === '') {
            return null;
        }

        $upn = $this->upn($claims);

        $identity = TeamsIdentity::query()
            ->where('tenant_id', $tenantId)
            ->where('aad_object_id', $objectId)
            ->first();

        if ($identity !== null) {
            if ($upn !== null && $identity->upn !== $upn) {
                $identity->forceFill(['upn' => $upn])->save();
            }

            return $identity;
        }

        if ($upn === null) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('teams.user_model');
        $column = (string) config('teams.user_email_column', 'email');

        $user = $model::query()->whereRaw("lower({$column}) = ?", [mb_strtolower($upn)])->first();

        if ($user === null) {
            Log::info('teams.identity.no_user', ['tenant_id' => $tenantId, 'upn' => $upn]);

            return null;
        }

        return TeamsIdentity::create([
            'user_id' => $user->getKey(),
            'tenant_id' => $tenantId,
            'aad_object_id' => $objectId,
            'upn' => $upn,
        ]);
    }

    /**
     * Entra sends the sign-in name as upn or preferred_username, depending on
     * the optional claims of the registration.
     *
     * @param  array<string, mixed>  $claims
     */
    private function upn(array $claims): ?string
    {
        foreach (['upn', 'preferred_username', 'email'] as $key) {
            if (is_string($claims[$key] ?? null) && $claims[$key] !== '') {
                return $claims[$key];
            }
        }

        return null;
    }
}
