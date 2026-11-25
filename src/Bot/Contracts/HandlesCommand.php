<?php

namespace MirkoHaaser\Teams\Bot\Contracts;

use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * A command is chosen by the first word of a message. Runs on the queue.
 */
interface HandlesCommand
{
    /** One line for the help answer. */
    public function description(): string;

    /**
     * @param  string  $arguments  the message without the command word
     */
    public function handle(string $arguments, IncomingActivity $activity, TeamsIdentity $identity): ?Reply;
}
