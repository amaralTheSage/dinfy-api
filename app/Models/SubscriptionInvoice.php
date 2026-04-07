<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
        protected $fillable = [
                'user_subscription_id',
                'provider',
                'provider_payment_id',
                'external_reference',
                'transaction_amount',
                'currency_id',
                'status',
                'status_detail',
                'expires_at',
                'paid_at',
                'canceled_at',
                'qr_code',
                'qr_code_base64',
                'qr_code_expires_at',
        ];

        protected $casts = [
                'transaction_amount' => 'decimal:2',
                'expires_at' => 'datetime',
                'paid_at' => 'datetime',
                'canceled_at' => 'datetime',
                'qr_code_expires_at' => 'datetime',
        ];

        public function subscription(): BelongsTo
        {
                return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
        }
}
