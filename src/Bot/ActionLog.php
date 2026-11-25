<?php

namespace MirkoHaaser\Teams\Bot;

use MirkoHaaser\Teams\Models\TeamsAction;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/** Writes the teams_actions log. Logging never breaks the handler. */
class ActionLog
{
    public function write(?TeamsIdentity $identity, string $kind, string $name, string $status, float $startedAt, ?string $error = null): void
    {
        try {
            TeamsAction::create([
                'user_id' => $identity?->user_id,
                'teams_identity_id' => $identity?->id,
                'kind' => $kind,
                'name' => mb_substr($name, 0, 100),
                'status' => $status,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            ]);
        } catch (\Throwable) {
            // The log is a convenience, not a reason to fail the user.
        }
    }
}
