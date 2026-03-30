<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderDetailResource - Chi tiết đơn hàng
 *
 * Bao gồm thông tin sản phẩm (nếu đã eager load).
 */
class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'quantity'   => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal'   => $this->subtotal,

            'product' => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
            ]),
        ];
    }
}
