<?php

namespace MirkoHaaser\Teams\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where the bot can write: a personal chat, a group chat or a channel.
 * Remembered from every incoming activity.
 *
 * @property string $id
 * @property string|null $teams_identity_id
 * @property string|null $tenant_id
 * @property string $type
 * @property string $service_url
 * @property string $conversation_id
 * @property Carbon|null $last_activity_at
 */
class TeamsConversation extends Model
{
    use HasUuids;

    public const PERSONAL = 'personal';

    public const GROUP_CHAT = 'groupChat';

    public const CHANNEL = 'channel';

    protected $table = 'teams_conversations';

    protected $fillable = ['teams_identity_id', 'tenant_id', 'type', 'service_url', 'conversation_id', 'last_activity_at'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    /** @return BelongsTo<TeamsIdentity, $this> */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(TeamsIdentity::class, 'teams_identity_id');
    }
}
