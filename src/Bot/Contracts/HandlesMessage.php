<?php

namespace MirkoHaaser\Teams\Bot\Contracts;

use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Receives every text message that no command claimed. Runs on the queue,
 * so it may take its time. Return null to stay silent.
 */
interface HandlesMessage
{
    public function handle(IncomingActivity $activity, TeamsIdentity $identity): ?Reply;
}
