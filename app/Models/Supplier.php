<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }
}
