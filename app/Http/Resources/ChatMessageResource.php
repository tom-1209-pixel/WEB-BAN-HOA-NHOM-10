<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ChatMessageResource — Format response cho tin nhắn.
 *
 * Loại bỏ: conversation_id (redundant khi đã nằm trong conversation response).
 * Giữ lại: id, role, content, created_at.
 */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'role'       => $this->role,     // ChatRole enum → auto serialize thành 'user'/'model'
            'content'    => $this->content,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
