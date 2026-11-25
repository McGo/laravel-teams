<?php

namespace MirkoHaaser\Teams\Bot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Bot\ActionLog;
use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Exceptions\TeamsThrottled;
use MirkoHaaser\Teams\Models\TeamsAction;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\TeamsManager;
use Throwable;

/**
 * Routes a text message: the first word picks a command, everything else
 * goes to the message handler, and without one the user gets the help text.
 */
class HandleIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $activity
     */
    public function __construct(
        public readonly array $activity,
        public readonly string $identityId,
        public readonly string $conversationId,
    ) {}

    public function handle(TeamsManager $teams, BotClient $bot, ActionLog $log): void
    {
        $identity = TeamsIdentity::find($this->identityId);
        $conversation = TeamsConversation::find($this->conversationId);

        if ($identity === null || $conversation === null) {
            return;
        }

        $activity = new IncomingActivity($this->activity);
        $text = $activity->text();
        [$word, $arguments] = array_pad(explode(' ', $text, 2), 2, '');

        if (config('teams.bot.typing', true)) {
            try {
                $bot->typing($conversation);
            } catch (Throwable) {
                // Cosmetic only.
            }
        }

        $started = microtime(true);
        $command = $word !== '' ? $teams->commandHandler($word) : null;
        $kind = $command ? 'command' : 'message';
        $name = $command ? mb_strtolower($word) : 'message';

        try {
            if ($command !== null) {
                $reply = $command->handle(trim($arguments), $activity, $identity);
            } elseif (($handler = $teams->messageHandler()) !== null) {
                $reply = $handler->handle($activity, $identity);
            } else {
                $reply = $this->help($teams);
                $kind = 'message';
                $name = 'help';
            }

            if ($reply !== null) {
                $bot->reply($conversation, $reply);
            }

            $log->write($identity, $kind, $name, TeamsAction::OK, $started);
        } catch (TeamsThrottled $e) {
            $this->release($e->retryAfter);
        } catch (Throwable $e) {
            $log->write($identity, $kind, $name, TeamsAction::FAILED, $started, $e->getMessage());
            Log::warning('teams.bot.message_failed', ['command' => $name, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function help(TeamsManager $teams): Reply
    {
        $lines = [];
        foreach (array_keys($teams->commands()) as $word) {
            $handler = $teams->commandHandler($word);
            $lines[] = "- **{$word}**: ".($handler?->description() ?? '');
        }

        return Reply::text($lines === []
            ? (string) __('This bot does not take text messages.')
            : (string) __('Available commands:')."\n\n".implode("\n", $lines));
    }
}
