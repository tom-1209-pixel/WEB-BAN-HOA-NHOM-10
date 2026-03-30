<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng chat_conversations — Lưu các cuộc hội thoại chatbot.
 *
 * - user_id nullable: hỗ trợ cả guest chat (không bắt buộc đăng nhập)
 * - title: tiêu đề tự động sinh từ tin nhắn đầu tiên
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();        // Xóa user → conversation vẫn giữ (orphan)
            $table->string('title', 255)->default('Cuộc trò chuyện mới');
            $table->timestamps();

            // Index: tìm conversation theo user nhanh hơn
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
