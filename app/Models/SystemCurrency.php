<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCurrency extends Model
{
    protected $table = 'system_currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
        'sort_order',
    ];

    public $timestamps = false;
}
