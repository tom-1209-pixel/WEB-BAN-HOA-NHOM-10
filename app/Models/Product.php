<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'quantity'            => 'integer',
        'low_stock_threshold' => 'integer',
        'profit_rate'         => 'decimal:2',
        'avg_import_price'    => 'decimal:2',
        'selling_price'       => 'decimal:2',
        'status'              => ProductStatus::class,
    ];

    // -------------------------------------------------------------------------
    // Business Logic
    // -------------------------------------------------------------------------

    /**
     * Tính lại giá bán dựa trên giá nhập bình quân và tỷ lệ lợi nhuận.
     * Công thức: selling_price = avg_import_price × (1 + profit_rate / 100)
     */
    public function recalculateSellingPrice(): void
    {
        $this->selling_price = round(
            $this->avg_import_price * (1 + $this->profit_rate / 100),
            2
        );
    }

    /**
     * Tính lại giá nhập bình quân khi hoàn thành phiếu nhập (Weighted Average).
     */
    public function recalculateAvgImportPrice(int $importedQty, float $importedPrice): void
    {
        $currentQty   = $this->quantity;
        $currentAvg   = (float) $this->avg_import_price;
        $totalQty      = $currentQty + $importedQty;

        if ($totalQty === 0) {
            return;
        }

        $this->avg_import_price = round(
            ($currentQty * $currentAvg + $importedQty * $importedPrice) / $totalQty,
            2
        );
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Visible);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'low_stock_threshold')
                     ->where('status', ProductStatus::Visible);
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importDetails(): HasMany
    {
        return $this->hasMany(ImportDetail::class);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
