<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_code',
        'status',
        'external_reference',
        'mercado_pago_preapproval_id',
        'mercado_pago_payment_id',
        'mercado_pago_authorized_payment_id',
        'transaction_amount',
        'currency_id',
        'frequency',
        'frequency_type',
        'checkout_url',
        'sandbox_checkout_url',
        'latest_payment_status',
        'latest_payment_status_detail',
        'started_at',
        'next_payment_at',
        'canceled_at',
        'last_notified_at',
        'raw_payload',
        'latest_payment_payload',
    ];

    protected function casts(): array
    {
        return [
            'transaction_amount' => 'decimal:2',
            'frequency' => 'integer',
            'started_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'canceled_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'raw_payload' => 'array',
            'latest_payment_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
