<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ChatConversationResource — Format response cho cuộc hội thoại.
 *
 * Trả về thông tin tổng quan: title, số tin nhắn, preview tin nhắn cuối.
 * Messages chi tiết chỉ trả về khi đã eager load (whenLoaded).
 */
class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Chỉ trả về khi eager load withCount('messages')
            'message_count' => $this->whenCounted('messages'),

            // Chỉ trả về khi eager load with('messages')
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
