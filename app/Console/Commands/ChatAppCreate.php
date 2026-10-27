<?php

namespace App\Console\Commands;

use App\Models\ChatApp;
use Illuminate\Console\Command;

class ChatAppCreate extends Command
{
    protected $signature = 'chat:app
        {name : Название приложения, например "ЛК litewms"}
        {--origin=* : Сайты, которым можно встраивать чат (https://test.litewms.ru). Можно несколько раз}
        {--guests : Разрешить анонимных гостей по ссылке}
        {--user-conversations : Разрешить участникам самим создавать личные диалоги и группы}
        {--directory : Разрешить поиск по участникам приложения}
        {--accent= : Фирменный цвет интерфейса, например #0f766e}
        {--title= : Заголовок в интерфейсе (по умолчанию «Чат»)}';

    protected $description = 'Подключить сайт к чату: создаёт приложение и печатает ключи (секреты показываются один раз)';

    public function handle(): int
    {
        $settings = array_filter([
            'guest_enabled' => $this->option('guests') ?: null,
            'user_conversations' => $this->option('user-conversations') ?: null,
            'directory' => $this->option('directory') ?: null,
            'accent' => $this->option('accent'),
            'title' => $this->option('title'),
        ], fn ($v) => $v !== null);

        ['app' => $app, 'secret' => $secret, 'server_token' => $token] = ChatApp::register(
            $this->argument('name'),
            $this->option('origin'),
            $settings
        );

        $this->components->info("Приложение «{$app->name}» создано. Сохраните ключи — секреты больше не покажутся.");
        $this->newLine();
        $this->line('CHAT_APP_KEY='.$app->key.'          # публичный: идёт в embed.js и в поле iss токена');
        $this->line('CHAT_APP_SECRET='.$secret.'   # подпись handoff-токенов (только на бэкенде сайта!)');
        $this->line('CHAT_SERVER_TOKEN='.$token.'  # серверный API (только на бэкенде сайта!)');

        if (! $app->allowed_origins) {
            $this->newLine();
            $this->components->warn('Список --origin пуст: встроить чат в iframe на сторонний сайт пока нельзя.');
        }

        return self::SUCCESS;
    }
}
