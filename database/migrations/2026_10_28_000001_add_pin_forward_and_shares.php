<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Закреплённые диалоги — у каждого участника свои.
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->after('hidden_from_message_id');
        });

        // Пересылка: у копии остаётся автор оригинала, как «Переслано от …» в Telegram.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('forwarded_from_name', 190)->nullable()->after('reply_to_id');
            $table->unsignedBigInteger('forwarded_from_message_id')->nullable()->after('forwarded_from_name');
        });

        // Публичная ссылка на своё сообщение (по образцу «Поделиться» в Яндекс Диске): одна ссылка на сообщение.
        Schema::create('message_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('access', 16)->default('members');   // members | app | anyone
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('participants')->nullOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_shares');
        Schema::table('messages', fn (Blueprint $t) => $t->dropColumn(['forwarded_from_name', 'forwarded_from_message_id']));
        Schema::table('conversation_members', fn (Blueprint $t) => $t->dropColumn('pinned_at'));
    }
};
