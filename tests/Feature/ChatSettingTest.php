<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class ChatSettingTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_гости_включаются_и_выключаются_без_потери_остальных_настроек(): void
    {
        ['app' => $app] = $this->makeApp(['title' => 'Поддержка']);
        $this->assertFalse((bool) $app->setting('guest_enabled'));

        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'on'])->assertSuccessful();
        $this->assertTrue($app->fresh()->setting('guest_enabled'));
        $this->assertSame('Поддержка', $app->fresh()->setting('title'));

        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'off'])->assertSuccessful();
        $this->assertFalse($app->fresh()->setting('guest_enabled'));
    }

    public function test_без_значения_команда_показывает_текущее_и_ничего_не_меняет(): void
    {
        ['app' => $app] = $this->makeApp(['guest_enabled' => true]);

        $this->artisan('chat:setting', ['name' => 'guests'])->expectsOutputToContain('on')->assertSuccessful();
        $this->artisan('chat:setting')->assertSuccessful();
        $this->assertTrue($app->fresh()->setting('guest_enabled'));
    }

    public function test_мусор_отклоняется_и_приложение_нужно_выбрать_при_нескольких(): void
    {
        ['app' => $app] = $this->makeApp();

        $this->artisan('chat:setting', ['name' => 'nope', 'state' => 'on'])->assertFailed();
        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'maybe'])->assertFailed();
        $this->assertFalse((bool) $app->fresh()->setting('guest_enabled'));

        $this->makeApp([], [], 'Второе');
        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'on'])->assertFailed();
        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'on', '--app' => $app->key])->assertSuccessful();
        $this->assertTrue($app->fresh()->setting('guest_enabled'));
    }

    public function test_предупреждает_если_у_приложения_нет_операторов(): void
    {
        $this->makeApp();

        $this->artisan('chat:setting', ['name' => 'guests', 'state' => 'on'])
            ->expectsOutputToContain('Операторов у приложения пока нет')
            ->assertSuccessful();
    }
}
