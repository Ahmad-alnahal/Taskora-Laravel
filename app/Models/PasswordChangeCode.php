<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordChangeCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'code_hash',
        'attempts',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
