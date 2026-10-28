<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @property-read Message $resource
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $m = $this->resource;
        $deleted = $m->deleted_at !== null;

        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'type' => $m->type,
            /** Текст сообщения; у удалённого — null (место в ленте остаётся). */
            'body' => $deleted ? null : $m->body,
            'deleted' => $deleted,
            'edited_at' => $m->edited_at?->toIso8601String(),
            /** Текст до первой правки: обе стороны видят, что поменялось. */
            'original_body' => ! $deleted && $m->edited_at ? $m->original_body : null,
            'sender' => $m->participant_id ? ParticipantResource::make($m->sender)->resolve($request) : null,
            /** Автор оригинала, если сообщение переслано; иначе null. */
            'forwarded_from' => $m->forwarded_from_name,
            'reply_to' => $this->replyPreview($m),
            'voice' => $m->isVoice() && ! $deleted ? [
                'url' => $this->mediaUrl($m),
                'duration' => $m->voice_duration,
                'mime' => $m->voice_mime,
            ] : null,
            'file' => $m->isFile() && ! $deleted ? [
                'url' => $this->mediaUrl($m),
                'name' => $m->file_name,
                'mime' => $m->file_mime,
                'size' => $m->file_size,
                /** image | video | file — как показывать во вложении */
                'kind' => $m->isImage() ? 'image' : ($m->isVideo() ? 'video' : 'file'),
            ] : null,
            'client_uuid' => $m->client_uuid,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }

    /** Подписанная ссылка: <img>/<audio>/<video> не умеют слать заголовок Authorization, а у мобильного клиента то же самое удобно. */
    private function mediaUrl(Message $m): string
    {
        return URL::temporarySignedRoute(
            'chat.media',
            now()->addMinutes((int) config('chat.media_url_ttl_minutes')),
            ['message' => $m->id]
        );
    }

    private function replyPreview(Message $m): ?array
    {
        if (! $m->reply_to_id) {
            return null;
        }

        $reply = $m->relationLoaded('replyTo') ? $m->replyTo : $m->replyTo()->with('sender')->first();

        if (! $reply) {
            return null;
        }

        return [
            'id' => $reply->id,
            'sender_name' => $reply->sender?->name,
            'preview' => $reply->preview(120),
        ];
    }
}
