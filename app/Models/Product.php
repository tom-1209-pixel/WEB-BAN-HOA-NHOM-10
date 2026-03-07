<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category_id',
        'description',
        'unit',
        'quantity',
        'low_stock_threshold',
        'image',
        'profit_rate',
        'avg_import_price',
        'selling_price',
        'status',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'low_stock_threshold' => 'integer',
        'profit_rate'       => 'decimal:2',
        'avg_import_price'  => 'decimal:2',
        'selling_price'     => 'decimal:2',
    ];

    /**
     * Sản phẩm thuộc về một danh mục.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Tính và cập nhật giá bán dựa trên giá nhập bình quân và tỷ lệ lợi nhuận.
     * Công thức: selling_price = avg_import_price * (1 + profit_rate / 100)
     */
    public function recalculateSellingPrice(): void
    {
        $this->selling_price = $this->avg_import_price * (1 + $this->profit_rate / 100);
    }
}
