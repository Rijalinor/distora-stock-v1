<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Enums\UserRole;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mobile_api_token_hash',
        'mobile_api_token_created_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mobile_api_token_hash',
    ];

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
            'role' => UserRole::class,
            'mobile_api_token_created_at' => 'datetime',
        ];
    }

    public function csvUploads()
    {
        return $this->hasMany(CsvUpload::class, 'uploaded_by');
    }

    public function stockSessions()
    {
        return $this->hasMany(StockSession::class, 'assigned_to');
    }

    public function stockAdjustmentLogs()
    {
        return $this->hasMany(StockAdjustmentLog::class, 'adjusted_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStockOfficer(): bool
    {
        return $this->role === UserRole::StockOfficer;
    }

    public function issueMobileApiToken(): string
    {
        $plainToken = bin2hex(random_bytes(32));

        $this->forceFill([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
        ])->save();

        return $plainToken;
    }

    public function revokeMobileApiToken(): void
    {
        $this->forceFill([
            'mobile_api_token_hash' => null,
            'mobile_api_token_created_at' => null,
        ])->save();
    }
}
