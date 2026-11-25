<?php

namespace MirkoHaaser\Teams\Console;

use Illuminate\Console\Command;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Cards\Cards;
use MirkoHaaser\Teams\Clients\BotClient;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Support\DeepLink;
use Throwable;

class SendTestCommand extends Command
{
    protected $signature = 'teams:send-test {user : User key} {--type=systemDefault : Activity type for the feed test}';

    protected $description = 'Send a test card to the bot chat and a test notification to the activity feed';

    public function handle(BotClient $bot, GraphClient $graph, DeepLink $links): int
    {
        $identity = TeamsIdentity::query()->where('user_id', $this->argument('user'))->first();

        if ($identity === null) {
            $this->components->error('No Teams identity for this user. Open a tab once or use teams:link.');

            return self::FAILURE;
        }

        $ok = true;

        try {
            $bot->send($identity, Reply::card(Cards::result('Test from '.config('app.name'), now()->toDateTimeString())));
            $this->components->twoColumnDetail('Bot chat', '<fg=green>sent</>');
        } catch (Throwable $e) {
            $ok = false;
            $this->components->twoColumnDetail('Bot chat', '<fg=red>'.$e->getMessage().'</>');
        }

        try {
            $graph->sendActivityNotification($identity->aad_object_id, [
                'topic' => ['source' => 'text', 'value' => (string) config('app.name'), 'webUrl' => $links->firstTab() ?? 'https://teams.microsoft.com'],
                'activityType' => (string) $this->option('type'),
                'previewText' => ['content' => 'Test from '.config('app.name')],
                'templateParameters' => [['name' => 'systemDefaultText', 'value' => 'Test from '.config('app.name')]],
            ]);
            $this->components->twoColumnDetail('Activity feed', '<fg=green>sent</>');
        } catch (Throwable $e) {
            $ok = false;
            $this->components->twoColumnDetail('Activity feed', '<fg=red>'.$e->getMessage().'</>');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
