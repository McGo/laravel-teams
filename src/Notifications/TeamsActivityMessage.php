<?php

namespace MirkoHaaser\Teams\Notifications;

/**
 * What a notification hands to the activity feed channel.
 *
 * @param  string  $type  activityType, declared in the manifest section
 * @param  string  $subjectKey  identifies the subject for the duplicate lock
 * @param  string  $previewText  teaser; Teams shows the first 150 characters
 * @param  array<string, string>  $parameters  values for the templateText
 * @param  string|null  $tab  tab the click leads to (defaults to the first tab)
 * @param  string|null  $subEntityId  target inside the tab
 * @param  \DateTimeInterface|null  $dedupeSince  an earlier delivery blocks only from here on (null = forever)
 * @param  string|null  $topic  text of the topic line (defaults to the app name)
 * @param  string|null  $webUrl  own click target; must be a Teams deep link, Graph rejects anything else with 400
 */
final class TeamsActivityMessage
{
    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public readonly string $type,
        public readonly string $subjectKey,
        public readonly string $previewText,
        public readonly array $parameters = [],
        public readonly ?string $tab = null,
        public readonly ?string $subEntityId = null,
        public readonly ?\DateTimeInterface $dedupeSince = null,
        public readonly ?string $topic = null,
        public readonly ?string $webUrl = null,
    ) {}
}
