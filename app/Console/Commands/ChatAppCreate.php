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
        {--title= : Заголовок в интерфейсе (по умолчанию «Чат»)}
        {--login-url= : Адрес сайта, который выдаёт токен входа и возвращает человека в чат (кнопка «Войти через LiteWMS» на /app), например https://test.litewms.ru/lk2/chat/login}';

    protected $description = 'Подключить сайт к чату: создаёт приложение и печатает ключи (секреты показываются один раз)';

    public function handle(): int
    {
        $login_url = $this->option('login-url');

        // Кнопка входа уводит человека на этот адрес: принимаем только https-ссылку.
        if ($login_url && ! preg_match('#^https://[^\s/]+(/\S*)?$#', $login_url)) {
            $this->components->error('--login-url должен быть полной ссылкой https://…');

            return self::INVALID;
        }

        // Сайты: https://host[:port], шаблон поддоменов https://*.example.ru, http только для localhost.
        foreach ($this->option('origin') as $origin) {
            if (! ChatApp::validOrigin(rtrim($origin, '/'))) {
                $this->components->error("--origin $origin: нужен адрес вида https://host[:port] или шаблон https://*.example.ru");

                return self::INVALID;
            }
        }

        $settings = array_filter([
            'guest_enabled' => $this->option('guests') ?: null,
            'user_conversations' => $this->option('user-conversations') ?: null,
            'directory' => $this->option('directory') ?: null,
            'accent' => $this->option('accent'),
            'title' => $this->option('title'),
            'login_url' => $login_url ?: null,
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
