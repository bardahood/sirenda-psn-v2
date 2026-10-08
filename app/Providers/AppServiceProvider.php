<?php

namespace App\Providers;

use App\Models\Penilaian;
use App\Policies\UsulanPsnPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
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
