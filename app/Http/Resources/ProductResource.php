<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource - Dành cho user (end-user / public API)
 *
 * Giấu các thông tin nhạy cảm:
 * - avg_import_price (giá nhập)
 * - profit_rate (tỷ lệ lợi nhuận)
 * - low_stock_threshold (ngưỡng cảnh báo tồn kho)
 * - quantity (số lượng thực tế trong kho)
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => $this->description,
            'unit'          => $this->unit,
            'selling_price' => $this->selling_price,
            'image'         => $this->image,
            'status'        => $this->status,
            'in_stock'      => $this->quantity > 0,

            'category' => [
                'id'   => $this->category?->id,
                'name' => $this->category?->name,
            ],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
