<?php

namespace MirkoHaaser\Teams\Bot;

/**
 * What a handler answers: text (Markdown as Teams renders it) or an Adaptive
 * Card, optionally both.
 */
final class Reply
{
    /**
     * @param  array<string, mixed>|null  $card
     */
    private function __construct(
        public readonly ?string $text = null,
        public readonly ?array $card = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    public static function card(array $card, ?string $text = null): self
    {
        return new self(text: $text, card: $card);
    }

    /** @return array<string, mixed> */
    public function toActivity(): array
    {
        $activity = ['type' => 'message'];

        if ($this->text !== null) {
            $activity['text'] = $this->text;
            $activity['textFormat'] = 'markdown';
        }

        if ($this->card !== null) {
            $activity['attachments'] = [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'content' => $this->card,
            ]];
        }

        return $activity;
    }
}
