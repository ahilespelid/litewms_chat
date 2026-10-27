<?php

namespace App\Console\Commands;

use App\Models\ChatApp;
use Illuminate\Console\Command;

class ChatOriginManage extends Command
{
    protected $signature = 'chat:origin
        {action : list | add | remove}
        {origin? : Сайт https://host[:port] или шаблон поддоменов https://*.example.ru}
        {--app= : Публичный ключ приложения (lw_pub_…); можно не указывать, если приложение одно}';

    protected $description = 'Список сайтов приложения чата: добавить или убрать сайт без пересоздания приложения и ключей';

    public function handle(): int
    {
        $app = $this->app();
        if (! $app) {
            return self::FAILURE;
        }

        $action = $this->argument('action');
        $origins = array_values($app->allowed_origins ?? []);

        if ($action === 'list') {
            foreach ($origins as $o) {
                $this->line($o);
            }
            $this->components->info("Приложение «{$app->name}»: сайтов ".count($origins));

            return self::SUCCESS;
        }

        if (! in_array($action, ['add', 'remove'], true)) {
            $this->components->error('Действие: list, add или remove');

            return self::INVALID;
        }

        $origin = rtrim((string) $this->argument('origin'), '/');
        if (! ChatApp::validOrigin($origin)) {
            $this->components->error('Нужен адрес вида https://host[:port] или шаблон https://*.example.ru (без пути; http только для localhost)');

            return self::INVALID;
        }

        $origins = $action === 'add'
            ? array_values(array_unique([...$origins, $origin]))
            : array_values(array_filter($origins, fn ($o) => $o !== $origin));

        $app->update(['allowed_origins' => $origins]);
        $this->components->info(($action === 'add' ? 'Добавлен: ' : 'Убран: ').$origin.'. Сайтов у приложения: '.count($origins));

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
