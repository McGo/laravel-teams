<?php

namespace MirkoHaaser\Teams\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Log of handled commands, messages and card actions.
 *
 * @property string $id
 */
class TeamsAction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const OK = 'ok';

    public const FAILED = 'failed';

    public const UNKNOWN = 'unknown';

    protected $table = 'teams_actions';

    protected $fillable = ['user_id', 'teams_identity_id', 'kind', 'name', 'status', 'duration_ms', 'error'];
}
