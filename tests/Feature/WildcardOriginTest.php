<?php

namespace Tests\Feature;

use App\Models\ChatApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class WildcardOriginTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_шаблон_пропускает_поддомены_но_не_сам_домен_и_не_чужие(): void
    {
        ['app' => $app] = $this->makeApp([], ['https://test.litewms.ru', 'https://*.new.litewms.ru']);

        $this->assertTrue($app->originAllowed('https://litebox.new.litewms.ru'));
        $this->assertTrue($app->originAllowed('https://a.b.new.litewms.ru'));
        $this->assertTrue($app->originAllowed('https://test.litewms.ru'));

        $this->assertFalse($app->originAllowed('https://new.litewms.ru'), 'сам домен шаблоном не покрывается');
        $this->assertFalse($app->originAllowed('http://litebox.new.litewms.ru'), 'схема должна совпасть');
        $this->assertFalse($app->originAllowed('https://litebox.new.litewms.ru:8443'), 'порт должен совпасть');
        $this->assertFalse($app->originAllowed('https://evilnew.litewms.ru'));
        $this->assertFalse($app->originAllowed('https://litebox.new.litewms.ru.evil.com'));
        $this->assertFalse($app->originAllowed('https://x.litewms.ru'));
    }

    public function test_embed_отдаёт_шаблон_в_frame_ancestors(): void
    {
        $this->withoutVite();
        ['app' => $app] = $this->makeApp([], ['https://test.litewms.ru', 'https://*.new.litewms.ru']);

        $this->get('/embed?app='.$app->key)->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://test.litewms.ru https://*.new.litewms.ru");
    }

    public function test_api_принимает_браузерный_запрос_с_поддомена_из_шаблона(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp([], ['https://*.new.litewms.ru']);
        $token = $this->handoff($app, $secret, 'u1');

        $this->postJson('/api/v1/auth/exchange', ['token' => $token], ['Origin' => 'https://litebox.new.litewms.ru'])->assertOk();
        $this->postJson('/api/v1/auth/exchange', ['token' => $this->handoff($app, $secret, 'u2')], ['Origin' => 'https://evil.example.com'])->assertForbidden();
    }

    public function test_команда_chat_app_принимает_только_строгий_шаблон(): void
    {
        foreach (['https://*', 'https://*.com', 'https://*.', 'http://*.new.litewms.ru', 'https://a*.new.litewms.ru', 'https://*.*.litewms.ru'] as $bad) {
            $this->artisan('chat:app', ['name' => 'ЛК', '--origin' => [$bad]])->assertFailed();
        }
        $this->assertSame(0, ChatApp::count());

        $this->artisan('chat:app', ['name' => 'ЛК', '--origin' => ['https://*.new.litewms.ru']])->assertSuccessful();
        $this->assertSame(['https://*.new.litewms.ru'], ChatApp::firstOrFail()->allowed_origins);
    }

    public function test_команда_chat_origin_хранит_список_в_базе(): void
    {
        ['app' => $app] = $this->makeApp([], ['https://test.litewms.ru']);

        $this->artisan('chat:origin', ['action' => 'add', 'origin' => 'https://*.litewms.ru'])->assertSuccessful();
        $this->artisan('chat:origin', ['action' => 'add', 'origin' => 'https://client.example.com/'])->assertSuccessful();
        $this->artisan('chat:origin', ['action' => 'add', 'origin' => 'https://client.example.com'])->assertSuccessful();

        $this->assertSame(
            ['https://test.litewms.ru', 'https://*.litewms.ru', 'https://client.example.com'],
            $app->fresh()->allowed_origins,
            'список лежит в БД, повтор не дублирует'
        );
        $this->assertTrue($app->fresh()->originAllowed('https://litebox.new.litewms.ru'));

        $this->artisan('chat:origin', ['action' => 'remove', 'origin' => 'https://client.example.com'])->assertSuccessful();
        $this->assertFalse($app->fresh()->originAllowed('https://client.example.com'));
        $this->artisan('chat:origin', ['action' => 'list'])->assertSuccessful();
    }

    public function test_команда_chat_origin_отклоняет_мусор_и_требует_приложение(): void
    {
        $this->artisan('chat:origin', ['action' => 'add', 'origin' => 'https://x.com'])->assertFailed();

        ['app' => $app] = $this->makeApp();
        foreach (['*', 'https://*', 'https://x.com/path', 'ftp://x.com', 'http://x.com', 'javascript:1', ''] as $bad) {
            $this->artisan('chat:origin', ['action' => 'add', 'origin' => $bad])->assertFailed();
        }
        $this->assertSame(['https://test.litewms.ru'], $app->fresh()->allowed_origins);

        $this->makeApp([], [], 'Второе');
        $this->artisan('chat:origin', ['action' => 'list'])->assertFailed();
        $this->artisan('chat:origin', ['action' => 'list', '--app' => $app->key])->assertSuccessful();
    }
}
