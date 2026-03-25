<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantExecution extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'phone_normalized',
        'idempotency_key',
        'intent',
        'status',
        'request_payload',
        'response_payload',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
