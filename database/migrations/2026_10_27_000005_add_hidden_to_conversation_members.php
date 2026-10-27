<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Удалить навсегда» — мягкое: диалог, сообщения, вложения и контекст остаются в БД и на диске
        // для аудита, но нигде не показываются и недоступны ни участникам, ни API.
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('archived_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('participants')->nullOnDelete();
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            // «Удалить для себя»: диалог скрыт у этого участника, у остальных остаётся.
            // Новое сообщение снимает скрытие, но история до hidden_from_message_id ему больше не показывается.
            $table->timestamp('hidden_at')->nullable()->after('left_at');
            $table->unsignedBigInteger('hidden_from_message_id')->nullable()->after('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deleted_at');
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropColumn(['hidden_at', 'hidden_from_message_id']);
        });
    }
};
