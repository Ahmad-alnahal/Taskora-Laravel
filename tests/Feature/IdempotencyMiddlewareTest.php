<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Confirms App\Http\Middleware\EnsureIdempotency actually closes the
 * "request succeeded on the server but the client never saw the response"
 * gap: a retried write with the same Idempotency-Key must return the exact
 * original outcome, not re-run the action or issue a fresh side effect
 * (e.g. a second login token). See ai-session-memory.md §23/§24.
 */
class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeating_a_login_with_the_same_key_returns_the_same_token_not_a_new_one(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $payload = ['email' => $user->email, 'password' => 'password'];

        $first = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => $key]);
        $first->assertStatus(200);

        $second = $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => $key]);
        $second->assertStatus(200);

        $this->assertSame($first->json('data.token'), $second->json('data.token'));
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_replayed_response_is_flagged_and_not_reprocessed(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::uuid();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], ['Idempotency-Key' => $key])->assertStatus(200);

        $replay = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], ['Idempotency-Key' => $key]);

        $replay->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(1, IdempotencyKey::where('key', $key)->count());
    }

    public function test_requests_without_the_header_are_not_idempotent(): void
    {
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'password'];

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(200);
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(200);

        // Two separate logins, no header — two separate tokens, as before.
        $this->assertSame(2, $user->fresh()->tokens()->count());
    }

    public function test_different_keys_are_independent(): void
    {
        $user = User::factory()->create();
        $payload = ['email' => $user->email, 'password' => 'password'];

        $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(200);
        $this->postJson('/api/v1/auth/login', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(200);

        $this->assertSame(2, $user->fresh()->tokens()->count());
    }

    public function test_get_requests_ignore_the_header(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $key = (string) Str::uuid();

        $this->getJson('/api/v1/profile', ['Idempotency-Key' => $key])->assertStatus(200);

        $this->assertSame(0, IdempotencyKey::where('key', $key)->count());
    }
}
