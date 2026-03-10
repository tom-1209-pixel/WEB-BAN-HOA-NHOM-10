<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'product_id',
        'quantity',
        'import_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'import_price' => 'decimal:2',
        'subtotal'     => 'decimal:2',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
