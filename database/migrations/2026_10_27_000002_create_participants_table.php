<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('chat_apps')->cascadeOnDelete();
            $table->string('external_id', 191)->nullable();   // id пользователя в системе-хозяине (ЛК)
            $table->string('kind', 16)->default('member');    // member | guest
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('role', 64)->nullable();           // подпись роли из ЛК (продавец, склад…)
            $table->boolean('is_operator')->default(false);   // видит все support-диалоги app
            $table->json('meta')->nullable();
            $table->string('link_secret_hash', 64)->nullable()->unique(); // персональная ссылка (гость/оператор)
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'external_id']);
            $table->index(['app_id', 'is_operator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
