<?php

namespace MirkoHaaser\Teams\Sessions;

use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Turns a validated tab sign-in into a session of the application. The
 * returned array is sent to the tab as JSON.
 */
interface IssuesTabSession
{
    /**
     * @return array{token: string, expires_at: string, user: array<string, mixed>}
     */
    public function issue(TeamsIdentity $identity): array;
}
