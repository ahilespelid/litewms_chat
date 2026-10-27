<?php

return [
    // Сколько секунд максимум может жить handoff-токен, выданный сайтом-хозяином.
    'handoff_max_age' => (int) env('CHAT_HANDOFF_MAX_AGE', 600),

    // Срок жизни рабочего Bearer-токена чата (дни). Веб-клиент перед истечением запрашивает новый handoff у хозяина.
    'token_ttl_days' => (int) env('CHAT_TOKEN_TTL_DAYS', 7),

    // Сколько одновременно живых токенов у одного участника (браузеры/устройства); старые отзываются.
    'max_tokens_per_participant' => (int) env('CHAT_MAX_TOKENS', 10),

    'message_max_length' => 4000,
    'file_max_kb' => (int) env('CHAT_FILE_MAX_KB', 25600),
    'voice_max_kb' => 10240,
    'voice_max_seconds' => 300,

    // Время жизни подписанной ссылки на голосовое/файл.
    'media_url_ttl_minutes' => 120,

    // Страница с документацией API доступна всем (в ней нет секретов).
    'docs_public' => true,
];
