<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Каждое подключаемое приложение (ЛК litewms, другой сайт, мобильное приложение) — отдельная «app»:
        // у него свои участники, диалоги и ключи. Данные разных app никогда не пересекаются.
        Schema::create('chat_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 64)->unique();          // публичный ключ (идёт в embed.js и в iss токена)
            $table->text('secret');                        // общий секрет подписи handoff-токенов (encrypted)
            $table->string('server_token_hash', 64)->unique(); // sha256 серверного токена host-бэкенда
            $table->json('allowed_origins')->nullable();   // https://test.litewms.ru, ...
            $table->json('settings')->nullable();          // support_enabled, guest_enabled, user_conversations, directory, accent
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_apps');
    }
};
