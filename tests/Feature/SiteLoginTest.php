<?php

namespace Tests\Feature;

use App\Models\ChatApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

/**
 * Самостоятельная страница /app: кнопка «Войти через LiteWMS» ведёт в кабинет за токеном.
 */
class SiteLoginTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    private const string LOGIN_URL = 'https://test.litewms.ru/lk2/chat/login';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Настройки, которые страница отдала браузеру в window.CHAT.
     *
     * @return array<string, mixed>
     */
    private function pageConfig(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertSame(1, preg_match('/window\.CHAT = (\{.*?\});<\/script>/s', $html, $m), 'в странице нет window.CHAT');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_страница_приложения_знает_адрес_входа_через_сайт(): void
    {
        ['app' => $app] = $this->makeApp(['login_url' => self::LOGIN_URL]);

        $config = $this->pageConfig('/app?app='.$app->key);

        $this->assertSame(self::LOGIN_URL, $config['loginUrl']);
        $this->assertSame($app->key, $config['appKey']);
    }

    public function test_без_параметра_app_берётся_единственное_приложение_со_входом_через_сайт(): void
    {
        ['app' => $app] = $this->makeApp(['login_url' => self::LOGIN_URL]);
        $this->makeApp([], ['https://other.test'], 'Без входа через сайт');

        $config = $this->pageConfig('/app');

        $this->assertSame($app->key, $config['appKey']);
        $this->assertSame(self::LOGIN_URL, $config['loginUrl']);
    }

    public function test_если_приложений_со_входом_несколько_выбор_за_ссылкой(): void
    {
        $this->makeApp(['login_url' => self::LOGIN_URL], ['https://a.test'], 'Первое');
        $this->makeApp(['login_url' => 'https://b.test/chat/login'], ['https://b.test'], 'Второе');

        $config = $this->pageConfig('/app');

        $this->assertNull($config['appKey']);
        $this->assertNull($config['loginUrl']);
    }

    public function test_у_приложения_без_входа_через_сайт_кнопки_нет(): void
    {
        ['app' => $app] = $this->makeApp();

        $config = $this->pageConfig('/app?app='.$app->key);

        $this->assertNull($config['loginUrl']);
    }

    public function test_отключённое_приложение_не_предлагает_вход(): void
    {
        ['app' => $app] = $this->makeApp(['login_url' => self::LOGIN_URL]);
        $app->forceFill(['is_active' => false])->save();

        $this->assertNull($this->pageConfig('/app?app='.$app->key)['loginUrl']);
        $this->assertNull($this->pageConfig('/app')['loginUrl']);
    }

    public function test_во_встроенном_режиме_адреса_входа_нет(): void
    {
        ['app' => $app] = $this->makeApp(['login_url' => self::LOGIN_URL]);

        // Внутри iframe токен у страницы-хозяина просит сам виджет, редирект там не нужен.
        $this->assertNull($this->pageConfig('/embed?app='.$app->key)['loginUrl']);
    }

    public function test_команда_chat_app_сохраняет_адрес_входа(): void
    {
        $this->artisan('chat:app', ['name' => 'ЛК', '--origin' => ['https://test.litewms.ru'], '--login-url' => self::LOGIN_URL])
            ->assertSuccessful();

        $this->assertSame(self::LOGIN_URL, ChatApp::firstOrFail()->setting('login_url'));
    }

    public function test_команда_chat_app_принимает_только_https_адрес_входа(): void
    {
        foreach (['http://test.litewms.ru/chat/login', 'javascript:alert(1)', '/lk2/chat/login'] as $bad) {
            $this->artisan('chat:app', ['name' => 'ЛК', '--login-url' => $bad])->assertFailed();
        }

        $this->assertSame(0, ChatApp::count());
    }
}
