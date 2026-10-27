<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('app_id')->constrained('chat_apps')->cascadeOnDelete();
            $table->string('type', 16);                       // direct | group | support
            $table->string('title')->nullable();
            // Контекст — «к чему привязан диалог» на сайте-хозяине: заявка, товар, документ…
            $table->string('context_type', 64)->nullable();
            $table->string('context_id', 191)->nullable();
            $table->string('context_title')->nullable();
            $table->string('context_url', 500)->nullable();
            $table->string('unique_key', 64)->nullable()->unique(); // идемпотентность find-or-create
            $table->foreignId('created_by')->nullable()->constrained('participants')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['app_id', 'context_type', 'context_id']);
            $table->index(['app_id', 'type', 'last_message_at']);
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('role', 16)->default('member');   // owner | member
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'participant_id']);
            $table->index('participant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }
};
