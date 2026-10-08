<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'unit_kerja_id',
        'is_active',
        'wajib_ganti_password',
        'last_login_at',
        'jumlah_login',
        'legacy_grup',
        'legacy_akses',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(RefUnitKerja::class);
    }

    /** Hanya melihat PSN dalam cakupan unit kerjanya (L¹). */
    public function lihatTerbatas(): bool
    {
        return $this->hasAnyRole(config('psn_dashboard.rbac.peran_lihat_terbatas'));
    }

    /** Input/verifikasi dibatasi pada PSN dalam cakupan unit kerjanya (I¹/V¹). */
    public function ubahTerbatas(): bool
    {
        return $this->hasAnyRole(config('psn_dashboard.rbac.peran_ubah_terbatas'));
    }

    public function mengampuPsn(int $psnId): bool
    {
        return $this->unit_kerja_id !== null && PsnUnitPengampu::withoutGlobalScopes()
            ->where('psn_id', $psnId)->where('unit_kerja_id', $this->unit_kerja_id)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'wajib_ganti_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
