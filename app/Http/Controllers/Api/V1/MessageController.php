<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ChatEvent;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Сообщения
 */
class MessageController extends ApiController
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * История диалога
     *
     * Возвращает до `limit` сообщений по возрастанию id. Без параметров — самые свежие. Листать назад:
     * `before` = id самого старого из полученных; вперёд (догрузка после обрыва связи): `after`.
     */
    public function index(Request $request, string $conversation): AnonymousResourceCollection
    {
        $c = $this->conversation($request, $conversation);

        $data = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
            'after' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = $data['limit'] ?? 50;

        $query = Message::where('conversation_id', $c->id)->with(['sender', 'replyTo.sender']);

        // Диалог, который участник «удалял для себя», показывает только то, что написано после этого.
        if ($c->hidden_from_message_id) {
            $query->where('id', '>', $c->hidden_from_message_id);
        }

        if (isset($data['after'])) {
            $items = $query->where('id', '>', $data['after'])->orderBy('id')->limit($limit)->get();
        } else {
            if (isset($data['before'])) {
                $query->where('id', '<', $data['before']);
            }
            $items = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        }

        return MessageResource::collection($items);
    }

    /**
     * Отправить текст
     *
     * `client_uuid` — ваш идентификатор попытки: повтор с тем же значением не создаст дубль (удобно при плохой связи).
     */
    public function store(Request $request, string $conversation): JsonResponse
    {
        $c = $this->conversation($request, $conversation);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.config('chat.message_max_length')],
            'reply_to_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->messages->send($c, $this->viewer($request), [
            'body' => $data['body'],
            'reply_to_id' => $this->replyTarget($c->id, $data['reply_to_id'] ?? null),
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);

        return MessageResource::make($message->load(['sender', 'replyTo.sender']))->response()->setStatusCode(201);
    }

    /**
     * Отправить голосовое
     *
     * Запрос `multipart/form-data`: `audio` (webm/ogg/mp4/m4a/mp3, до 10 МБ) и `duration` в секундах.
     */
    public function storeVoice(Request $request, string $conversation): JsonResponse
    {
        $c = $this->conversation($request, $conversation);

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:'.config('chat.voice_max_kb'), 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,video/mp4,audio/mpeg,audio/x-m4a,audio/aac,audio/wav,audio/x-wav'],
            'duration' => ['required', 'integer', 'min:1', 'max:'.config('chat.voice_max_seconds')],
            'reply_to_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->messages->sendVoice($c, $this->viewer($request), $request->file('audio'), (int) $data['duration'],
            $data['client_uuid'] ?? null, $this->replyTarget($c->id, $data['reply_to_id'] ?? null));

        return MessageResource::make($message->load(['sender', 'replyTo.sender']))->response()->setStatusCode(201);
    }

    /**
     * Отправить файл, фото или видео
     *
     * Запрос `multipart/form-data`, поле `file`, до 25 МБ. Исполняемые и скриптовые типы запрещены.
     */
    public function storeFile(Request $request, string $conversation): JsonResponse
    {
        $c = $this->conversation($request, $conversation);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('chat.file_max_kb'), 'extensions:'.$this->allowedExtensions()],
            'reply_to_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $message = $this->messages->sendFile($c, $this->viewer($request), $request->file('file'),
            $data['client_uuid'] ?? null, $this->replyTarget($c->id, $data['reply_to_id'] ?? null));

        return MessageResource::make($message->load(['sender', 'replyTo.sender']))->response()->setStatusCode(201);
    }

    /**
     * Изменить своё текстовое сообщение
     *
     * Исходный текст сохраняется: `original_body` видят все участники («было: …»).
     */
    public function update(Request $request, string $conversation, int $message): MessageResource
    {
        $m = $this->ownMessage($request, $conversation, $message);
        abort_unless($m->type === Message::TYPE_TEXT, 422, 'Редактировать можно только текст.');

        $data = $request->validate(['body' => ['required', 'string', 'max:'.config('chat.message_max_length')]]);

        return MessageResource::make($this->messages->edit($m, $data['body'])->load(['sender', 'replyTo.sender']));
    }

    /**
     * Удалить своё сообщение
     *
     * Удаление «мягкое»: у собеседников в ленте остаётся пометка «Сообщение удалено».
     */
    public function destroy(Request $request, string $conversation, int $message): MessageResource
    {
        $m = $this->ownMessage($request, $conversation, $message);

        return MessageResource::make($this->messages->delete($m)->load(['sender', 'replyTo.sender']));
    }

    /**
     * Показать удалённое (только операторы)
     *
     * Возвращает исходное содержимое удалённого сообщения. Остальным участникам — 403.
     */
    public function reveal(Request $request, string $conversation, int $message): JsonResponse
    {
        $c = $this->conversation($request, $conversation);
        abort_unless($this->viewer($request)->is_operator, 403, 'Только для операторов.');

        $m = Message::where('conversation_id', $c->id)->findOrFail($message);

        return response()->json(['data' => ['id' => $m->id, 'body' => $m->body, 'original_body' => $m->original_body, 'deleted_at' => $m->deleted_at?->toIso8601String()]]);
    }

    /**
     * Прочитано до сообщения
     *
     * Без `message_id` — прочитан весь диалог. Остальным участникам уйдёт событие `message.read`.
     */
    public function read(Request $request, string $conversation): JsonResponse
    {
        $c = $this->conversation($request, $conversation);
        $data = $request->validate(['message_id' => ['nullable', 'integer', 'min:1']]);

        $this->messages->markRead($c, $this->viewer($request), $data['message_id'] ?? null);

        return response()->json(['message' => 'ok']);
    }

    /**
     * «Печатает…»
     *
     * Лёгкий сигнал собеседникам (событие `typing` в presence-канале диалога). Шлите не чаще раза в 3 секунды.
     */
    public function typing(Request $request, string $conversation): JsonResponse
    {
        $c = $this->conversation($request, $conversation);
        $me = $this->viewer($request);

        broadcast(new ChatEvent('conversation.'.$c->id, 'typing', ['participant_id' => $me->id, 'name' => $me->name], true))->toOthers();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Файл или голосовое по подписанной ссылке
     *
     * Ссылки приходят в полях `voice.url` и `file.url` сообщения; действуют ограниченное время и не требуют токена,
     * поэтому подходят для `<img>`, `<audio>`, `<video>` и нативных плееров.
     */
    public function media(Request $request, int $message)
    {
        return $this->messages->stream(Message::findOrFail($message));
    }

    private function ownMessage(Request $request, string $conversation, int $message): Message
    {
        $c = $this->conversation($request, $conversation);
        $m = Message::where('conversation_id', $c->id)->findOrFail($message);
        abort_unless($m->participant_id === $this->viewer($request)->id, 403, 'Это не ваше сообщение.');
        abort_if($m->deleted_at, 422, 'Сообщение уже удалено.');

        return $m;
    }

    private function replyTarget(string $conversationId, ?int $id): ?int
    {
        return $id && Message::where('conversation_id', $conversationId)->whereKey($id)->exists() ? $id : null;
    }

    /** Список расширений через запятую: всё, кроме исполняемого и скриптов. */
    private function allowedExtensions(): string
    {
        return 'jpg,jpeg,png,gif,webp,heic,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv,rtf,zip,rar,7z,gz,tar,mp4,mov,webm,mkv,avi,mp3,m4a,ogg,wav,aac,json,xml,md';
    }
}
