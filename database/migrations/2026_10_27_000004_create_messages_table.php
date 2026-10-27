<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('participants')->nullOnDelete(); // null = системное
            $table->string('type', 16)->default('text');     // text | voice | file | system
            $table->text('body')->nullable();
            $table->text('original_body')->nullable();       // текст до первой правки
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->string('client_uuid', 64)->nullable();   // идемпотентность повторной отправки
            $table->string('voice_path')->nullable();
            $table->string('voice_mime', 100)->nullable();
            $table->unsignedSmallInteger('voice_duration')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();     // мягкое скрытие: место в ленте остаётся
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->unique(['conversation_id', 'client_uuid']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });

        // Токены мобильных приложений (FCM/APNs) — заготовка под будущее приложение.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('platform', 16);                  // ios | android
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('messages');
    }
};
