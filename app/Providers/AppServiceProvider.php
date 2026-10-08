<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
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
