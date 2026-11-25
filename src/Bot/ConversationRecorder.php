<?php

namespace MirkoHaaser\Teams\Bot;

use MirkoHaaser\Teams\Models\TeamsConversation;
use MirkoHaaser\Teams\Models\TeamsIdentity;

/**
 * Remembers where an activity came from. Runs for every activity, not only
 * on installation, so a user who simply writes to the bot is reachable later.
 */
class ConversationRecorder
{
    public function identityFor(IncomingActivity $activity): ?TeamsIdentity
    {
        $aad = $activity->fromAadObjectId();
        if ($aad === '') {
            return null;
        }

        $identity = TeamsIdentity::query()
            ->where('aad_object_id', $aad)
            ->when($activity->tenantId() !== '', fn ($q) => $q->where('tenant_id', $activity->tenantId()))
            ->first();

        $identity?->markSeen();

        return $identity;
    }

    public function record(IncomingActivity $activity, ?TeamsIdentity $identity): ?TeamsConversation
    {
        if ($activity->serviceUrl() === '' || $activity->conversationId() === '') {
            return null;
        }

        $type = in_array($activity->conversationType(), [TeamsConversation::GROUP_CHAT, TeamsConversation::CHANNEL], true)
            ? $activity->conversationType()
            : TeamsConversation::PERSONAL;

        $conversation = TeamsConversation::query()->firstOrNew([
            'service_url' => $activity->serviceUrl(),
            'conversation_id' => $activity->conversationId(),
        ]);

        $conversation->fill([
            'type' => $type,
            'tenant_id' => $activity->tenantId() ?: $conversation->tenant_id,
            'last_activity_at' => now(),
        ]);

        // Only personal chats belong to one person.
        if ($type === TeamsConversation::PERSONAL && $identity !== null) {
            $conversation->teams_identity_id = $identity->id;
        }

        $conversation->save();

        return $conversation;
    }
}
