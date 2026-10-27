<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Participant;
use App\Services\ConversationService;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function viewer(Request $request): Participant
    {
        return $request->user();
    }

    /** Диалог участника: чужой или несуществующий одинаково отвечает 404 (чтобы нельзя было перебирать id). */
    protected function conversation(Request $request, string $id): Conversation
    {
        $conversation = app(ConversationService::class)->findFor($this->viewer($request), $id);
        abort_unless($conversation, 404, 'Диалог не найден.');

        return $conversation;
    }
}
