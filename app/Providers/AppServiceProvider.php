<?php

namespace App\Providers;

use App\Models\Penilaian;
use App\Policies\UsulanPsnPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HTTPS dipaksa bila APP_URL https. Bila sertifikat belum aktif (APP_URL masih http), CSS/JS tetap
        // dimuat lewat http; memaksa https saat itu membuat halaman tampil tanpa gaya.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        // Produksi: abaikan public/hot sisa `npm run dev` yang ikut terunggah (mengarahkan aset ke localhost:5173).
        if ($this->app->isProduction()) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }

        // Super Admin memiliki seluruh hak (K pada semua modul).
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);
        // Aksi atas penilaian diotorisasi oleh policy usulan induknya.
        Gate::policy(Penilaian::class, UsulanPsnPolicy::class);

        // Kolom jejak standar untuk tabel data inti: siapa membuat/mengubah/menghapus
        // dan kapan. Nilai lama/baru per perubahan dicatat terpisah di audit_log.
        Blueprint::macro('jejak', function (bool $softDeletes = true) {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamps();

            if ($softDeletes) {
                $this->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $this->softDeletes();
            }
        });
    }
}
