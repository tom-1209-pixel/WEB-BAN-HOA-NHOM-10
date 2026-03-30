<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource - Thông tin người dùng
 *
 * Không trả về: password, api_token, remember_token
 * Gom địa chỉ vào object 'address' cho gọn gàng
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'username'  => $this->username,
            'email'     => $this->email,
            'full_name' => $this->full_name,
            'phone'     => $this->phone,
            'role'      => $this->role,
            'is_active' => $this->is_active,

            'address' => [
                'street'   => $this->street,
                'ward'     => $this->ward,
                'district' => $this->district,
                'city'     => $this->city,
            ],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
