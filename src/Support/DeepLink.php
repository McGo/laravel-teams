<?php

namespace MirkoHaaser\Teams\Support;

use InvalidArgumentException;

/**
 * Links into a tab of the personal app. Only tabs from the manifest section
 * of the config are accepted; an unknown tab is a programming error.
 */
class DeepLink
{
    /** Windows clients stop at 2048 characters. Longer links lose their context. */
    private const MAX_LENGTH = 2048;

    /**
     * @param  list<string>  $tabs  entity ids
     */
    public function __construct(private readonly string $appId, private readonly array $tabs) {}

    public function toTab(string $tab, ?string $subEntityId = null): string
    {
        if (! in_array($tab, $this->tabs, true)) {
            throw new InvalidArgumentException("Teams: unknown tab [{$tab}]");
        }

        $base = "https://teams.microsoft.com/l/entity/{$this->appId}/{$tab}";

        if ($subEntityId === null || $subEntityId === '') {
            return $base;
        }

        $link = $base.'?context='.rawurlencode((string) json_encode(['subEntityId' => $subEntityId], JSON_UNESCAPED_UNICODE));

        return mb_strlen($link) <= self::MAX_LENGTH ? $link : $base;
    }

    public function firstTab(): ?string
    {
        return isset($this->tabs[0]) ? $this->toTab($this->tabs[0]) : null;
    }
}
