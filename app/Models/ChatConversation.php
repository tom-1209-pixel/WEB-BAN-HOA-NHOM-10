<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChatConversation — Cuộc hội thoại chatbot.
 *
 * Mỗi conversation thuộc về 1 user (hoặc null = guest).
 * Chứa nhiều messages, sắp xếp theo thời gian.
 */
class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /** Người dùng sở hữu cuộc hội thoại (nullable = guest). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Tất cả tin nhắn trong cuộc hội thoại, sắp xếp theo thời gian. */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')
                    ->orderBy('created_at');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Lọc conversation theo user_id.
     * Dùng cho: lấy danh sách hội thoại của user đang đăng nhập.
     */
    public function scopeForUser($query, ?int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
