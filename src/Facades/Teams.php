<?php

namespace MirkoHaaser\Teams\Facades;

use Illuminate\Support\Facades\Facade;
use MirkoHaaser\Teams\TeamsManager;

/**
 * @method static TeamsManager onMessage(string $handler)
 * @method static TeamsManager command(string $word, string $handler)
 * @method static TeamsManager action(string $verb, string $handler)
 * @method static \MirkoHaaser\Teams\Clients\BotClient bot()
 * @method static \MirkoHaaser\Teams\Clients\GraphClient graph()
 * @method static \MirkoHaaser\Teams\Testing\TeamsFake fake()
 *
 * @see TeamsManager
 */
class Teams extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TeamsManager::class;
    }
}
