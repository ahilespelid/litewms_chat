<?php

namespace Tests\Feature;

use App\Models\Participant;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_handoff_token_is_exchanged_for_bearer_and_creates_participant(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        $jwt = $this->handoff($app, $secret, '42', ['name' => 'Анна', 'role' => 'Продавец', 'email' => 'a@x.ru', 'operator' => true]);

        $r = $this->postJson('/api/v1/auth/exchange', ['token' => $jwt])->assertOk();

        $r->assertJsonPath('data.participant.name', 'Анна')
            ->assertJsonPath('data.participant.external_id', '42')
            ->assertJsonPath('data.participant.is_operator', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.app.key', $app->key);

        $this->assertDatabaseHas('participants', ['app_id' => $app->id, 'external_id' => '42', 'role' => 'Продавец']);

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$r->json('data.token')])
            ->assertOk()->assertJsonPath('data.participant.name', 'Анна');
    }

    public function test_second_login_updates_profile_instead_of_duplicating(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '7', ['name' => 'Было'])])->assertOk();
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '7', ['name' => 'Стало'])])->assertOk();

        $this->assertSame(1, Participant::where('external_id', '7')->count());
        $this->assertSame('Стало', Participant::where('external_id', '7')->value('name'));
    }

    public function test_handoff_is_single_use(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();
        $jwt = $this->handoff($app, $secret, '1');

        $this->postJson('/api/v1/auth/exchange', ['token' => $jwt])->assertOk();
        $this->postJson('/api/v1/auth/exchange', ['token' => $jwt])->assertStatus(401);
    }

    public function test_forged_expired_and_overlong_tokens_are_rejected(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        // чужая подпись
        $forged = JWT::encode(['iss' => $app->key, 'sub' => '1', 'exp' => time() + 60, 'jti' => 'a'], str_repeat('x', 40), 'HS256');
        $this->postJson('/api/v1/auth/exchange', ['token' => $forged])->assertStatus(401);

        // просрочен
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1', [], -3600)])->assertStatus(401);

        // живёт слишком долго
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1', [], 86400)])->assertStatus(401);

        // нет jti
        $noJti = JWT::encode(['iss' => $app->key, 'sub' => '1', 'exp' => time() + 60], $secret, 'HS256');
        $this->postJson('/api/v1/auth/exchange', ['token' => $noJti])->assertStatus(401);

        // alg none
        $none = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=').'.'
            .rtrim(strtr(base64_encode(json_encode(['iss' => $app->key, 'sub' => '1', 'exp' => time() + 60, 'jti' => 'z'])), '+/', '-_'), '=').'.';
        $this->postJson('/api/v1/auth/exchange', ['token' => $none])->assertStatus(401);

        // мусор
        $this->postJson('/api/v1/auth/exchange', ['token' => 'abc'])->assertStatus(401);
        $this->assertSame(0, Participant::count());
    }

    public function test_token_of_other_app_cannot_be_signed_with_this_apps_secret(): void
    {
        ['app' => $a, 'secret' => $secretA] = $this->makeApp();
        ['app' => $b] = $this->makeApp();

        // iss — приложение B, подпись — секрет приложения A
        $jwt = JWT::encode(['iss' => $b->key, 'sub' => '1', 'exp' => time() + 60, 'jti' => 'q'], $secretA, 'HS256');
        $this->postJson('/api/v1/auth/exchange', ['token' => $jwt])->assertStatus(401);
    }

    public function test_foreign_origin_is_rejected_but_server_and_own_origin_are_fine(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1')], ['Origin' => 'https://evil.example'])->assertStatus(403);
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1')])->assertOk();
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '2')], ['Origin' => config('app.url')])->assertOk();
    }

    public function test_blocked_participant_cannot_login_and_loses_tokens(): void
    {
        ['app' => $app, 'secret' => $secret, 'server_token' => $st] = $this->makeApp();
        [$p, $h] = $this->actor($app, '5');

        $this->getJson('/api/v1/me', $h)->assertOk();
        $this->postJson('/api/v1/server/participants/5/block', [], $this->server($st))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me', $h)->assertStatus(401);
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '5')])->assertStatus(403);

        $this->postJson('/api/v1/server/participants/5/unblock', [], $this->server($st))->assertOk();
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '5')])->assertOk();
    }

    public function test_inactive_app_is_closed(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();
        $app->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1')])->assertStatus(401);
    }

    public function test_logout_revokes_current_token(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();
        $token = $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '9')])->json('data.token');
        $h = ['Authorization' => 'Bearer '.$token];

        $this->postJson('/api/v1/auth/logout', [], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', $h)->assertStatus(401);
    }

    public function test_guest_flow_requires_enabling_and_returns_resume_link(): void
    {
        ['app' => $app] = $this->makeApp();
        $this->postJson('/api/v1/auth/guest', ['app_key' => $app->key, 'name' => 'Гость'])->assertStatus(403);

        $app->update(['settings' => ['guest_enabled' => true]]);
        $r = $this->postJson('/api/v1/auth/guest', ['app_key' => $app->key, 'name' => 'Гость'])->assertCreated();
        $r->assertJsonPath('data.participant.kind', 'guest');
        $secret = $r->json('data.link_secret');
        $this->assertNotEmpty($r->json('data.conversation_id'));

        $this->postJson('/api/v1/auth/link', ['secret' => $secret])->assertOk()->assertJsonPath('data.participant.name', 'Гость');
        $this->postJson('/api/v1/auth/link', ['secret' => 'nope'])->assertStatus(401);
    }

    public function test_old_tokens_are_pruned_to_limit(): void
    {
        config(['chat.max_tokens_per_participant' => 3]);
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, '1')])->assertOk();
        }

        $this->assertSame(3, Participant::where('external_id', '1')->first()->tokens()->count());
    }
}
