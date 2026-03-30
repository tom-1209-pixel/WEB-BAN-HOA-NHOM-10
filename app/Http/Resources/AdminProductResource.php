<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AdminProductResource - Dành cho admin
 *
 * Hiển thị đầy đủ thông tin bao gồm:
 * - Giá nhập bình quân, tỷ lệ lợi nhuận
 * - Số lượng tồn kho thực tế, ngưỡng cảnh báo
 * - Trạng thái sắp hết hàng
 */
class AdminProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'description'         => $this->description,
            'unit'                => $this->unit,
            'quantity'            => $this->quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_low_stock'        => $this->isLowStock(),
            'image'               => $this->image,
            'profit_rate'         => $this->profit_rate,
            'avg_import_price'    => $this->avg_import_price,
            'selling_price'       => $this->selling_price,
            'status'              => $this->status,

            'category' => [
                'id'   => $this->category?->id,
                'name' => $this->category?->name,
            ],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
