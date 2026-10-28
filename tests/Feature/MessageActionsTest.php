<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageShare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

/**
 * Закрепление диалогов, пересылка и «Поделиться» сообщением.
 */
class MessageActionsTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    private function group(array $owner, array $others, string $title = 'Группа'): string
    {
        return $this->postJson('/api/v1/conversations', ['type' => 'group', 'title' => $title, 'participant_ids' => $others], $owner)->assertCreated()->json('data.id');
    }

    private function say(string $conversation, array $h, string $body): int
    {
        return $this->postJson("/api/v1/conversations/$conversation/messages", ['body' => $body], $h)->assertCreated()->json('data.id');
    }

    // ——— закрепление

    public function test_закреплённые_диалоги_идут_первыми_у_каждого_свои_и_есть_лимит(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        $c1 = $this->group($ha, [$b->id], 'Первый');
        $c2 = $this->group($ha, [$b->id], 'Второй');
        $c3 = $this->group($ha, [$b->id], 'Третий');
        $this->say($c1, $ha, 'старый');
        $this->say($c3, $ha, 'самый свежий');

        $ids = fn ($h) => collect($this->getJson('/api/v1/conversations', $h)->json('data'))->pluck('id')->all();
        $this->assertSame($c3, $ids($ha)[0]);

        $this->patchJson("/api/v1/conversations/$c1", ['pinned' => true], $ha)->assertOk()->assertJsonPath('data.pinned', true);
        $this->assertSame($c1, $ids($ha)[0], 'закреплённый наверху, хотя написали в другой');
        $this->assertSame($c3, $ids($hb)[0], 'у собеседника порядок прежний');

        config(['chat.pinned_max' => 1]);
        $this->patchJson("/api/v1/conversations/$c2", ['pinned' => true], $ha)->assertUnprocessable();
        $this->patchJson("/api/v1/conversations/$c1", ['pinned' => true], $ha)->assertOk();

        $this->patchJson("/api/v1/conversations/$c1", ['pinned' => false], $ha)->assertOk()->assertJsonPath('data.pinned', false);
        $this->assertSame($c3, $ids($ha)[0]);
    }

    public function test_удаление_для_себя_снимает_закрепление(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [, $ha] = $this->actor($app, 'a');
        [$b] = $this->actor($app, 'b');
        $c = $this->group($ha, [$b->id]);
        $this->patchJson("/api/v1/conversations/$c", ['pinned' => true], $ha)->assertOk();

        $this->deleteJson("/api/v1/conversations/$c", [], $ha)->assertOk();
        $this->say($c, $this->actor($app, 'b')[1], 'вернись');

        $this->assertFalse($this->getJson('/api/v1/conversations', $ha)->json('data.0.pinned'));
    }

    // ——— пересылка

    public function test_пересылка_текста_сохраняет_автора_оригинала_и_идёт_от_моего_имени(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a', ['name' => 'Анна']);
        [$b, $hb] = $this->actor($app, 'b', ['name' => 'Борис']);
        [$c, $hc] = $this->actor($app, 'c', ['name' => 'Вера']);
        $src = $this->group($ha, [$b->id], 'Исходный');
        $dst = $this->group($hb, [$c->id], 'Куда');
        $id = $this->say($src, $ha, 'привет из группы');

        $r = $this->postJson("/api/v1/conversations/$src/messages/$id/forward", ['conversation_ids' => [$dst]], $hb)->assertCreated();

        $r->assertJsonPath('data.0.body', 'привет из группы')
            ->assertJsonPath('data.0.forwarded_from', 'Анна')
            ->assertJsonPath('data.0.sender.name', 'Борис')
            ->assertJsonPath('data.0.conversation_id', $dst);

        // пересылка пересланного сохраняет первого автора
        $fwd = $r->json('data.0.id');
        $dst2 = $this->group($hc, [$b->id], 'Ещё');
        $this->postJson("/api/v1/conversations/$dst/messages/$fwd/forward", ['conversation_ids' => [$dst2]], $hb)
            ->assertCreated()->assertJsonPath('data.0.forwarded_from', 'Анна');

        // пересланное не редактируется
        $this->patchJson("/api/v1/conversations/$dst/messages/$fwd", ['body' => 'правка'], $hb)->assertUnprocessable();
    }

    public function test_пересылка_файла_копирует_вложение_и_переживает_удаление_оригинала(): void
    {
        Storage::fake('local');
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        $src = $this->group($ha, [$b->id]);
        $dst = $this->group($ha, [$b->id], 'Куда');

        $sent = $this->post("/api/v1/conversations/$src/messages/file", ['file' => UploadedFile::fake()->create('акт.pdf', 12, 'application/pdf')], $ha + ['Accept' => 'application/json'])->assertCreated();
        $id = $sent->json('data.id');

        $copy = $this->postJson("/api/v1/conversations/$src/messages/$id/forward", ['conversation_ids' => [$dst]], $ha)->assertCreated();
        $this->assertSame('акт.pdf', $copy->json('data.0.file.name'));

        $orig = Message::findOrFail($id);
        $new = Message::findOrFail($copy->json('data.0.id'));
        $this->assertNotSame($orig->file_path, $new->file_path);
        Storage::disk('local')->assertExists($new->file_path);

        $this->deleteJson("/api/v1/conversations/$src/messages/$id", [], $ha)->assertOk();
        Storage::disk('local')->assertExists($new->file_path);
    }

    public function test_пересылать_можно_только_в_свои_диалоги_и_не_удалённое(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        [$c, $hc] = $this->actor($app, 'c');
        $src = $this->group($ha, [$b->id]);
        $foreign = $this->group($hc, [$b->id], 'Чужой для A');
        $mine = $this->group($ha, [$b->id], 'Свой');
        $id = $this->say($src, $ha, 'текст');

        $this->postJson("/api/v1/conversations/$src/messages/$id/forward", ['conversation_ids' => [$foreign]], $ha)->assertNotFound();
        $this->assertSame(0, Message::where('conversation_id', $foreign)->count());

        $this->deleteJson("/api/v1/conversations/$src/messages/$id", [], $ha)->assertOk();
        $this->postJson("/api/v1/conversations/$src/messages/$id/forward", ['conversation_ids' => [$mine]], $ha)->assertUnprocessable();
        $this->postJson("/api/v1/conversations/$src/messages/$id/forward", ['conversation_ids' => []], $ha)->assertUnprocessable();
    }

    // ——— «Поделиться»

    private function share(string $conversation, int $message, array $h, array $body = ['access' => 'anyone'])
    {
        return $this->putJson("/api/v1/conversations/$conversation/messages/$message/share", $body, $h);
    }

    private function open(string $url, array $h = [], array $body = [])
    {
        return $this->postJson('/api/v1/shared/'.basename($url), $body, $h);
    }

    public function test_делиться_можно_только_своими_сообщениями(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        $c = $this->group($ha, [$b->id]);
        $id = $this->say($c, $ha, 'моё');

        $this->share($c, $id, $hb)->assertForbidden();
        $this->getJson("/api/v1/conversations/$c/messages/$id/share", $ha)->assertOk()->assertJsonPath('data', null);
        $this->share($c, $id, $ha)->assertCreated()->assertJsonPath('data.access', 'anyone')->assertJsonPath('data.has_password', false);

        $this->deleteJson("/api/v1/conversations/$c/messages/$id", [], $ha)->assertOk();
        $this->share($c, $id, $ha)->assertUnprocessable();
    }

    public function test_ссылка_для_всех_открывается_без_входа_и_адрес_не_меняется_при_правках(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a', ['name' => 'Анна']);
        [$b] = $this->actor($app, 'b');
        $c = $this->group($ha, [$b->id]);
        $id = $this->say($c, $ha, 'публичное');

        $url = $this->share($c, $id, $ha)->json('data.url');
        $again = $this->share($c, $id, $ha, ['access' => 'anyone', 'expires_in' => 3600])->assertOk();
        $this->assertSame($url, $again->json('data.url'));

        $r = $this->open($url)->assertOk();
        $r->assertJsonPath('data.body', 'публичное')->assertJsonPath('data.sender.name', 'Анна')->assertJsonPath('data.conversation_id', null);
        $this->assertSame(1, MessageShare::firstOrFail()->views);

        $this->get('/m/'.basename($url))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_доступ_участникам_и_вошедшим_проверяется(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        ['app' => $other] = $this->makeApp([], ['https://x.example.com'], 'Другое');
        [$a, $ha] = $this->actor($app, 'a');
        [$b, $hb] = $this->actor($app, 'b');
        [$c, $hc] = $this->actor($app, 'c');
        [, $hx] = $this->actor($other, 'x');
        $conv = $this->group($ha, [$b->id]);
        $id = $this->say($conv, $ha, 'для своих');

        $url = $this->share($conv, $id, $ha, ['access' => 'members'])->json('data.url');
        $this->open($url)->assertUnauthorized()->assertJsonPath('code', 'auth_required');
        $this->open($url, $hc)->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->open($url, $hx)->assertForbidden();
        $this->open($url, $hb)->assertOk()->assertJsonPath('data.conversation_id', $conv);
        $this->open($url, $ha)->assertOk();

        $this->share($conv, $id, $ha, ['access' => 'app']);
        $this->open($url)->assertUnauthorized();
        $this->open($url, $hc)->assertOk()->assertJsonPath('data.conversation_id', null);
        $this->open($url, $hx)->assertForbidden();
    }

    public function test_пароль_срок_и_отзыв_ссылки(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b] = $this->actor($app, 'b');
        $conv = $this->group($ha, [$b->id]);
        $id = $this->say($conv, $ha, 'секрет');

        $url = $this->share($conv, $id, $ha, ['access' => 'anyone', 'password' => 'hunter22', 'expires_in' => 3600])->assertCreated()->json('data.url');
        $this->assertNotSame('hunter22', MessageShare::firstOrFail()->password_hash);

        $this->open($url)->assertForbidden()->assertJsonPath('code', 'password_required');
        $this->open($url, [], ['password' => 'nope'])->assertForbidden()->assertJsonPath('code', 'wrong_password');
        $this->open($url, [], ['password' => 'hunter22'])->assertOk()->assertJsonPath('data.body', 'секрет');
        $this->open($url, $ha)->assertOk();   // автору пароль не нужен

        $this->share($conv, $id, $ha, ['access' => 'anyone', 'clear_password' => true])->assertOk()->assertJsonPath('data.has_password', false);
        $this->open($url)->assertOk();

        $this->travel(2)->hours();
        $this->open($url)->assertStatus(410)->assertJsonPath('code', 'expired');
        $this->open($url, $ha)->assertOk();
        $this->travelBack();

        $this->share($conv, $id, $ha, ['access' => 'anyone', 'expires_in' => null]);
        $this->open($url)->assertOk();

        $this->deleteJson("/api/v1/conversations/$conv/messages/$id/share", [], $ha)->assertOk();
        $this->open($url)->assertNotFound();
        $this->assertNotSame($url, $this->share($conv, $id, $ha)->json('data.url'), 'после отзыва адрес новый');
    }

    public function test_ссылка_умирает_с_сообщением_и_с_диалогом(): void
    {
        ['app' => $app] = $this->makeApp(['user_conversations' => true]);
        [$a, $ha] = $this->actor($app, 'a');
        [$b] = $this->actor($app, 'b');
        $conv = $this->group($ha, [$b->id]);
        $one = $this->say($conv, $ha, 'раз');
        $two = $this->say($conv, $ha, 'два');
        $u1 = $this->share($conv, $one, $ha)->json('data.url');
        $u2 = $this->share($conv, $two, $ha)->json('data.url');

        $this->deleteJson("/api/v1/conversations/$conv/messages/$one", [], $ha)->assertOk();
        $this->open($u1)->assertNotFound();
        $this->open($u2)->assertOk();

        $this->deleteJson("/api/v1/conversations/$conv?scope=all", [], $ha)->assertOk();
        $this->open($u2)->assertNotFound();
    }
}
