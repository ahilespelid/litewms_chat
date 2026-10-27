<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_VOICE = 'voice';
    public const TYPE_FILE = 'file';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'conversation_id', 'participant_id', 'type', 'body', 'original_body', 'reply_to_id', 'client_uuid',
        'voice_path', 'voice_mime', 'voice_duration', 'file_path', 'file_name', 'file_mime', 'file_size',
        'edited_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function isVoice(): bool
    {
        return $this->type === self::TYPE_VOICE;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isImage(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->file_mime, 'image/');
    }

    public function isVideo(): bool
    {
        return $this->isFile() && str_starts_with((string) $this->file_mime, 'video/');
    }

    /** Короткая подпись для списка диалогов и push, когда сам контент показать нельзя. */
    public function preview(int $limit = 80): string
    {
        if ($this->deleted_at) {
            return 'Сообщение удалено';
        }

        return match ($this->type) {
            self::TYPE_VOICE => 'Голосовое сообщение',
            self::TYPE_FILE => ($this->isImage() ? 'Фото' : ($this->isVideo() ? 'Видео' : 'Файл')).': '.$this->file_name,
            default => mb_strimwidth((string) $this->body, 0, $limit, '…'),
        };
    }
}
