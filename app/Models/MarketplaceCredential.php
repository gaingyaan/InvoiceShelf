<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceCredential extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credential'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paired_at' => 'datetime',
        ];
    }
}
