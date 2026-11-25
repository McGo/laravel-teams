<?php

namespace MirkoHaaser\Teams\Tests\Feature;

use Illuminate\Notifications\Notification;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Facades\Teams;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Models\TeamsNotification;
use MirkoHaaser\Teams\Notifications\TeamsActivityChannel;
use MirkoHaaser\Teams\Notifications\TeamsActivityMessage;
use MirkoHaaser\Teams\Tests\Fixtures\User;
use MirkoHaaser\Teams\Tests\TestCase;

class NotificationsTest extends TestCase
{
    public function test_activity_feed_installs_sends_and_locks_duplicates(): void
    {
        $fake = Teams::fake();
        $user = $this->linkedUser();

        $user->notify(new ReportReady);
        $user->notify(new ReportReady);

        $this->assertCount(1, $fake->graph->notifications);
        $this->assertSame(['aad-user-1'], $fake->graph->installed);
        $fake->assertActivityNotified($user, 'reportReady');

        $payload = $fake->graph->notifications[0]['payload'];
        $this->assertStringStartsWith('https://teams.microsoft.com/l/entity/'.self::APP.'/items?context=', $payload['topic']['webUrl']);
        $this->assertSame([['name' => 'name', 'value' => 'Q3']], $payload['templateParameters']);
        $this->assertNotNull(TeamsIdentity::sole()->installed_at);
        $this->assertSame(TeamsNotification::SENT, TeamsNotification::sole()->status);
    }

    public function test_disabled_integration_only_logs(): void
    {
        config(['teams.enabled' => false]);
        $user = $this->linkedUser();

        $user->notify(new ReportReady);

        $this->assertSame(TeamsNotification::SKIPPED, TeamsNotification::sole()->status);
    }

    public function test_bot_channel_creates_a_personal_conversation_when_needed(): void
    {
        $fake = Teams::fake();
        $user = $this->linkedUser();
        TeamsConversation::create([
            'teams_identity_id' => null, 'type' => TeamsConversation::CHANNEL,
            'service_url' => self::SERVICE_URL, 'conversation_id' => '19:channel',
        ]);
        // service URL known from an earlier personal-less contact
        TeamsConversation::query()->update(['teams_identity_id' => TeamsIdentity::sole()->id]);

        $user->notify(new BotHello);

        $fake->assertMessageSent('Hello Jane');
        $this->assertSame('fake-conversation-1', $fake->bot->sent[0]['conversation_id']);
        $this->assertSame(1, TeamsConversation::where('type', 'personal')->count());
    }

    public function test_nothing_sent_without_identity(): void
    {
        $fake = Teams::fake();
        $user = User::create(['name' => 'Bob', 'email' => 'bob@contoso.com']);

        $user->notify(new BotHello);

        $fake->assertNothingSent();
    }
}

class ReportReady extends Notification
{
    public function via(object $notifiable): array
    {
        return [TeamsActivityChannel::class];
    }

    public function toTeamsActivity(object $notifiable): TeamsActivityMessage
    {
        return new TeamsActivityMessage(
            type: 'reportReady',
            subjectKey: 'report:q3',
            previewText: 'Report Q3 is ready',
            parameters: ['name' => 'Q3'],
            tab: 'items',
            subEntityId: 'report-q3',
        );
    }
}

class BotHello extends Notification
{
    public function via(object $notifiable): array
    {
        return ['teams-bot'];
    }

    public function toTeamsBot(object $notifiable): Reply
    {
        return Reply::text('Hello '.$notifiable->name);
    }
}
