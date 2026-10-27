<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class ConversationDeleteTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    private function support(array $h, array $ctx = ['type' => 'request', 'id' => '7', 'title' => 'Заявка №7']): string
    {
        return $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => $ctx], $h)->json('data.id');
    }

    public function test_для_себя_скрывает_только_у_меня_и_возвращается_с_новым_сообщением_без_старой_истории(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $op] = $this->actor($app, 'op', ['is_operator' => true]);
        [, $h] = $this->actor($app, 's1');
        $id = $this->support($h);
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'старое'], $h)->assertCreated();

        $this->deleteJson("/api/v1/conversations/$id", [], $h)->assertOk();

        $this->assertCount(0, $this->getJson('/api/v1/conversations', $h)->json('data'));
        $this->getJson("/api/v1/conversations/$id", $h)->assertNotFound();
        $this->assertCount(1, $this->getJson('/api/v1/conversations', $op)->json('data'), 'у оператора диалог остался');
        $this->assertSame(0, $this->getJson('/api/v1/unread', $h)->json('data.total'));

        // оператор отвечает — диалог возвращается, но без старой переписки
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'новое'], $op)->assertCreated();

        $list = $this->getJson('/api/v1/conversations', $h)->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame(1, $list->json('data.0.unread_count'));
        $this->assertSame(['новое'], collect($this->getJson("/api/v1/conversations/$id/messages", $h)->json('data'))->pluck('body')->all());
        $this->assertCount(2, $this->getJson("/api/v1/conversations/$id/messages", $op)->json('data'), 'оператор видит всю историю');
    }

    public function test_повторное_обращение_возвращает_скрытый_диалог(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $h] = $this->actor($app, 's1');
        $id = $this->support($h);
        $this->deleteJson("/api/v1/conversations/$id", [], $h)->assertOk();

        $again = $this->postJson('/api/v1/conversations', ['type' => 'support', 'context' => ['type' => 'request', 'id' => '7', 'title' => 'Заявка №7']], $h);

        $this->assertSame($id, $again->json('data.id'));
        $this->assertCount(1, $this->getJson('/api/v1/conversations', $h)->json('data'));
    }

    public function test_навсегда_в_поддержке_может_только_оператор_и_диалог_пропадает_у_всех(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $op] = $this->actor($app, 'op', ['is_operator' => true]);
        [, $h] = $this->actor($app, 's1');
        $id = $this->support($h);
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'секрет'], $h)->assertCreated();

        $this->assertFalse($this->getJson("/api/v1/conversations/$id", $h)->json('data.can_delete_for_all'));
        $this->assertTrue($this->getJson("/api/v1/conversations/$id", $op)->json('data.can_delete_for_all'));
        $this->deleteJson("/api/v1/conversations/$id?scope=all", [], $h)->assertForbidden();

        $this->deleteJson("/api/v1/conversations/$id?scope=all", [], $op)->assertOk();

        foreach ([$h, $op] as $who) {
            $this->getJson("/api/v1/conversations/$id", $who)->assertNotFound();
            $this->getJson("/api/v1/conversations/$id/messages", $who)->assertNotFound();
            $this->assertCount(0, $this->getJson('/api/v1/conversations', $who)->json('data'));
        }
    }

    public function test_навсегда_мягкое_сообщения_и_контекст_остаются_в_базе_а_ключ_освобождается(): void
    {
        ['app' => $app] = $this->makeApp();
        [$opP, $op] = $this->actor($app, 'op', ['is_operator' => true]);
        [, $h] = $this->actor($app, 's1');
        $id = $this->support($h);
        $this->postJson("/api/v1/conversations/$id/messages", ['body' => 'для архива'], $h)->assertCreated();

        $this->deleteJson("/api/v1/conversations/$id?scope=all", [], $op)->assertOk();

        $row = Conversation::withTrashed()->findOrFail($id);
        $this->assertNotNull($row->deleted_at);
        $this->assertSame($opP->id, $row->deleted_by);
        $this->assertSame('request', $row->context_type);
        $this->assertSame('Заявка №7', $row->context_title);
        $this->assertNull($row->unique_key);
        $this->assertSame(1, Message::where('conversation_id', $id)->where('body', 'для архива')->count());

        // «Написать складу» по той же заявке — новый пустой диалог
        $new = $this->support($h);
        $this->assertNotSame($id, $new);
        $this->assertCount(0, $this->getJson("/api/v1/conversations/$new/messages", $h)->json('data'));
    }

    public function test_навсегда_в_группе_только_создатель(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        $id = $this->postJson('/api/v1/conversations', ['type' => 'group', 'title' => 'Группа', 'participant_ids' => [$b->id]], $ha)->json('data.id');

        $this->deleteJson("/api/v1/conversations/$id?scope=all", [], $hb)->assertForbidden();
        $this->deleteJson("/api/v1/conversations/$id?scope=all", [], $ha)->assertOk();
        $this->getJson("/api/v1/conversations/$id", $hb)->assertNotFound();
    }

    public function test_чужой_диалог_и_неверный_scope(): void
    {
        ['app' => $app] = $this->makeApp();
        [, $h] = $this->actor($app, 's1');
        [, $other] = $this->actor($app, 's2');
        $id = $this->support($h);

        $this->deleteJson("/api/v1/conversations/$id", [], $other)->assertNotFound();
        $this->deleteJson("/api/v1/conversations/$id?scope=nope", [], $h)->assertUnprocessable();
        $this->assertCount(1, $this->getJson('/api/v1/conversations', $h)->json('data'));
    }
}
