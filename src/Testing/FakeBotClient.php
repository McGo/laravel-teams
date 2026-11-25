<?php

namespace MirkoHaaser\Teams\Testing;

use MirkoHaaser\Teams\Clients\BotClient;

/** Records what would go to the Bot Connector. */
class FakeBotClient extends BotClient
{
    /** @var list<array{service_url: string, conversation_id: string, activity: array<string, mixed>}> */
    public array $sent = [];

    /** @var list<array{conversation_id: string, activity_id: string, card: array<string, mixed>}> */
    public array $updated = [];

    private int $conversations = 0;

    public function __construct()
    {
        parent::__construct('fake-bot', 'fake', 'https://login.invalid', 'fake-tenant');
    }

    public function accessToken(): string
    {
        return 'fake-token';
    }

    public function sendActivity(string $serviceUrl, string $conversationId, array $activity): void
    {
        $this->sent[] = ['service_url' => $serviceUrl, 'conversation_id' => $conversationId, 'activity' => $activity];
    }

    public function updateCard(string $serviceUrl, string $conversationId, string $activityId, array $card): void
    {
        $this->updated[] = ['conversation_id' => $conversationId, 'activity_id' => $activityId, 'card' => $card];
    }

    public function createConversation(string $serviceUrl, string $aadObjectId, ?string $tenantId = null): string
    {
        return 'fake-conversation-'.(++$this->conversations);
    }
}
