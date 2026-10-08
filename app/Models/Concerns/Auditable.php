<?php

namespace App\Models\Concerns;

use App\Observers\AuditObserver;

/**
 * Setiap create/update/delete/restore dicatat ke audit_log (nilai lama & baru).
 * Model dapat menimpa auditPsnId() bila psn_id tidak ada langsung di tabelnya.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    public function auditPsnId(): ?int
    {
        return $this->getAttribute('psn_id');
    }

    /** Kolom yang tidak perlu dicatat sebagai perubahan. */
    public function auditAbaikan(): array
    {
        return ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by'];
    }
}
