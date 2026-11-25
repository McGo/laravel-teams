<?php

namespace MirkoHaaser\Teams\Cards;

/**
 * Small helpers for Adaptive Cards. Plain arrays in, plain arrays out; pass
 * your own arrays wherever these are not enough.
 *
 * Cards default to version 1.2, the highest the Teams mobile app renders.
 * Buttons use Action.Submit: in practice the Teams client did not forward
 * Action.Execute to the bot reliably, while Action.Submit arrives as a
 * message activity every time and works with 1.2. The bot then replaces
 * the clicked card. Action.Execute needs version 1.4; pass it to card().
 */
final class Cards
{
    public const VERSION = '1.2';

    public const VERSION_UNIVERSAL_ACTIONS = '1.4';

    /**
     * @param  list<array<string, mixed>>  $body
     * @param  list<array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    public static function card(array $body, array $actions = [], string $version = self::VERSION): array
    {
        return array_filter([
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => $version,
            'body' => $body,
            'actions' => $actions ?: null,
        ], static fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    public static function text(string $text, bool $bold = false, bool $subtle = false, ?string $size = null): array
    {
        return array_filter([
            'type' => 'TextBlock',
            'text' => $text,
            'wrap' => true,
            'weight' => $bold ? 'Bolder' : null,
            'isSubtle' => $subtle ?: null,
            'size' => $size,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, string>  $facts  title => value
     * @return array<string, mixed>
     */
    public static function facts(array $facts): array
    {
        return [
            'type' => 'FactSet',
            'facts' => array_map(
                fn (string $title, string $value) => ['title' => $title, 'value' => $value],
                array_keys($facts),
                array_values($facts),
            ),
        ];
    }

    /**
     * A button that sends verb and data to the bot.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function submit(string $title, string $verb, array $data = []): array
    {
        return [
            'type' => 'Action.Submit',
            'title' => $title,
            'data' => array_merge($data, ['verb' => $verb, 'source' => 'card']),
        ];
    }

    /**
     * Universal Action Model button with a fallback to Action.Submit, for
     * clients that do deliver the invoke. The card needs version 1.4:
     * Cards::card($body, $actions, Cards::VERSION_UNIVERSAL_ACTIONS).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function execute(string $title, string $verb, array $data = []): array
    {
        return [
            'type' => 'Action.Execute',
            'title' => $title,
            'verb' => $verb,
            'data' => $data,
            'fallback' => self::submit($title, $verb, $data),
        ];
    }

    /** @return array<string, mixed> */
    public static function openUrl(string $title, string $url): array
    {
        return ['type' => 'Action.OpenUrl', 'title' => $title, 'url' => $url];
    }

    /**
     * A card without buttons, used to replace a clicked card with the result.
     *
     * @return array<string, mixed>
     */
    public static function result(string $text, ?string $detail = null): array
    {
        return self::card(array_values(array_filter([
            self::text($text, bold: true),
            $detail !== null ? self::text($detail, subtle: true) : null,
        ])));
    }
}
