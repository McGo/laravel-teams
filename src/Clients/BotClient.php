<?php

namespace MirkoHaaser\Teams\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MirkoHaaser\Teams\Bot\Reply;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;
use MirkoHaaser\Teams\Exceptions\TeamsThrottled;
use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Outgoing calls to the Bot Connector. No SDK, a handful of HTTP requests.
 */
class BotClient
{
    use AppTokens;

    public function __construct(
        protected readonly string $appId,
        protected readonly ?string $appPassword,
        protected readonly string $loginUrl,
        protected readonly string $tenantId,
        protected readonly int $timeout = 15,
        protected readonly int $tokenLeeway = 120,
    ) {}

    public function enabled(): bool
    {
        return $this->appId !== '' && ! empty($this->appPassword);
    }

    public function accessToken(): string
    {
        return $this->fetchAppToken(
            $this->loginUrl.'/oauth2/v2.0/token',
            $this->appId,
            (string) $this->appPassword,
            'https://api.botframework.com/.default',
            $this->timeout,
            $this->tokenLeeway,
        );
    }

    /** Send a reply into a known conversation. */
    public function reply(TeamsConversation $conversation, Reply $reply): void
    {
        $this->sendActivity($conversation->service_url, $conversation->conversation_id, $reply->toActivity());
    }

    /**
     * Write to a user's personal chat. Creates the conversation if the bot
     * has not talked to them yet; the service URL comes from any earlier
     * conversation of that user.
     */
    public function send(TeamsIdentity $identity, Reply $reply): void
    {
        $this->reply($this->personalConversation($identity), $reply);
    }

    public function typing(TeamsConversation $conversation): void
    {
        $this->sendActivity($conversation->service_url, $conversation->conversation_id, ['type' => 'typing']);
    }

    /**
     * Replace a message in place, typically the card that was clicked, so
     * its buttons disappear.
     *
     * @param  array<string, mixed>  $card
     */
    public function updateCard(string $serviceUrl, string $conversationId, string $activityId, array $card): void
    {
        $this->check(
            $this->request()->put(
                rtrim($serviceUrl, '/')."/v3/conversations/{$conversationId}/activities/{$activityId}",
                Reply::card($card)->toActivity(),
            ),
            'update activity',
        );
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    public function sendActivity(string $serviceUrl, string $conversationId, array $activity): void
    {
        $this->check(
            $this->request()->post(rtrim($serviceUrl, '/')."/v3/conversations/{$conversationId}/activities", $activity),
            'send activity',
        );
    }

    /** Create a personal conversation and return its id. */
    public function createConversation(string $serviceUrl, string $aadObjectId, ?string $tenantId = null): string
    {
        $response = $this->request()->post(rtrim($serviceUrl, '/').'/v3/conversations', [
            'bot' => ['id' => $this->appId],
            'isGroup' => false,
            'members' => [['id' => $aadObjectId]],
            'channelData' => ['tenant' => ['id' => $tenantId ?: $this->tenantId]],
        ]);

        $this->check($response, 'create conversation');

        return (string) $response->json('id');
    }

    public function personalConversation(TeamsIdentity $identity): TeamsConversation
    {
        $existing = $identity->conversations()->where('type', TeamsConversation::PERSONAL)->latest('last_activity_at')->first();
        if ($existing !== null) {
            return $existing;
        }

        $serviceUrl = $identity->conversations()->value('service_url');
        if (! is_string($serviceUrl) || $serviceUrl === '') {
            throw new TeamsRequestFailed('Teams bot: no service URL known for this user. They have to install the app or write to the bot once.');
        }

        return TeamsConversation::create([
            'teams_identity_id' => $identity->id,
            'tenant_id' => $identity->tenant_id,
            'type' => TeamsConversation::PERSONAL,
            'service_url' => $serviceUrl,
            'conversation_id' => $this->createConversation($serviceUrl, $identity->aad_object_id, $identity->tenant_id),
            'last_activity_at' => now(),
        ]);
    }

    protected function request(): PendingRequest
    {
        return Http::withToken($this->accessToken())->timeout($this->timeout)->acceptJson();
    }

    protected function check(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            throw new TeamsThrottled("Teams bot: throttled on {$what}", (int) ($response->header('Retry-After') ?: 30), (string) $response->body());
        }

        throw new TeamsRequestFailed("Teams bot: {$what} failed", $response->status(), (string) $response->body());
    }
}
