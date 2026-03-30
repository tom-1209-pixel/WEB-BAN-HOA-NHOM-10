<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource - Đơn hàng
 *
 * Gom thông tin giao hàng vào object 'shipping'.
 * Dùng whenLoaded cho user và details.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'total_amount'   => $this->total_amount,
            'note'           => $this->note,

            'shipping' => [
                'name'     => $this->shipping_name,
                'phone'    => $this->shipping_phone,
                'street'   => $this->shipping_street,
                'ward'     => $this->shipping_ward,
                'district' => $this->shipping_district,
                'city'     => $this->shipping_city,
            ],

            'user'    => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'details' => $this->whenLoaded('details', fn () => OrderDetailResource::collection($this->details)),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
