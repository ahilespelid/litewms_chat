<?php

namespace App\Services;

use App\Events\ChatEvent;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Jobs\FanOutMessage;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class MessageService
{
    /**
     * Сохраняет сообщение и отдаёт рассылку очереди. Повтор с тем же client_uuid вернёт то же сообщение:
     * мобильный клиент на плохой связи может безопасно повторять отправку.
     */
    public function send(Conversation $conversation, ?Participant $sender, array $attrs): Message
    {
        if (! empty($attrs['client_uuid'])) {
            $existing = Message::where('conversation_id', $conversation->id)->where('client_uuid', $attrs['client_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $message = DB::transaction(function () use ($conversation, $sender, $attrs) {
            $message = $conversation->messages()->create($attrs + [
                'participant_id' => $sender?->id,
                'type' => Message::TYPE_TEXT,
            ]);

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            // Новое сообщение возвращает диалог тем, кто его «удалил для себя».
            ConversationMember::where('conversation_id', $conversation->id)->whereNotNull('hidden_at')->update(['hidden_at' => null]);

            // Своё сообщение — прочитано отправителем (иначе оно «непрочитанное» на его втором устройстве).
            if ($sender) {
                ConversationMember::where('conversation_id', $conversation->id)
                    ->where('participant_id', $sender->id)
                    ->update(['last_read_message_id' => $message->id]);
            }

            return $message;
        });

        FanOutMessage::dispatch($message->id, 'message.created');

        return $message;
    }

    public function sendVoice(Conversation $conversation, Participant $sender, UploadedFile $file, int $duration, ?string $clientUuid = null, ?int $replyTo = null): Message
    {
        $path = $file->store('voice', 'local');

        return $this->send($conversation, $sender, [
            'type' => Message::TYPE_VOICE,
            'voice_path' => $path,
            'voice_mime' => $file->getMimeType() ?: 'audio/webm',
            'voice_duration' => $duration,
            'client_uuid' => $clientUuid,
            'reply_to_id' => $replyTo,
        ]);
    }

    public function sendFile(Conversation $conversation, Participant $sender, UploadedFile $file, ?string $clientUuid = null, ?int $replyTo = null): Message
    {
        $path = $file->store('files', 'local');

        return $this->send($conversation, $sender, [
            'type' => Message::TYPE_FILE,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'client_uuid' => $clientUuid,
            'reply_to_id' => $replyTo,
        ]);
    }

    /**
     * Пересылка в другой диалог от имени пересылающего. У копии остаётся автор оригинала («Переслано от …»),
     * при пересылке пересланного — автор самого первого сообщения. Вложение копируется в новый файл:
     * удаление оригинала копию не ломает.
     */
    public function forward(Message $source, Conversation $target, Participant $by): Message
    {
        abort_if($source->deleted_at || $source->type === Message::TYPE_SYSTEM, 422, 'Это сообщение переслать нельзя.');

        $attrs = [
            'type' => $source->type,
            'body' => $source->body,
            'forwarded_from_name' => $source->forwarded_from_name ?: ($source->sender?->name ?: 'Система'),
            'forwarded_from_message_id' => $source->forwarded_from_message_id ?: $source->id,
        ];

        $disk = Storage::disk('local');
        $copied = null;

        if ($source->isVoice() || $source->isFile()) {
            $from = $source->isVoice() ? $source->voice_path : $source->file_path;
            abort_unless($from && $disk->exists($from), 422, 'Файл сообщения недоступен.');

            $ext = pathinfo($from, PATHINFO_EXTENSION);
            $copied = dirname($from).'/'.Str::uuid().($ext ? '.'.$ext : '');
            $disk->copy($from, $copied);

            $attrs += $source->isVoice()
                ? ['voice_path' => $copied, 'voice_mime' => $source->voice_mime, 'voice_duration' => $source->voice_duration]
                : ['file_path' => $copied, 'file_name' => $source->file_name, 'file_mime' => $source->file_mime, 'file_size' => $source->file_size];
        }

        try {
            return $this->send($target, $by, $attrs);
        } catch (\Throwable $e) {
            $copied && $disk->delete($copied);
            throw $e;
        }
    }

    public function edit(Message $message, string $body): Message
    {
        if ($message->original_body === null) {
            $message->original_body = $message->body;
        }

        $message->forceFill(['body' => $body, 'edited_at' => now()])->save();
        FanOutMessage::dispatch($message->id, 'message.updated');

        return $message;
    }

    /** Мягкое удаление: сообщение остаётся в базе и «дырой» в ленте у собеседников. */
    public function delete(Message $message): Message
    {
        $message->forceFill(['deleted_at' => now()])->save();
        FanOutMessage::dispatch($message->id, 'message.updated');

        return $message;
    }

    /** Отмечает прочитанным до $upTo (по умолчанию — до последнего) и сообщает остальным. */
    public function markRead(Conversation $conversation, Participant $reader, ?int $upTo = null): void
    {
        $upTo ??= (int) $conversation->last_message_id;
        $upTo = min($upTo, (int) $conversation->last_message_id);

        $member = ConversationMember::where('conversation_id', $conversation->id)->where('participant_id', $reader->id)->first();

        if (! $member || $upTo <= (int) $member->last_read_message_id) {
            return;
        }

        $member->forceFill(['last_read_message_id' => $upTo])->save();

        $recipients = ConversationMember::where('conversation_id', $conversation->id)->whereNull('left_at')->pluck('participant_id');

        foreach ($recipients as $id) {
            if ($id === $reader->id) {
                // Остальные устройства читателя: сбросить счётчик.
                $fresh = app(ConversationService::class)->findFor($reader, $conversation->id);
                broadcast(new ChatEvent('participant.'.$id, 'conversation.updated', [
                    'conversation' => ConversationResource::make($fresh)->forViewer($reader)->resolve(Request::create('/')),
                ]));
                continue;
            }

            broadcast(new ChatEvent('participant.'.$id, 'message.read', [
                'conversation_id' => $conversation->id,
                'participant_id' => $reader->id,
                'last_read_message_id' => $upTo,
            ]));
        }
    }

    /** Системное сообщение от сайта-хозяина («Заявка принята»). */
    public function system(Conversation $conversation, string $body): Message
    {
        return $this->send($conversation, null, ['type' => Message::TYPE_SYSTEM, 'body' => $body]);
    }

    public function stream(Message $message)
    {
        $path = $message->isVoice() ? $message->voice_path : $message->file_path;
        abort_unless($path && ! $message->deleted_at && $message->conversation()->exists() && Storage::disk('local')->exists($path), 404);

        if ($message->isVoice()) {
            return Storage::disk('local')->response($path, null, [
                'Content-Type' => $message->voice_mime ?: 'audio/webm',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ]);
        }

        $inline = $message->isImage() || $message->isVideo();

        return Storage::disk('local')->response(
            $path,
            $message->file_name,
            [
                'Content-Type' => $message->file_mime ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                // Файл от собеседника открывается на домене чата: запрещаем ему исполнять скрипты.
                'Content-Security-Policy' => 'sandbox',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }
}
