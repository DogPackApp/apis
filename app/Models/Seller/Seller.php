<?php

namespace App\Models\Seller;

use App\Mail\SellerForgotPasswordEmail;
use App\Models\Store\Store;
use Database\Factories\SellerFactory;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class Seller extends Authenticatable implements CanResetPassword, OAuthenticatable
{
    /** @use HasFactory<SellerFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'status',
        'google_id',
        'twofa_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'twofa_enabled' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Seller $seller): void {
            $seller->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): SellerFactory
    {
        return SellerFactory::new();
    }

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new SellerForgotPasswordEmail($token, $this->email, $this->first_name));
    }

    public function isVerified(): bool
    {
        return $this->status === 1;
    }

    public function is2FAEnabled(): bool
    {
        return $this->twofa_enabled === 1;
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }
}
