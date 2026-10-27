<?php

namespace App\Console\Commands;

use App\Models\ChatApp;
use App\Services\ParticipantService;
use Illuminate\Console\Command;
use App\Models\Participant;

class ChatOperatorCreate extends Command
{
    protected $signature = 'chat:operator {app : Публичный ключ приложения} {name : Имя оператора} {--rotate= : id оператора, чьи ссылку перевыпустить}';

    protected $description = 'Создать оператора поддержки со входом по личной ссылке (без пароля и без сайта-хозяина)';

    public function handle(ParticipantService $participants): int
    {
        $app = ChatApp::where('key', $this->argument('app'))->first();

        if (! $app) {
            $this->components->error('Приложение не найдено.');

            return self::FAILURE;
        }

        if ($id = $this->option('rotate')) {
            $operator = Participant::where('app_id', $app->id)->where('is_operator', true)->findOrFail($id);
        } else {
            $operator = Participant::create([
                'app_id' => $app->id,
                'kind' => 'member',
                'name' => $this->argument('name'),
                'role' => 'Оператор',
                'is_operator' => true,
            ]);
            $participants->joinOpenSupportConversations($operator);
        }

        $secret = $operator->issueLinkSecret();

        $this->components->info("Оператор #{$operator->id} «{$operator->name}». Ссылка для входа (показывается один раз):");
        $this->line(rtrim((string) config('app.url'), '/').'/l/'.$secret);

        return self::SUCCESS;
    }
}
