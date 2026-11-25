<?php

namespace MirkoHaaser\Teams\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;
use MirkoHaaser\Teams\Exceptions\TeamsThrottled;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Delivery to the user's personal chat with the bot. The notification
 * returns a Reply from toTeamsBot().
 */
class TeamsBotChannel
{
    public function __construct(private readonly BotClient $bot) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('teams.enabled') || ! $this->bot->enabled()) {
            return;
        }

        if (! $notifiable instanceof Model || ! method_exists($notification, 'toTeamsBot')) {
            return;
        }

        $reply = $notification->toTeamsBot($notifiable);
        $identity = TeamsIdentity::forUser($notifiable);

        if (! $reply instanceof Reply || $identity === null) {
            return;
        }

        try {
            $this->bot->send($identity, $reply);
        } catch (TeamsThrottled $e) {
            throw $e;
        } catch (TeamsRequestFailed $e) {
            Log::warning('teams.bot.notify_failed', ['user_id' => $notifiable->getKey(), 'error' => $e->getMessage()]);
        }
    }
}
