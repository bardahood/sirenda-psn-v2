<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->user = User::create(['username' => 'dit.sda', 'name' => 'Dit SDA', 'email' => 'sda@bappenas.go.id', 'password' => 'KataSandi-123'])->assignRole('Pimpinan');
    }

    public function test_login_dengan_username_atau_email_dan_tercatat(): void
    {
        $this->post('/login', ['login' => 'dit.sda', 'password' => 'KataSandi-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
        $this->post('/logout');
        $this->post('/login', ['login' => 'SDA@bappenas.go.id', 'password' => 'KataSandi-123'])->assertRedirect('/dashboard');

        $this->assertSame(['LOGIN', 'LOGOUT', 'LOGIN'], DB::table('login_log')->orderBy('id')->pluck('aktivitas')->all());
        $this->assertSame(2, $this->user->fresh()->jumlah_login);
    }

    public function test_kata_sandi_salah_dan_pembatasan_percobaan(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'dit.sda', 'password' => 'salah'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => 'dit.sda', 'password' => 'KataSandi-123'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $this->assertSame(5, DB::table('login_log')->where('aktivitas', 'GAGAL')->count());
    }

    public function test_akun_nonaktif_ditolak_dan_dikeluarkan(): void
    {
        $this->user->update(['is_active' => false]);
        $this->post('/login', ['login' => 'dit.sda', 'password' => 'KataSandi-123'])->assertSessionHasErrors('login');

        // Dinonaktifkan saat sesi berjalan.
        $this->actingAs($this->user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wajib_ganti_kata_sandi_sebelum_lanjut(): void
    {
        $this->user->update(['wajib_ganti_password' => true]);
        $this->actingAs($this->user)->get('/dashboard')->assertRedirect('/ganti-password');
        $this->getJson('/api/v1/dashboard/kpi')->assertForbidden();

        $this->put('/ganti-password', ['password_lama' => 'KataSandi-123', 'password' => 'lemah', 'password_confirmation' => 'lemah'])->assertSessionHasErrors('password');
        $this->put('/ganti-password', ['password_lama' => 'KataSandi-123', 'password' => 'KataSandiBaru2026', 'password_confirmation' => 'KataSandiBaru2026'])->assertRedirect('/dashboard');
        $this->assertFalse($this->user->fresh()->wajib_ganti_password);
        $this->get('/dashboard')->assertOk();
    }
}
