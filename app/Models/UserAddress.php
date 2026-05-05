<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'zipcode',
        'street',
        'neighborhood',
        'address_number',
        'address_complement',
        'state',
        'city',
    ];

    #UserAddress::create(['user_id'=>5,'zipcode'=>"96015420,'street'=>"Rua Padre Anchieta",'neighborhood'=> "Centro",'address_number'=>"4715",'state'=>"RS",'city'=>"Pelotas"]);

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
