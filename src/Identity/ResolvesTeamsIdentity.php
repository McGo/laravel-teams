<?php

namespace MirkoHaaser\Teams\Identity;

use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Maps an Entra user to an application user. Bind your own implementation if
 * your users are identified by something other than their mail address.
 */
interface ResolvesTeamsIdentity
{
    /**
     * Resolve validated claims to an identity and persist the mapping.
     * Return null when no user matches. Never create users on suspicion.
     *
     * @param  array<string, mixed>  $claims  validated claims, never raw ones
     */
    public function resolve(array $claims): ?TeamsIdentity;
}
