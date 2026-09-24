<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Makes any write request safe to retry: if the client sends an
 * `Idempotency-Key` header and that exact key was already processed, the
 * cached original response is replayed instead of re-running the request.
 *
 * This closes the "request succeeded on the server but the response never
 * reached the client" gap (flaky tunnel/mobile network) — the client can
 * safely retry with the same key and get back the same result, instead of
 * risking a duplicate side effect or being left unsure whether anything
 * happened. See database/migrations/*_create_idempotency_keys_table.php and
 * ai-session-memory.md §23/§24 for the incident that prompted this.
 *
 * A request without the header behaves exactly as before (opt-in, no
 * behavior change for existing/older clients). GET/HEAD are skipped — they
 * are already safe to retry by nature.
 */
class EnsureIdempotency
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || $request->isMethodSafe()) {
            return $next($request);
        }

        $cached = IdempotencyKey::where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            return response($cached->response_body, $cached->response_status)
                ->header('Content-Type', 'application/json')
                ->header('Idempotency-Replayed', 'true');
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        // Only cache responses that represent a definitive outcome — not
        // transient server errors, which the client should be free to retry
        // as a genuinely new attempt.
        if ($response->getStatusCode() < 500) {
            $this->storeIfNew($key, $response);
        }

        return $response;
    }

    private function storeIfNew(string $key, SymfonyResponse $response): void
    {
        try {
            IdempotencyKey::create([
                'key' => $key,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (QueryException $e) {
            // Two near-simultaneous requests with the same key raced each
            // other — the unique constraint on `key` caught it. Whichever
            // wrote first wins; this one's response is simply discarded
            // (the client already got a valid response either way).
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
