<?php

namespace MirkoHaaser\Teams\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Auth\BotFrameworkTokenValidator;
use MirkoHaaser\Teams\Bot\ActionLog;
use MirkoHaaser\Teams\Bot\ConversationRecorder;
use MirkoHaaser\Teams\Bot\Events\BotInstalled;
use MirkoHaaser\Teams\Bot\Events\BotUninstalled;
use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Jobs\HandleIncomingMessage;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Cards\Cards;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Exceptions\TeamsTokenInvalid;
use MirkoHaaser\Teams\Models\TeamsAction;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Support\DeepLink;
use MirkoHaaser\Teams\TeamsManager;
use Throwable;

/**
 * Messaging endpoint of the bot. Authenticated by the Bot Connector token,
 * not by the application's guards. Without a configured bot it answers 404.
 */
class BotController extends Controller
{
    public function __construct(
        private readonly TeamsManager $teams,
        private readonly BotClient $bot,
        private readonly ConversationRecorder $recorder,
        private readonly ActionLog $log,
        private readonly DeepLink $links,
    ) {}

    public function __invoke(Request $request, BotFrameworkTokenValidator $validator): JsonResponse
    {
        abort_unless((string) config('teams.bot.app_id') !== '', 404);

        $activity = new IncomingActivity($request->all());

        $token = (string) $request->bearerToken();
        abort_if($token === '', 401);

        try {
            $validator->validate($token, $activity->serviceUrl());
        } catch (TeamsTokenInvalid $e) {
            Log::warning('teams.bot.auth_failed', ['error' => $e->getMessage()]);
            abort(401);
        }

        $identity = $this->recorder->identityFor($activity);
        $conversation = $this->recorder->record($activity, $identity);

        if ($installation = $activity->installation()) {
            $this->onInstallation($installation, $activity, $conversation, $identity);

            return response()->json(['ok' => true]);
        }

        if ($activity->isCardExecute()) {
            return $this->onCardExecute($activity, $identity);
        }

        if ($activity->isCardSubmit()) {
            $this->onCardSubmit($activity, $conversation, $identity);

            return response()->json(['ok' => true]);
        }

        if ($activity->type() === 'message' && $conversation !== null) {
            $this->onMessage($activity, $conversation, $identity);
        }

        return response()->json(['ok' => true]);
    }

    private function onInstallation(string $what, IncomingActivity $activity, ?TeamsConversation $conversation, ?TeamsIdentity $identity): void
    {
        if ($conversation === null) {
            return;
        }

        if ($what === 'add') {
            if ($identity !== null && $identity->installed_at === null) {
                $identity->forceFill(['installed_at' => now()])->save();
            }
            event(new BotInstalled($activity, $conversation, $identity));

            return;
        }

        if ($identity !== null && $conversation->type === TeamsConversation::PERSONAL) {
            $identity->forceFill(['installed_at' => null])->save();
        }
        event(new BotUninstalled($activity, $conversation, $identity));
    }

    private function onMessage(IncomingActivity $activity, TeamsConversation $conversation, ?TeamsIdentity $identity): void
    {
        if ($identity === null) {
            $this->replyUnlinked($conversation);

            return;
        }

        HandleIncomingMessage::dispatch($activity->raw, $identity->id, $conversation->id)
            ->onConnection(config('teams.bot.queue_connection'))
            ->onQueue(config('teams.bot.queue'));
    }

    private function onCardSubmit(IncomingActivity $activity, ?TeamsConversation $conversation, ?TeamsIdentity $identity): void
    {
        if ($conversation === null) {
            return;
        }

        if ($identity === null) {
            $this->replyUnlinked($conversation);

            return;
        }

        $card = $this->runAction($activity, $identity);

        try {
            if ($activity->replyToId() !== '') {
                $this->bot->updateCard($conversation->service_url, $conversation->conversation_id, $activity->replyToId(), $card);
            } else {
                $this->bot->reply($conversation, Reply::card($card));
            }
        } catch (Throwable $e) {
            Log::warning('teams.bot.reply_failed', ['error' => $e->getMessage()]);
        }
    }

    private function onCardExecute(IncomingActivity $activity, ?TeamsIdentity $identity): JsonResponse
    {
        $card = $identity !== null
            ? $this->runAction($activity, $identity)
            : Cards::result($this->unlinkedText());

        return response()->json([
            'statusCode' => 200,
            'type' => 'application/vnd.microsoft.card.adaptive',
            'value' => $card,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runAction(IncomingActivity $activity, TeamsIdentity $identity): array
    {
        $verb = $activity->cardVerb();
        $handler = $this->teams->actionHandler($verb);
        $started = microtime(true);

        if ($handler === null) {
            $this->log->write($identity, 'card', $verb, TeamsAction::UNKNOWN, $started);

            return Cards::result((string) __('This action is not available.'));
        }

        try {
            $card = $handler->handle($activity->cardData(), $activity, $identity);
            $this->log->write($identity, 'card', $verb, TeamsAction::OK, $started);

            return $card;
        } catch (Throwable $e) {
            $this->log->write($identity, 'card', $verb, TeamsAction::FAILED, $started, $e->getMessage());
            Log::warning('teams.bot.action_failed', ['verb' => $verb, 'error' => $e->getMessage()]);

            return Cards::result((string) __('That did not work.'), $e->getMessage());
        }
    }

    private function replyUnlinked(TeamsConversation $conversation): void
    {
        try {
            $this->bot->reply($conversation, Reply::text($this->unlinkedText()));
        } catch (Throwable $e) {
            Log::warning('teams.bot.reply_failed', ['error' => $e->getMessage()]);
        }
    }

    private function unlinkedText(): string
    {
        $link = (string) config('teams.app_id') !== '' ? ($this->links->firstTab() ?? '') : '';

        return trim(str_replace('{link}', $link, (string) config('teams.bot.unlinked_reply')), " :\n");
    }
}
