<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FinancialAccount extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'type',
        'subtype',
        'name',
        'marketing_name',
        'tax_number',
        'owner',
        'number_last4',
        'balance',
        'currency',
        'credit_data',
        'data',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'decimal:2',
        'credit_data' => 'array',
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }
}
