<?php

namespace App\Domains\Contacts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    use HasFactory;

    public function address(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
