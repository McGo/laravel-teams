<?php

namespace MirkoHaaser\Teams\Bot\Contracts;

use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * A button on a card. Runs synchronously: the returned card replaces the one
 * that was clicked, and Teams waits only a few seconds for it. Long work goes
 * to a job that writes back through the BotClient; return an interim card.
 */
interface HandlesCardAction
{
    /**
     * @param  array<string, mixed>  $data  the action data without verb and source
     * @return array<string, mixed> the card that replaces the clicked one
     */
    public function handle(array $data, IncomingActivity $activity, TeamsIdentity $identity): array;
}
