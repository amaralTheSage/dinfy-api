<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\PasswordResetTokenNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'avatar',
        'name',
        'email',
        'email_verified_at',
        'workos_user_id',
        'phone',
        'phone_normalized',
        'whatsapp_phone',
        'whatsapp_phone_normalized',
        'whatsapp_opted_in_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'workos_user_id',
        'phone_normalized',
        'whatsapp_phone_normalized',
        'remember_token',
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
            'subscription_started_at' => 'datetime',
            'subscription_renews_at' => 'datetime',
            'subscription_canceled_at' => 'datetime',
            'whatsapp_opted_in_at' => 'datetime',
        ];
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function financialBudgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function sendPasswordResetNotification(
        $token,
        ?string $resetUrl = null,
        ?\DateTimeInterface $expiresAt = null,
    ): void {
        $this->notify(new PasswordResetTokenNotification((string) $token, $resetUrl, $expiresAt));
    }
}
