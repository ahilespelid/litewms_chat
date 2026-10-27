<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class ServerApiTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_server_api_requires_server_token(): void
    {
        ['app' => $app, 'secret' => $secret] = $this->makeApp();

        $this->putJson('/api/v1/server/participants/1', ['name' => 'X'])->assertUnauthorized();
        $this->putJson('/api/v1/server/participants/1', ['name' => 'X'], $this->server('lcs_wrong'))->assertUnauthorized();
        // публичный ключ и секрет подписи — не серверный токен
        $this->putJson('/api/v1/server/participants/1', ['name' => 'X'], $this->server($app->key))->assertUnauthorized();
        $this->putJson('/api/v1/server/participants/1', ['name' => 'X'], $this->server($secret))->assertUnauthorized();
    }

    public function test_participant_upsert_block_unblock(): void
    {
        ['app' => $app, 'server_token' => $t] = $this->makeApp();

        $this->putJson('/api/v1/server/participants/55', ['name' => 'Пётр', 'role' => 'Склад', 'is_operator' => true], $this->server($t))
            ->assertSuccessful()->assertJsonPath('data.name', 'Пётр')->assertJsonPath('data.is_operator', true);

        [$p, $h] = $this->actor($app, '55');
        $this->getJson('/api/v1/me', $h)->assertOk();

        $this->postJson('/api/v1/server/participants/55/block', [], $this->server($t))->assertOk();
        $this->getJson('/api/v1/me', $h)->assertUnauthorized(); // токены отозваны

        $this->postJson('/api/v1/server/participants/55/unblock', [], $this->server($t))->assertOk();
        $this->assertNull($p->fresh()->blocked_at);
        $this->postJson('/api/v1/server/participants/404/block', [], $this->server($t))->assertNotFound();
    }

    public function test_conversation_is_created_idempotently_with_context_and_first_message(): void
    {
        ['app' => $app, 'server_token' => $t] = $this->makeApp();

        $body = [
            'type' => 'group',
            'title' => 'Заявка №123',
            'unique_key' => 'request:123:seller:5',
            'members' => [
                ['external_id' => 'seller5', 'name' => 'Продавец'],
                ['external_id' => 'wh1', 'name' => 'Склад', 'role' => 'Склад'],
            ],
            'context' => ['type' => 'request', 'id' => '123', 'title' => 'Заявка №123', 'url' => 'https://test.litewms.ru/lk2/requests/123'],
            'message' => ['body' => 'Заявка принята'],
        ];

        $first = $this->postJson('/api/v1/server/conversations', $body, $this->server($t))->assertCreated();
        $again = $this->postJson('/api/v1/server/conversations', $body, $this->server($t))->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, Message::count(), 'первое сообщение не дублируется при повторе');
        $this->assertSame('system', Message::first()->type);
        $this->assertSame(2, Participant::where('app_id', $app->id)->count());

        // участник сразу видит диалог со своим токеном и непрочитанным
        [, $h] = $this->actor($app, 'seller5');
        $list = $this->getJson('/api/v1/conversations?context_type=request&context_id=123', $h)->assertOk();
        $this->assertSame($first->json('data.id'), $list->json('data.0.id'));
        $this->assertSame(1, $list->json('data.0.unread_count'));
    }

    public function test_direct_conversation_needs_exactly_two_members(): void
    {
        ['server_token' => $t] = $this->makeApp();

        $this->postJson('/api/v1/server/conversations', ['type' => 'direct', 'members' => [['external_id' => 'a']]], $this->server($t))
            ->assertStatus(422);
    }

    public function test_members_system_message_archive_and_unread(): void
    {
        ['app' => $app, 'server_token' => $t] = $this->makeApp();

        $id = $this->postJson('/api/v1/server/conversations', [
            'type' => 'group', 'title' => 'Группа', 'members' => [['external_id' => 'a', 'name' => 'A'], ['external_id' => 'b', 'name' => 'B']],
        ], $this->server($t))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/server/conversations/$id/members", ['members' => [['external_id' => 'c', 'name' => 'C']]], $this->server($t))
            ->assertOk()->assertJsonPath('added', 1);
        [, $hc] = $this->actor($app, 'c');
        $this->getJson("/api/v1/conversations/$id", $hc)->assertOk();

        $this->postJson("/api/v1/server/conversations/$id/messages", ['body' => 'Статус изменён'], $this->server($t))
            ->assertCreated()->assertJsonPath('data.type', 'system');
        $this->postJson("/api/v1/server/conversations/$id/messages", ['body' => 'Привет', 'from' => 'a'], $this->server($t))
            ->assertCreated()->assertJsonPath('data.type', 'text');
        // автор не из диалога
        $this->postJson("/api/v1/server/conversations/$id/messages", ['body' => 'x', 'from' => 'zzz'], $this->server($t))->assertNotFound();

        $this->getJson('/api/v1/server/participants/b/unread', $this->server($t))
            ->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.conversations', 1);
        $this->getJson('/api/v1/server/participants/nobody/unread', $this->server($t))->assertJsonPath('data.total', 0);

        $this->deleteJson("/api/v1/server/conversations/$id/members/c", [], $this->server($t))->assertOk();
        $this->getJson("/api/v1/conversations/$id", $hc)->assertStatus(404);

        $this->postJson("/api/v1/server/conversations/$id/archive", [], $this->server($t))->assertOk();
        [, $ha] = $this->actor($app, 'a');
        $this->assertCount(0, $this->getJson('/api/v1/conversations', $ha)->json('data'));
    }

    public function test_apps_are_fully_isolated(): void
    {
        ['app' => $a, 'server_token' => $ta] = $this->makeApp([], ['https://a.example'], 'A');
        ['app' => $b, 'server_token' => $tb] = $this->makeApp([], ['https://b.example'], 'B');

        $id = $this->postJson('/api/v1/server/conversations', ['type' => 'group', 'members' => [['external_id' => '1', 'name' => 'x'], ['external_id' => '2']]], $this->server($ta))
            ->assertCreated()->json('data.id');

        // другой app не может ни писать в чужой диалог, ни архивировать, ни менять состав
        $this->postJson("/api/v1/server/conversations/$id/messages", ['body' => 'hack'], $this->server($tb))->assertNotFound();
        $this->postJson("/api/v1/server/conversations/$id/archive", [], $this->server($tb))->assertNotFound();
        $this->postJson("/api/v1/server/conversations/$id/members", ['members' => [['external_id' => '9']]], $this->server($tb))->assertNotFound();

        // тот же external_id в другом app — другой человек
        [$p1] = $this->actor($a, '1');
        [$q1, $hq] = $this->actor($b, '1');
        $this->assertNotSame($p1->id, $q1->id);
        $this->getJson("/api/v1/conversations/$id", $hq)->assertNotFound();
        $this->getJson('/api/v1/server/participants/1/unread', $this->server($tb))->assertJsonPath('data.total', 0);
    }
}
