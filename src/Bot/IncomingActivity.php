<?php

namespace MirkoHaaser\Teams\Bot;

/**
 * An activity from the Bot Connector, read-only. The raw array stays
 * available for anything the accessors do not cover.
 */
final class IncomingActivity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(public readonly array $raw) {}

    public function type(): string
    {
        return (string) ($this->raw['type'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->raw['name'] ?? '');
    }

    public function id(): string
    {
        return (string) ($this->raw['id'] ?? '');
    }

    public function serviceUrl(): string
    {
        return (string) ($this->raw['serviceUrl'] ?? '');
    }

    public function conversationId(): string
    {
        return (string) ($this->raw['conversation']['id'] ?? '');
    }

    /** personal, groupChat or channel. */
    public function conversationType(): string
    {
        return (string) ($this->raw['conversation']['conversationType'] ?? 'personal');
    }

    public function tenantId(): string
    {
        return (string) ($this->raw['conversation']['tenantId'] ?? $this->raw['channelData']['tenant']['id'] ?? '');
    }

    public function fromAadObjectId(): string
    {
        return (string) ($this->raw['from']['aadObjectId'] ?? '');
    }

    public function fromName(): string
    {
        return (string) ($this->raw['from']['name'] ?? '');
    }

    public function botId(): string
    {
        return (string) ($this->raw['recipient']['id'] ?? '');
    }

    public function replyToId(): string
    {
        return (string) ($this->raw['replyToId'] ?? '');
    }

    /** Message text with <at>mentions</at> removed and whitespace trimmed. */
    public function text(): string
    {
        $text = (string) ($this->raw['text'] ?? '');
        $text = (string) preg_replace('#<at>.*?</at>#is', '', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return array<string, mixed> */
    public function value(): array
    {
        return (array) ($this->raw['value'] ?? []);
    }

    public function isCardSubmit(): bool
    {
        return $this->type() === 'message' && ($this->value()['source'] ?? null) === 'card';
    }

    public function isCardExecute(): bool
    {
        return $this->type() === 'invoke' && $this->name() === 'adaptiveCard/action';
    }

    public function cardVerb(): string
    {
        return $this->isCardExecute()
            ? (string) ($this->value()['action']['verb'] ?? '')
            : (string) ($this->value()['verb'] ?? '');
    }

    /** @return array<string, mixed> */
    public function cardData(): array
    {
        $data = $this->isCardExecute()
            ? (array) ($this->value()['action']['data'] ?? [])
            : $this->value();

        unset($data['verb'], $data['source']);

        return $data;
    }

    /** installationUpdate: add or remove. conversationUpdate: bot added or removed. */
    public function installation(): ?string
    {
        if ($this->type() === 'installationUpdate') {
            return match ((string) ($this->raw['action'] ?? '')) {
                'add' => 'add',
                'remove' => 'remove',
                default => null,
            };
        }

        if ($this->type() === 'conversationUpdate') {
            foreach (['membersAdded' => 'add', 'membersRemoved' => 'remove'] as $field => $result) {
                foreach ((array) ($this->raw[$field] ?? []) as $member) {
                    if (($member['id'] ?? null) === $this->botId()) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }
}
