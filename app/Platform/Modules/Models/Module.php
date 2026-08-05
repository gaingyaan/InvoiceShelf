<?php

namespace App\Platform\Modules\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $table = 'modules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'installed' => 'boolean',
            'enabled' => 'boolean',
            'last_failed_at' => 'datetime',
        ];
    }
}
