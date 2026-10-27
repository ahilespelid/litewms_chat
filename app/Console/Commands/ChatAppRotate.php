<?php

namespace App\Console\Commands;

use App\Models\ChatApp;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ChatAppRotate extends Command
{
    protected $signature = 'chat:app:rotate {key : Публичный ключ приложения} {--secret : Перевыпустить секрет подписи} {--server-token : Перевыпустить серверный токен}';

    protected $description = 'Перевыпустить секреты приложения (старые перестают работать сразу)';

    public function handle(): int
    {
        $app = ChatApp::where('key', $this->argument('key'))->first();

        if (! $app) {
            $this->components->error('Приложение не найдено.');

            return self::FAILURE;
        }

        if (! $this->option('secret') && ! $this->option('server-token')) {
            $this->components->error('Укажите --secret и/или --server-token.');

            return self::FAILURE;
        }

        if ($this->option('secret')) {
            $secret = 'lws_'.Str::random(48);
            $app->secret = $secret;
            $this->line('CHAT_APP_SECRET='.$secret);
        }

        if ($this->option('server-token')) {
            $token = 'lcs_'.Str::random(48);
            $app->server_token_hash = ChatApp::hashServerToken($token);
            $this->line('CHAT_SERVER_TOKEN='.$token);
        }

        $app->save();

        return self::SUCCESS;
    }
}
