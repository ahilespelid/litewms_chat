<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ChatHelpers;
use Tests\TestCase;

class PagesAndDocsTest extends TestCase
{
    use ChatHelpers, RefreshDatabase;

    public function test_embed_allows_only_app_origins_as_frame_ancestors(): void
    {
        $this->withoutVite();
        ['app' => $app] = $this->makeApp([], ['https://test.litewms.ru', 'https://litewms.ru']);

        $r = $this->get('/embed?app='.$app->key)->assertOk();
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self' https://test.litewms.ru https://litewms.ru", $csp);
        $this->assertNull($r->headers->get('Set-Cookie'), 'чат не ставит cookie');

        $this->get('/embed?app=lw_pub_doesnotexist12345')->assertNotFound();
        $this->get('/embed')->assertNotFound();
    }

    public function test_full_page_cannot_be_framed(): void
    {
        $this->withoutVite();
        $this->get('/app')->assertOk()->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }

    public function test_every_api_route_is_in_openapi_spec(): void
    {
        $spec = $this->getJson('/docs/openapi.json')->assertOk()->json();

        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('participant', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('server', $spec['components']['securitySchemes']);

        $documented = collect($spec['paths'])->flatMap(fn ($ops, $path) => collect($ops)->keys()->map(fn ($m) => strtoupper($m).' '.ltrim($path, '/')));

        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && ! str_contains($r->uri(), 'broadcasting') && ! str_contains($r->uri(), 'media'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => $m.' '.preg_replace('#^api/v1/#', '', $r->uri())))
            ->reject(fn ($sig) => $documented->contains($sig));

        $this->assertSame([], $missing->values()->all(), 'эти маршруты не попали в Swagger');
    }
}
