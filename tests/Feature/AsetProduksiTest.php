<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aset Vite di hosting: tidak memaksa https saat APP_URL masih http, dan public/hot diabaikan di produksi. */
class AsetProduksiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(public_path('hot'));
        parent::tearDown();
    }

    protected function halamanLogin(string $appUrl): string
    {
        $this->app['env'] = 'production';
        config(['app.url' => $appUrl]);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        return $this->get('http://sirenda.contoh.test/login')->assertOk()->getContent();
    }

    public function test_app_url_http_tidak_memaksa_https_dan_public_hot_diabaikan(): void
    {
        file_put_contents(public_path('hot'), 'http://localhost:5173');
        $html = $this->halamanLogin('http://sirenda.contoh.test');
        $this->assertMatchesRegularExpression('#href="http://sirenda\.contoh\.test/build/assets/app-[^"]+\.css"#', $html);
        $this->assertStringNotContainsString('5173', $html);
    }

    public function test_app_url_https_memaksa_https(): void
    {
        $this->assertMatchesRegularExpression('#href="https://sirenda\.contoh\.test/build/assets/app-[^"]+\.css"#', $this->halamanLogin('https://sirenda.contoh.test'));
    }
}
