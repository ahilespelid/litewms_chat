<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MiscController;
use App\Http\Controllers\Api\V1\Server\ServerController;
use App\Http\Middleware\AuthenticateApp;
use App\Http\Middleware\EnsureParticipantActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 (префикс /api/v1 добавляется автоматически)
|--------------------------------------------------------------------------
| 1. Публичные: вход (обмен handoff-токена / гость / личная ссылка) и подписанные ссылки на файлы.
| 2. Для участника (Bearer-токен чата): диалоги, сообщения, push. Этим же API пользуется веб-клиент
|    и будущее мобильное приложение.
| 3. Серверный (Bearer lcs_…): для бэкенда сайта-хозяина — участники и диалоги.
*/

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:chat-auth')->prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('exchange', [AuthController::class, 'exchange'])->name('exchange');
        Route::post('guest', [AuthController::class, 'guest'])->name('guest');
        Route::post('link', [AuthController::class, 'link'])->name('link');
    });

    Route::get('media/{message}', [MessageController::class, 'media'])->middleware('signed')->whereNumber('message')->name('chat.media');

    Route::middleware(['auth:sanctum', EnsureParticipantActive::class, 'throttle:chat-api'])->name('api.v1.')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('unread', [MiscController::class, 'unread'])->name('unread');
        Route::get('participants', [MiscController::class, 'participants'])->name('participants.index');

        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update');
        Route::post('conversations/{conversation}/members', [ConversationController::class, 'addMembers'])->name('conversations.members.store');
        Route::delete('conversations/{conversation}/members/{participant}', [ConversationController::class, 'removeMember'])->whereNumber('participant')->name('conversations.members.destroy');

        Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::middleware('throttle:chat-send')->group(function () {
            Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
            Route::post('conversations/{conversation}/messages/voice', [MessageController::class, 'storeVoice'])->name('messages.voice');
            Route::post('conversations/{conversation}/messages/file', [MessageController::class, 'storeFile'])->name('messages.file');
        });
        Route::patch('conversations/{conversation}/messages/{message}', [MessageController::class, 'update'])->whereNumber('message')->name('messages.update');
        Route::delete('conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy'])->whereNumber('message')->name('messages.destroy');
        Route::get('conversations/{conversation}/messages/{message}/reveal', [MessageController::class, 'reveal'])->whereNumber('message')->name('messages.reveal');
        Route::post('conversations/{conversation}/read', [MessageController::class, 'read'])->name('conversations.read');
        Route::post('conversations/{conversation}/typing', [MessageController::class, 'typing'])->name('conversations.typing');

        Route::post('push/subscribe', [MiscController::class, 'subscribe'])->name('push.subscribe');
        Route::post('push/unsubscribe', [MiscController::class, 'unsubscribe'])->name('push.unsubscribe');
        Route::post('devices', [MiscController::class, 'registerDevice'])->name('devices.store');
        Route::delete('devices', [MiscController::class, 'forgetDevice'])->name('devices.destroy');
    });

    Route::prefix('server')->middleware([AuthenticateApp::class, 'throttle:chat-server'])->name('api.v1.server.')->group(function () {
        Route::put('participants/{externalId}', [ServerController::class, 'upsertParticipant'])->name('participants.upsert');
        Route::post('participants/{externalId}/block', [ServerController::class, 'blockParticipant'])->name('participants.block');
        Route::post('participants/{externalId}/unblock', [ServerController::class, 'unblockParticipant'])->name('participants.unblock');
        Route::get('participants/{externalId}/unread', [ServerController::class, 'unread'])->name('participants.unread');

        Route::post('conversations', [ServerController::class, 'createConversation'])->name('conversations.store');
        Route::post('conversations/{conversation}/members', [ServerController::class, 'addMembers'])->name('conversations.members.store');
        Route::delete('conversations/{conversation}/members/{externalId}', [ServerController::class, 'removeMember'])->name('conversations.members.destroy');
        Route::post('conversations/{conversation}/archive', [ServerController::class, 'archive'])->name('conversations.archive');
        Route::post('conversations/{conversation}/messages', [ServerController::class, 'sendMessage'])->name('conversations.messages.store');
    });
});
