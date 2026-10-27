<?php

namespace Tests\Unit;

use App\Exceptions\HandoffException;
use App\Services\Auth\HandoffVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class HandoffVerifierTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    private function expectRejected(string $jwt, string $why): void
    {
        try {
            app(HandoffVerifier::class)->verify($jwt);
            $this->fail("токен должен быть отклонён: $why");
        } catch (HandoffException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_valid_token_passes(): void
    {
        ['app' => $app, 'secret' => $s] = $this->makeApp();
        $r = app(HandoffVerifier::class)->verify($this->handoff($app, $s, '1'));
        $this->assertSame('1', $r['claims']->sub);
        $this->assertTrue($r['app']->is($app));
    }

    public function test_rejects_bad_tokens(): void
    {
        ['app' => $app, 'secret' => $s] = $this->makeApp();

        $this->expectRejected('not-a-jwt', 'мусор');
        $this->expectRejected($this->handoff($app, str_repeat('w', 40), '1'), 'чужая подпись');
        $this->expectRejected($this->handoff($app, $s, '1', ['exp' => time() - 600, 'iat' => time() - 700]), 'просрочен');
        $this->expectRejected($this->handoff($app, $s, '1', [], 3600), 'слишком долгий срок');
        $this->expectRejected($this->handoff($app, $s, '1', ['iss' => 'lw_pub_unknownunknown']), 'неизвестный iss');
        $this->expectRejected($this->handoff($app, $s, '', []), 'пустой sub');

        $noJti = JWT::encode(['iss' => $app->key, 'sub' => '1', 'iat' => time(), 'exp' => time() + 60], $s, 'HS256');
        $this->expectRejected($noJti, 'нет jti');

        // alg=none нельзя
        $b64 = fn ($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $none = $b64('{"alg":"none","typ":"JWT"}').'.'.$b64(json_encode(['iss' => $app->key, 'sub' => '1', 'exp' => time() + 60, 'jti' => 'x'])).'.';
        $this->expectRejected($none, 'alg none');
    }

    public function test_replay_and_inactive_app_are_rejected(): void
    {
        ['app' => $app, 'secret' => $s] = $this->makeApp();
        $jwt = $this->handoff($app, $s, '1');
        app(HandoffVerifier::class)->verify($jwt);
        $this->expectRejected($jwt, 'повтор');

        $app->forceFill(['is_active' => false])->save();
        $this->expectRejected($this->handoff($app, $s, '2'), 'приложение выключено');
    }
}
