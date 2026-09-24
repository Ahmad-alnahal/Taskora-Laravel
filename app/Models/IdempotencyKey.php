<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Caches the response of a write request keyed by a client-generated
 * `Idempotency-Key` header, so a retried request (e.g. after the original
 * response was lost in transit, even though the server already processed it
 * successfully) returns the exact original response instead of re-running
 * the operation. See App\Http\Middleware\EnsureIdempotency.
 *
 * Rows are otherwise permanent — nothing deletes them once `expires_at`
 * passes, only ignores them in lookups. `Prunable` + the daily `model:prune`
 * schedule in routes/console.php actually removes them, so this table
 * doesn't grow forever.
 */
class IdempotencyKey extends Model
{
    use Prunable;

    protected $fillable = [
        'key',
        'response_status',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now());
    }
}
