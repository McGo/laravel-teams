<?php

namespace MirkoHaaser\Teams\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use MirkoHaaser\Teams\Clients\GraphClient;
use MirkoHaaser\Teams\Exceptions\TeamsRequestFailed;
use MirkoHaaser\Teams\Exceptions\TeamsThrottled;
use MirkoHaaser\Teams\Models\TeamsIdentity;
use MirkoHaaser\Teams\Models\TeamsNotification;
use MirkoHaaser\Teams\Support\DeepLink;

/**
 * Delivery to the Teams activity feed. Use it in via() next to mail.
 *
 * Installing the app is part of this path on purpose: the RSC permission is
 * granted per user with the installation. A user who joins after the rollout
 * would otherwise never get a notification, and nobody would notice.
 */
class TeamsActivityChannel
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly DeepLink $links,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof Model || ! method_exists($notification, 'toTeamsActivity')) {
            return;
        }

        $message = $notification->toTeamsActivity($notifiable);
        if (! $message instanceof TeamsActivityMessage) {
            return;
        }

        if (! $this->graph->enabled()) {
            $this->log($notifiable, $message, TeamsNotification::SKIPPED, error: 'Teams is disabled');

            return;
        }

        if (TeamsNotification::alreadySent($notifiable->getKey(), $message->type, $message->subjectKey, $message->dedupeSince)) {
            return;
        }

        $identity = TeamsIdentity::forUser($notifiable);
        $recipient = $identity !== null ? $identity->aad_object_id : $notifiable->getAttribute((string) config('teams.user_email_column', 'email'));

        if (! is_string($recipient) || $recipient === '') {
            $this->log($notifiable, $message, TeamsNotification::SKIPPED, error: 'No Entra id known');

            return;
        }

        $this->ensureInstalled($recipient, $identity);

        try {
            $this->graph->sendActivityNotification($recipient, [
                'topic' => [
                    'source' => 'text',
                    'value' => $message->topic ?? (string) config('teams.manifest.name.short', config('app.name')),
                    'webUrl' => $message->webUrl ?? $this->target($message),
                ],
                'activityType' => $message->type,
                'previewText' => ['content' => $message->previewText],
                'templateParameters' => $this->templateParameters($message),
            ]);
        } catch (TeamsThrottled $e) {
            $this->log($notifiable, $message, TeamsNotification::FAILED, 429, $e->getMessage());

            throw $e;
        } catch (TeamsRequestFailed $e) {
            $this->log($notifiable, $message, TeamsNotification::FAILED, $e->status, $e->body ?: $e->getMessage());

            return;
        }

        $this->log($notifiable, $message, TeamsNotification::SENT);
    }

    private function target(TeamsActivityMessage $message): string
    {
        if ($message->tab !== null) {
            return $this->links->toTab($message->tab, $message->subEntityId);
        }

        return $this->links->firstTab() ?? 'https://teams.microsoft.com';
    }

    /**
     * A failure here does not stop the delivery; the following call then
     * fails with a telling message and ends up in the log.
     */
    private function ensureInstalled(string $recipient, ?TeamsIdentity $identity): void
    {
        if ($identity?->installed_at !== null) {
            return;
        }

        $catalogAppId = (string) config('teams.catalog_app_id');
        if ($catalogAppId === '') {
            return;
        }

        try {
            $this->graph->installAppForUser($recipient, $catalogAppId, ['TeamsActivity.Send.User']);
            $identity?->forceFill(['installed_at' => now()])->save();
        } catch (TeamsRequestFailed $e) {
            Log::warning('teams.install_failed', ['recipient' => $recipient, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function templateParameters(TeamsActivityMessage $message): array
    {
        // The reserved type systemDefault carries its text in a fixed parameter.
        $parameters = $message->type === 'systemDefault'
            ? ['systemDefaultText' => $message->previewText]
            : $message->parameters;

        return array_map(
            fn (string $name, string $value) => ['name' => $name, 'value' => $value],
            array_keys($parameters),
            array_values($parameters),
        );
    }

    private function log(Model $user, TeamsActivityMessage $message, string $status, ?int $httpStatus = null, ?string $error = null): void
    {
        TeamsNotification::create([
            'user_id' => $user->getKey(),
            'type' => $message->type,
            'subject_key' => $message->subjectKey,
            'status' => $status,
            'http_status' => $httpStatus,
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
            'sent_at' => $status === TeamsNotification::SENT ? now() : null,
        ]);
    }
}
