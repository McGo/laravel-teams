<?php

namespace MirkoHaaser\Teams\Bot\Events;

use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;

class BotInstalled
{
    public function __construct(
        public readonly IncomingActivity $activity,
        public readonly TeamsConversation $conversation,
        public readonly ?TeamsIdentity $identity,
    ) {}
}
