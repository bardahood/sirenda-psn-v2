<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->catat($model, 'CREATE', null, $this->bersih($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $baru = $this->bersih($model, $model->getChanges());

        // Soft delete & restore ditangani event masing-masing.
        unset($baru['deleted_at']);
        if (! $baru) {
            return;
        }

        $this->catat($model, 'UPDATE', Arr::only($this->bersih($model, $model->getOriginal()), array_keys($baru)), $baru);
    }

    public function deleted(Model $model): void
    {
        $this->catat($model, 'DELETE', $this->bersih($model, $model->getOriginal()), null);
    }

    public function restored(Model $model): void
    {
        $this->catat($model, 'RESTORE', null, $this->bersih($model, $model->getAttributes()));
    }

    protected function bersih(Model $model, array $nilai): array
    {
        return Arr::except($nilai, $model->auditAbaikan());
    }

    protected function catat(Model $model, string $aksi, ?array $lama, ?array $baru): void
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_label' => $user?->username,
            'tabel' => $model->getTable(),
            'record_id' => $model->getKey(),
            'psn_id' => $model->auditPsnId(),
            'aksi' => $aksi,
            'nilai_lama' => $lama,
            'nilai_baru' => $baru,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => app()->runningInConsole() ? 'console' : substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
