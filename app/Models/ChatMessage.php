<?php

namespace App\Models;

use App\Enums\ChatRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChatMessage — Tin nhắn trong cuộc hội thoại chatbot.
 *
 * - role: cast thành ChatRole enum ('user' | 'model')
 * - content: nội dung tin nhắn (text dài)
 * - tokens_used: số token đã tiêu thụ (chỉ có ở response từ Gemini)
 */
class ChatMessage extends Model
{
    /** Bảng chỉ có created_at, không có updated_at (tin nhắn không sửa). */
    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens_used',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'role'        => ChatRole::class,
            'tokens_used' => 'integer',
            'created_at'  => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /** Cuộc hội thoại chứa tin nhắn này. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
