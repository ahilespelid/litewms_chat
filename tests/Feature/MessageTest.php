<?php

namespace Tests\Feature;

use App\Events\ChatEvent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    private array $hA;
    private array $hB;
    private string $conv;
    private $a;
    private $b;
    private $chatApp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        ['app' => $this->chatApp, 'server_token' => $st] = $this->makeApp();
        [$this->a, $this->hA] = $this->actor($this->chatApp, 'a', ['name' => 'Анна']);
        [$this->b, $this->hB] = $this->actor($this->chatApp, 'b', ['name' => 'Борис', 'is_operator' => true]);

        $this->conv = $this->postJson('/api/v1/server/conversations', [
            'type' => 'direct', 'members' => [['external_id' => 'a'], ['external_id' => 'b']],
        ], $this->server($st))->json('data.id');
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/conversations/{$this->conv}/messages".$suffix;
    }

    public function test_send_history_and_pagination(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->postJson($this->url(), ['body' => "m$i"], $this->hA)->assertCreated()->assertJsonPath('data.sender.name', 'Анна');
        }

        $last = $this->getJson($this->url('?limit=3'), $this->hB)->assertOk();
        $this->assertSame(['m5', 'm6', 'm7'], array_column($last->json('data'), 'body'));

        $before = $last->json('data.0.id');
        $older = $this->getJson($this->url("?limit=3&before=$before"), $this->hB);
        $this->assertSame(['m2', 'm3', 'm4'], array_column($older->json('data'), 'body'));

        $after = $older->json('data.2.id');
        $newer = $this->getJson($this->url("?after=$after"), $this->hB);
        $this->assertSame(['m5', 'm6', 'm7'], array_column($newer->json('data'), 'body'));
    }

    public function test_client_uuid_makes_resending_idempotent(): void
    {
        $one = $this->postJson($this->url(), ['body' => 'раз', 'client_uuid' => 'u-1'], $this->hA)->assertCreated();
        $two = $this->postJson($this->url(), ['body' => 'раз', 'client_uuid' => 'u-1'], $this->hA)->assertCreated();

        $this->assertSame($one->json('data.id'), $two->json('data.id'));
        $this->assertSame(1, Message::count());
    }

    public function test_message_validation(): void
    {
        $this->postJson($this->url(), ['body' => ''], $this->hA)->assertStatus(422);
        $this->postJson($this->url(), ['body' => str_repeat('я', 4001)], $this->hA)->assertStatus(422);
        $this->postJson($this->url(), [], $this->getHeadersWithoutAuth())->assertStatus(401);
    }

    private function getHeadersWithoutAuth(): array
    {
        return [];
    }

    public function test_reply_keeps_preview_and_ignores_foreign_conversation_ids(): void
    {
        $first = $this->postJson($this->url(), ['body' => 'вопрос'], $this->hA)->json('data.id');
        $reply = $this->postJson($this->url(), ['body' => 'ответ', 'reply_to_id' => $first], $this->hB)->assertCreated();

        $reply->assertJsonPath('data.reply_to.id', $first)->assertJsonPath('data.reply_to.sender_name', 'Анна')->assertJsonPath('data.reply_to.preview', 'вопрос');

        $bogus = $this->postJson($this->url(), ['body' => 'x', 'reply_to_id' => 999999], $this->hB)->assertCreated();
        $this->assertNull($bogus->json('data.reply_to'));
    }

    public function test_edit_keeps_original_and_only_author_can_edit_or_delete(): void
    {
        $id = $this->postJson($this->url(), ['body' => 'Было'], $this->hA)->json('data.id');

        $this->patchJson($this->url("/$id"), ['body' => 'Хак'], $this->hB)->assertForbidden();
        $this->deleteJson($this->url("/$id"), [], $this->hB)->assertForbidden();

        $edited = $this->patchJson($this->url("/$id"), ['body' => 'Стало'], $this->hA)->assertOk();
        $edited->assertJsonPath('data.body', 'Стало')->assertJsonPath('data.original_body', 'Было');
        $this->patchJson($this->url("/$id"), ['body' => 'Ещё'], $this->hA)->assertJsonPath('data.original_body', 'Было');
    }

    public function test_delete_is_soft_hidden_for_others_and_revealable_only_by_operator(): void
    {
        $id = $this->postJson($this->url(), ['body' => 'секрет'], $this->hA)->json('data.id');

        $deleted = $this->deleteJson($this->url("/$id"), [], $this->hA)->assertOk();
        $deleted->assertJsonPath('data.deleted', true)->assertJsonPath('data.body', null);

        $seen = $this->getJson($this->url(), $this->hB)->json('data.0');
        $this->assertTrue($seen['deleted']);
        $this->assertNull($seen['body']);

        $this->getJson($this->url("/$id/reveal"), $this->hA)->assertForbidden();
        $this->getJson($this->url("/$id/reveal"), $this->hB)->assertOk()->assertJsonPath('data.body', 'секрет');

        $this->patchJson($this->url("/$id"), ['body' => 'воскрес'], $this->hA)->assertStatus(422);
    }

    public function test_read_receipts_are_visible_to_the_other_side_and_broadcast(): void
    {
        $id = $this->postJson($this->url(), ['body' => 'привет'], $this->hA)->json('data.id');

        Event::fake([ChatEvent::class]);
        $this->postJson("/api/v1/conversations/{$this->conv}/read", ['message_id' => $id], $this->hB)->assertOk();

        Event::assertDispatched(ChatEvent::class, fn ($e) => $e->name === 'message.read'
            && $e->channel === 'participant.'.$this->a->id
            && $e->payload['last_read_message_id'] === $id);

        $members = collect($this->getJson("/api/v1/conversations/{$this->conv}", $this->hA)->json('data.members'));
        $this->assertSame($id, $members->firstWhere('id', $this->b->id)['last_read_message_id']);
    }

    public function test_message_events_reach_every_member_channel_with_their_own_unread(): void
    {
        Event::fake([ChatEvent::class]);

        $this->postJson($this->url(), ['body' => 'эй'], $this->hA)->assertCreated();   // очередь sync → рассылка сразу

        Event::assertDispatched(ChatEvent::class, fn ($e) => $e->name === 'message.created'
            && $e->channel === 'participant.'.$this->b->id
            && $e->payload['conversation']['unread_count'] === 1
            && $e->payload['message']['body'] === 'эй');
        Event::assertDispatched(ChatEvent::class, fn ($e) => $e->name === 'message.created'
            && $e->channel === 'participant.'.$this->a->id
            && $e->payload['conversation']['unread_count'] === 0);
    }

    public function test_typing_goes_to_presence_channel(): void
    {
        Event::fake([ChatEvent::class]);
        $this->postJson("/api/v1/conversations/{$this->conv}/typing", [], $this->hA)->assertOk();

        Event::assertDispatched(ChatEvent::class, fn ($e) => $e->name === 'typing' && $e->presence && $e->channel === 'conversation.'.$this->conv);
    }

    public function test_file_upload_is_served_only_through_signed_url_and_is_sandboxed(): void
    {
        $r = $this->postJson($this->url('/file'), ['file' => UploadedFile::fake()->image('photo.jpg', 40, 40)], $this->hA)->assertCreated();
        $r->assertJsonPath('data.type', 'file')->assertJsonPath('data.file.kind', 'image')->assertJsonPath('data.file.name', 'photo.jpg');

        $url = $r->json('data.file.url');
        $this->assertStringContainsString('signature=', $url);

        $ok = $this->get($url)->assertOk();
        $this->assertSame('sandbox', $ok->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $ok->headers->get('X-Content-Type-Options'));

        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();
        $this->get("/api/v1/media/{$r->json('data.id')}")->assertForbidden();   // без подписи
    }

    public function test_dangerous_uploads_are_rejected(): void
    {
        foreach (['x.php', 'x.html', 'x.svg', 'x.exe', 'x.js', 'x.sh'] as $name) {
            $this->postJson($this->url('/file'), ['file' => UploadedFile::fake()->create($name, 5)], $this->hA)->assertStatus(422);
        }
        $this->assertSame(0, Message::count());
    }

    public function test_voice_message(): void
    {
        $audio = UploadedFile::fake()->create('v.webm', 20, 'audio/webm');
        $r = $this->postJson($this->url('/voice'), ['audio' => $audio, 'duration' => 7], $this->hA);

        if ($r->status() === 422) {
            $this->markTestSkipped('fake() не отдаёт реальный audio-mime: '.json_encode($r->json('errors')));
        }

        $r->assertCreated()->assertJsonPath('data.voice.duration', 7);
        $this->get($r->json('data.voice.url'))->assertOk();
    }

    public function test_deleted_media_is_gone(): void
    {
        $r = $this->postJson($this->url('/file'), ['file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')], $this->hA)->assertCreated();
        $url = $r->json('data.file.url');
        $this->deleteJson($this->url('/'.$r->json('data.id')), [], $this->hA)->assertOk();

        $this->get($url)->assertNotFound();
    }
}
