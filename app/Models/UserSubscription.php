<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'plan_code',
        'status',
        'external_reference',
        'mercado_pago_payment_id',
        'transaction_amount',
        'currency_id',
        'frequency',
        'frequency_type',
        'payer_document_type',
        'payer_document_number',
        'latest_payment_status',
        'latest_payment_status_detail',
        'started_at',
        'next_payment_at',
        'canceled_at',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'transaction_amount' => 'decimal:2',
            'frequency' => 'integer',
            'started_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'canceled_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'user_subscription_id');
    }
}
