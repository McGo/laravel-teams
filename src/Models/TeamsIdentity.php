<?php

namespace MirkoHaaser\Teams\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An application user as Microsoft knows them. (tenant_id, aad_object_id) is
 * the stable key; the UPN is kept for first contact and for humans.
 *
 * @property string $id
 * @property int|string $user_id
 * @property string $tenant_id
 * @property string $aad_object_id
 * @property string|null $upn
 * @property Carbon|null $installed_at
 * @property Carbon|null $last_seen_at
 */
class TeamsIdentity extends Model
{
    use HasUuids;

    protected $table = 'teams_identities';

    protected $fillable = ['user_id', 'tenant_id', 'aad_object_id', 'upn', 'installed_at', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('teams.user_model');

        return $this->belongsTo($model, 'user_id');
    }

    /** @return HasMany<TeamsConversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(TeamsConversation::class);
    }

    /** @return HasOne<TeamsConversation, $this> */
    public function personalConversation(): HasOne
    {
        return $this->hasOne(TeamsConversation::class)
            ->where('type', TeamsConversation::PERSONAL)
            ->latestOfMany('last_activity_at');
    }

    /**
     * Deliverable means the app is installed for this user. Without it Graph
     * accepts a notification, but it never arrives.
     *
     * @param  Builder<TeamsIdentity>  $query
     * @return Builder<TeamsIdentity>
     */
    public function scopeDeliverable(Builder $query): Builder
    {
        return $query->whereNotNull('installed_at');
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public static function forUser(Model $user): ?self
    {
        return static::query()->where('user_id', $user->getKey())->latest('last_seen_at')->first();
    }
}
