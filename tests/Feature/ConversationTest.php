<?php

namespace Tests\Feature;

use App\Events\ChatEvent;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_support_conversation_is_opened_once_per_context_and_operators_are_joined(): void
    {
        ['app' => $app] = $this->makeApp();
        [$op] = $this->actor($app, 'op1', ['is_operator' => true, 'name' => 'Склад']);
        [$seller, $h] = $this->actor($app, 's1', ['name' => 'Продавец']);

        $ctx = ['type' => 'request', 'id' => '123', 'title' => 'Заявка №123'];

        $first = $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => $ctx], $h)->assertCreated();
        $again = $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => $ctx], $h)->assertOk();
        $other = $this->postJson('/api/v1/conversations', ['type' => 'support'], $h)->assertCreated();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertNotSame($first->json('data.id'), $other->json('data.id'));
        $first->assertJsonPath('data.context.id', '123')->assertJsonPath('data.title', 'Поддержка · Заявка №123');

        // оператор видит диалог и называется он по имени обратившегося
        [, $hop] = [null, ['Authorization' => 'Bearer '.$op->createToken('t')->plainTextToken]];
        $list = $this->getJson('/api/v1/conversations?context_type=request&context_id=123', $hop)->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Продавец · Заявка №123', $list->json('data.0.title'));
    }

    public function test_new_operator_joins_existing_support_conversations_with_history_read(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $h] = $this->actor($app, 's1');
        $id = $this->postJson('/api/v1/conversations', ['type' => 'support'], $h)->json('data.id');
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'Привет'], $h)->assertCreated();

        [$late] = $this->actor($app, 'late', ['is_operator' => true]);
        $hl = ['Authorization' => 'Bearer '.$late->createToken('t')->plainTextToken];

        $r = $this->getJson('/api/v1/conversations', $hl)->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertSame(0, $r->json('data.0.unread_count'));
    }

    public function test_user_cannot_create_direct_or_group_unless_enabled(): void
    {
        ['app' => $app] = $this->makeApp();
        [$a, $ha] = $this->actor($app, 'a');
        [$b] = $this->actor($app, 'b');

        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'participant_id' => $b->id], $ha)->assertStatus(403);

        $app->update(['settings' => ['user_conversations' => true]]);
        $d = $this->postJson('/api/v1/conversations', ['type' => 'direct', 'participant_id' => $b->id], $ha)->assertCreated();
        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'participant_id' => $b->id], $ha)->assertOk()
            ->assertJsonPath('data.id', $d->json('data.id'));
        $d->assertJsonPath('data.title', $b->name);
    }

    public function test_conversations_do_not_leak_between_apps_or_strangers(): void
    {
        ['app' => $a, 'server_token' => $tokenA] = $this->makeApp();
        ['app' => $b] = $this->makeApp([], [], 'Другой сайт');

        $this->postJson('/api/v1/server/conversations', [
            'type' => 'group', 'title' => 'Секретный', 'members' => [['external_id' => '1'], ['external_id' => '2']],
        ], $this->server($tokenA))->assertCreated();
        $id = Conversation::first()->id;

        [, $stranger] = $this->actor($a, '3');          // тот же app, не участник
        [, $foreign] = $this->actor($b, '1');           // другой app, тот же external_id

        foreach ([$stranger, $foreign] as $h) {
            $this->getJson("/api/v1/conversations/$id", $h)->assertNotFound();
            $this->getJson("/api/v1/conversations/$id/messages", $h)->assertNotFound();
            $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'x'], $h)->assertNotFound();
            $this->assertCount(0, $this->getJson('/api/v1/conversations', $h)->json('data'));
        }
    }

    public function test_context_url_from_client_is_dropped_unless_origin_is_allowed(): void
    {
        ['app' => $app] = $this->makeApp([], ['https://test.litewms.ru']);
        [, $h] = $this->actor($app, 's1');

        $good = $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => ['type' => 'request', 'id' => '1', 'url' => 'https://test.litewms.ru/lk2/requests/1']], $h);
        $bad = $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => ['type' => 'request', 'id' => '2', 'url' => 'https://evil.example/phish']], $h);

        $this->assertSame('https://test.litewms.ru/lk2/requests/1', $good->json('data.context.url'));
        $this->assertNull($bad->json('data.context.url'));
    }

    public function test_group_owner_manages_members_and_member_can_leave(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$owner, $ho] = $this->actor($app, 'o');
        [$m1, $h1] = $this->actor($app, 'm1');
        [$m2] = $this->actor($app, 'm2');

        $id = $this->postJson('/api/v1/conversations', ['type' => 'group', 'title' => 'Команда', 'participant_ids' => [$m1->id]], $ho)->assertCreated()->json('data.id');

        $this->postJson("/api/v1/conversations/$id/members", ['participant_ids' => [$m2->id]], $h1)->assertForbidden();
        $this->postJson("/api/v1/conversations/$id/members", ['participant_ids' => [$m2->id]], $ho)->assertOk();
        $this->assertSame(3, $this->getJson("/api/v1/conversations/$id", $ho)->json('data.member_count'));

        $this->deleteJson("/api/v1/conversations/$id/members/{$m2->id}", [], $h1)->assertForbidden();
        $this->deleteJson("/api/v1/conversations/$id/members/{$m1->id}", [], $h1)->assertOk();
        $this->getJson("/api/v1/conversations/$id", $h1)->assertNotFound();

        $this->patchJson("/api/v1/conversations/$id", ['title' => 'Новое'], $ho)->assertOk()->assertJsonPath('data.title', 'Новое');
    }

    public function test_unread_counters_and_total(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $hs] = $this->actor($app, 's');
        [$op, ] = $this->actor($app, 'op', ['is_operator' => true]);
        $ho = ['Authorization' => 'Bearer '.$op->createToken('t')->plainTextToken];

        $id = $this->postJson('/api/v1/conversations', ['type' => 'support'], $hs)->json('data.id');
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'раз'], $hs);
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'два'], $hs);

        $this->assertSame(2, $this->getJson('/api/v1/unread', $ho)->json('data.total'));
        $this->assertSame(0, $this->getJson('/api/v1/unread', $hs)->json('data.total'));   // свои — прочитаны
        $this->assertSame(2, $this->getJson('/api/v1/conversations?unread=1', $ho)->json('data.0.unread_count'));

        $this->postJson("/api/v1/conversations/$id/read", [], $ho)->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/unread', $ho)->json('data.total'));
        $this->assertCount(0, $this->getJson('/api/v1/conversations?unread=1', $ho)->json('data'));
    }

    public function test_new_conversation_is_announced_on_participant_channels(): void
    {
        Event::fake([ChatEvent::class]);
        ['app' => $app, 'server_token' => $st] = $this->makeApp();

        $this->postJson('/api/v1/server/conversations', [
            'type' => 'direct', 'members' => [['external_id' => 'a', 'name' => 'A'], ['external_id' => 'b', 'name' => 'B']],
        ], $this->server($st))->assertCreated();

        Event::assertDispatched(ChatEvent::class, fn ($e) => $e->name === 'conversation.updated' && str_starts_with($e->channel, 'participant.'));
        Event::assertDispatchedTimes(ChatEvent::class, 2);
    }
}
