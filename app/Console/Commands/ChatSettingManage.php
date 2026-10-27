<?php

namespace App\Console\Commands;

use App\Models\ChatApp;
use App\Models\Participant;
use Illuminate\Console\Command;

class ChatSettingManage extends Command
{
    /** Переключатели в командной строке → ключи в `chat_apps.settings`. */
    private const array SWITCHES = [
        'guests' => 'guest_enabled',
        'user-conversations' => 'user_conversations',
        'directory' => 'directory',
    ];

    protected $signature = 'chat:setting
        {name? : guests | user-conversations | directory (без аргумента — показать все)}
        {state? : on | off (без аргумента — показать значение)}
        {--app= : Публичный ключ приложения (lw_pub_…); можно не указывать, если приложение одно}';

    protected $description = 'Переключатели приложения чата без пересоздания: гости, личные диалоги участников, поиск по участникам';

    public function handle(): int
    {
        $app = $this->app();
        if (! $app) {
            return self::FAILURE;
        }

        $name = $this->argument('name');
        $state = $this->argument('state');

        if ($name !== null && ! isset(self::SWITCHES[$name])) {
            $this->components->error('Переключатель: '.implode(', ', array_keys(self::SWITCHES)));

            return self::INVALID;
        }

        if ($state !== null && ! in_array($state, ['on', 'off'], true)) {
            $this->components->error('Значение: on или off');

            return self::INVALID;
        }

        if ($name === null || $state === null) {
            foreach ($name === null ? self::SWITCHES : [$name => self::SWITCHES[$name]] as $switch => $key) {
                $this->line(str_pad($switch, 20).($app->setting($key) ? 'on' : 'off'));
            }

            return self::SUCCESS;
        }

        $key = self::SWITCHES[$name];
        $app->update(['settings' => array_merge($app->settings ?? [], [$key => $state === 'on'])]);
        $this->components->info("Приложение «{$app->name}»: $name = $state");

        if ($name === 'guests' && $state === 'on' && ! Participant::where('app_id', $app->id)->where('is_operator', true)->whereNull('blocked_at')->exists()) {
            $this->components->warn('Операторов у приложения пока нет: обращения гостей сохранятся, а новый оператор получит их при назначении (chat:operator или operator: true в токене).');
        }

        return self::SUCCESS;
    }

    private function app(): ?ChatApp
    {
        if ($key = $this->option('app')) {
            $app = ChatApp::where('key', $key)->first();
            if (! $app) {
                $this->components->error('Приложение с таким ключом не найдено');
            }

            return $app;
        }

        $apps = ChatApp::all();
        if ($apps->count() !== 1) {
            $this->components->error($apps->isEmpty() ? 'Приложений нет' : 'Приложений несколько, укажите --app=lw_pub_…');

            return null;
        }

        return $apps->first();
    }
}
