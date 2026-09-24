<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

trait CreatesIdempotently
{
    /**
     * Create a record on the given relation, or return the record already
     * created by an earlier attempt of the same client-generated request
     * (matched by client_uuid). This is what makes offline sync retries safe:
     * a queued mutation replayed after a dropped connection must not create
     * a duplicate project/task.
     *
     * The existing-record lookup handles the common case (client retries
     * because it never saw the first response). The catch around create()
     * is the race-condition safety net for two near-simultaneous retries —
     * the database's unique(parent_id, client_uuid) constraint is the real
     * source of truth, this just turns that violation into a clean replay
     * instead of a 500.
     *
     * @return array{0: Model, 1: bool} the resolved model and whether it was newly created
     */
    protected function createIdempotently(HasMany $relation, array $data, ?string $clientUuid): array
    {
        if ($clientUuid) {
            $existing = (clone $relation)->where('client_uuid', $clientUuid)->first();

            if ($existing) {
                return [$existing, false];
            }
        }

        try {
            return [$relation->create($data), true];
        } catch (QueryException $e) {
            if ($clientUuid && $this->isUniqueConstraintViolation($e)) {
                $existing = (clone $relation)->where('client_uuid', $clientUuid)->first();

                if ($existing) {
                    return [$existing, false];
                }

                abort(409, 'معرّف الطلب (client_uuid) مستخدم مسبقاً بواسطة حساب آخر');
            }

            throw $e;
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
