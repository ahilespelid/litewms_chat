<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Порядок закреплённых диалогов у каждого участника свой: меньше число — выше в списке.
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->integer('pin_order')->nullable()->after('pinned_at');
        });

        // Уже закреплённые: порядок как раньше — свежее закрепление выше.
        $rows = DB::table('conversation_members')->whereNotNull('pinned_at')->orderBy('participant_id')->orderByDesc('pinned_at')->get(['id', 'participant_id']);
        $pos = [];
        foreach ($rows as $r) {
            $pos[$r->participant_id] = ($pos[$r->participant_id] ?? -1) + 1;
            DB::table('conversation_members')->where('id', $r->id)->update(['pin_order' => $pos[$r->participant_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropColumn('pin_order');
        });
    }
};
