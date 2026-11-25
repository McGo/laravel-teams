<?php

namespace MirkoHaaser\Teams\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One delivery attempt to the activity feed.
 *
 * @property string $id
 * @property string $status
 */
class TeamsNotification extends Model
{
    use HasUuids;

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $table = 'teams_notifications';

    protected $fillable = ['user_id', 'type', 'subject_key', 'status', 'http_status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /**
     * Was this subject already delivered? Failed attempts do not block, or a
     * single Graph error would swallow the reminder for good.
     */
    public static function alreadySent(int|string $userId, string $type, string $subjectKey, ?\DateTimeInterface $since = null): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('subject_key', $subjectKey)
            ->where('status', self::SENT)
            ->when($since !== null, fn (Builder $q) => $q->where('sent_at', '>=', $since))
            ->exists();
    }
}
