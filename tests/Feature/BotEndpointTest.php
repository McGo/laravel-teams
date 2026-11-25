<?php

namespace MirkoHaaser\Teams\Tests\Feature;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Bot\Contracts\HandlesCardAction;
use MirkoHaaser\Teams\Bot\Contracts\HandlesCommand;
use MirkoHaaser\Teams\Bot\Contracts\HandlesMessage;
use MirkoHaaser\Teams\Bot\Events\BotInstalled;
use MirkoHaaser\Teams\Bot\IncomingActivity;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Cards\Cards;
use MirkoHaaser\Teams\Facades\Teams;
use MirkoHaaser\Teams\Models\TeamsAction;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Tests\TestCase;

class BotEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMicrosoft();
    }

    public function test_without_bot_app_id_the_endpoint_is_closed(): void
    {
        config(['teams.bot.app_id' => '']);

        $this->postActivity($this->activity())->assertNotFound();
    }

    public function test_rejects_missing_and_foreign_tokens(): void
    {
        $this->postJson('/api/teams/bot/messages', $this->activity())->assertUnauthorized();
        $this->postActivity($this->activity(), $this->botToken(['aud' => 'other-bot']))->assertUnauthorized();
        $this->postActivity($this->activity(), $this->botToken(['iss' => 'https://evil.example']))->assertUnauthorized();
        $this->postActivity($this->activity(), $this->botToken(['serviceurl' => 'https://evil.example/']))->assertUnauthorized();
    }

    public function test_rejects_keys_not_endorsed_for_teams(): void
    {
        $this->fakeMicrosoftAgain(['skype']);

        $this->postActivity($this->activity())->assertUnauthorized();
    }

    public function test_remembers_the_conversation_from_any_activity(): void
    {
        $fake = Teams::fake();
        $this->linkedUser();

        $this->postActivity($this->activity())->assertOk();

        $conversation = TeamsConversation::sole();
        $this->assertSame('a:conversation-1', $conversation->conversation_id);
        $this->assertSame(TeamsConversation::PERSONAL, $conversation->type);
        $this->assertSame(TeamsIdentity::sole()->id, $conversation->teams_identity_id);
        $fake->assertMessageSent('does not take text messages');
    }

    public function test_unlinked_users_get_a_hint_and_no_handler_runs(): void
    {
        $fake = Teams::fake();
        Teams::onMessage(ExplodingHandler::class);

        $this->postActivity($this->activity())->assertOk();

        $fake->assertMessageSent('not linked yet');
        $this->assertSame(0, TeamsIdentity::count());
    }

    public function test_commands_are_routed_by_the_first_word(): void
    {
        $fake = Teams::fake();
        Teams::command('echo', EchoCommand::class);
        Teams::onMessage(FallbackHandler::class);
        $this->linkedUser();

        $this->postActivity($this->activity(['text' => '<at>Bot</at> Echo hello   world']))->assertOk();
        $fake->assertMessageSent('echo: hello world');

        $this->postActivity($this->activity(['text' => 'something else']))->assertOk();
        $fake->assertMessageSent('fallback: something else');

        $this->assertSame(['command', 'message'], TeamsAction::orderBy('created_at')->pluck('kind')->all());
    }

    public function test_help_lists_the_commands(): void
    {
        $fake = Teams::fake();
        Teams::command('echo', EchoCommand::class);
        $this->linkedUser();

        $this->postActivity($this->activity(['text' => 'what?']))->assertOk();

        $fake->assertMessageSent('**echo**: Repeats the text');
    }

    public function test_card_submit_replaces_the_clicked_card(): void
    {
        $fake = Teams::fake();
        Teams::action('item.done', DoneAction::class);
        $this->linkedUser();

        $this->postActivity($this->activity([
            'text' => null,
            'replyToId' => 'card-activity',
            'value' => ['verb' => 'item.done', 'source' => 'card', 'item' => 42],
        ]))->assertOk();

        $this->assertSame('card-activity', $fake->bot->updated[0]['activity_id']);
        $fake->assertCardSent(fn ($card) => $card['body'][0]['text'] === 'Done: 42');
    }

    public function test_card_execute_answers_with_an_invoke_response(): void
    {
        Teams::fake();
        Teams::action('item.done', DoneAction::class);
        $this->linkedUser();

        $this->postActivity($this->activity([
            'type' => 'invoke',
            'name' => 'adaptiveCard/action',
            'value' => ['action' => ['type' => 'Action.Execute', 'verb' => 'item.done', 'data' => ['item' => 7]]],
        ]))
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('value.body.0.text', 'Done: 7');
    }

    public function test_unknown_verbs_get_a_result_card(): void
    {
        Teams::fake();
        $this->linkedUser();

        $this->postActivity($this->activity([
            'type' => 'invoke', 'name' => 'adaptiveCard/action',
            'value' => ['action' => ['verb' => 'nope']],
        ]))->assertJsonPath('value.body.0.text', 'This action is not available.');

        $this->assertSame(TeamsAction::UNKNOWN, TeamsAction::sole()->status);
    }

    public function test_installation_marks_the_identity_and_fires_an_event(): void
    {
        Event::fake([BotInstalled::class]);
        Teams::fake();
        $this->linkedUser();

        $this->postActivity($this->activity(['type' => 'installationUpdate', 'action' => 'add', 'text' => null]))->assertOk();

        $this->assertNotNull(TeamsIdentity::sole()->installed_at);
        Event::assertDispatched(BotInstalled::class);
    }

    private function fakeMicrosoftAgain(array $endorsements): void
    {
        // Http::fake stacks; start from a fresh factory.
        $this->app->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
        Cache::flush();
        $this->fakeMicrosoft($endorsements);
    }
}

class EchoCommand implements HandlesCommand
{
    public function description(): string
    {
        return 'Repeats the text';
    }

    public function handle(string $arguments, IncomingActivity $activity, TeamsIdentity $identity): ?Reply
    {
        return Reply::text("echo: {$arguments}");
    }
}

class FallbackHandler implements HandlesMessage
{
    public function handle(IncomingActivity $activity, TeamsIdentity $identity): ?Reply
    {
        return Reply::text('fallback: '.$activity->text());
    }
}

class ExplodingHandler implements HandlesMessage
{
    public function handle(IncomingActivity $activity, TeamsIdentity $identity): ?Reply
    {
        throw new \RuntimeException('must not run');
    }
}

class DoneAction implements HandlesCardAction
{
    public function handle(array $data, IncomingActivity $activity, TeamsIdentity $identity): array
    {
        return Cards::result('Done: '.$data['item']);
    }
}
