<?php

namespace MirkoHaaser\Teams\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use PHPUnit\Framework\Assert;

/**
 * Returned by Teams::fake(). Assertions over everything the application
 * tried to send to Teams.
 */
class TeamsFake
{
    public function __construct(public readonly FakeBotClient $bot, public readonly FakeGraphClient $graph) {}

    /** Assert a text message went out, optionally containing $text. */
    public function assertMessageSent(?string $text = null): void
    {
        $texts = array_map(fn ($s) => (string) ($s['activity']['text'] ?? ''), $this->messages());

        Assert::assertNotEmpty(array_filter($texts, fn ($t) => $t !== '' && ($text === null || str_contains($t, $text))),
            'No Teams message'.($text ? " containing [{$text}]" : '').' was sent. Sent: '.json_encode($texts));
    }

    /** Assert a card went out (new or replacing one), optionally matching a callback. */
    public function assertCardSent(?callable $match = null): void
    {
        $cards = array_merge(
            array_map(fn ($s) => $s['activity']['attachments'][0]['content'] ?? null, $this->messages()),
            array_map(fn ($u) => $u['card'], $this->bot->updated),
        );
        $cards = array_values(array_filter($cards));

        Assert::assertNotEmpty($match ? array_filter($cards, $match) : $cards, 'No matching Teams card was sent.');
    }

    public function assertActivityNotified(Model|Authenticatable|null $user = null, ?string $type = null): void
    {
        $objectId = $user instanceof Model ? TeamsIdentity::forUser($user)?->aad_object_id : null;

        $hits = array_filter($this->graph->notifications, fn ($n) => ($type === null || ($n['payload']['activityType'] ?? null) === $type)
            && ($objectId === null || $n['user'] === $objectId));

        Assert::assertNotEmpty($hits, 'No matching activity feed notification was sent.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->messages(), 'Teams messages were sent.');
        Assert::assertSame([], $this->bot->updated, 'Teams cards were updated.');
        Assert::assertSame([], $this->graph->notifications, 'Activity feed notifications were sent.');
    }

    /** @return list<array{service_url: string, conversation_id: string, activity: array<string, mixed>}> */
    private function messages(): array
    {
        return array_values(array_filter($this->bot->sent, fn ($s) => ($s['activity']['type'] ?? '') === 'message'));
    }
}
