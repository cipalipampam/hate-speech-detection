<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    
    use HasFactory, Notifiable, HasRoles;

    /**
     * Atribut yang boleh diisi massal.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atribut beserta tipe cast-nya.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    /** Semua sesi analisis yang dibuat oleh user ini. */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class, 'user_id');
    }

    /** Riwayat pengujian kalimat teks tunggal. */
    public function singlePredictions(): HasMany
    {
        return $this->hasMany(SinglePrediction::class, 'user_id');
    }
}
