<?php

namespace App\Enums;

/**
 * ChatRole — Vai trò của người gửi tin nhắn trong cuộc hội thoại.
 *
 * Gemini API sử dụng đúng 2 role:
 * - 'user'  : tin nhắn từ người dùng
 * - 'model' : tin nhắn phản hồi từ AI
 *
 * Enum này đồng bộ với giá trị role mà Gemini API yêu cầu,
 * nên KHÔNG được thay đổi value.
 */
enum ChatRole: string
{
    case User  = 'user';
    case Model = 'model';

    /**
     * Nhãn hiển thị tiếng Việt (dùng cho UI/logging).
     */
    public function label(): string
    {
        return match($this) {
            self::User  => 'Người dùng',
            self::Model => 'Trợ lý AI',
        };
    }
}
