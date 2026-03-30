<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng chat_messages — Lưu từng tin nhắn trong cuộc hội thoại.
 *
 * - role: 'user' hoặc 'model' (đồng bộ với Gemini API)
 * - content: nội dung tin nhắn (text dài)
 * - tokens_used: số token đã dùng (nullable, chỉ có ở response từ model)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                  ->constrained('chat_conversations')
                  ->cascadeOnDelete();     // Xóa conversation → xóa hết messages
            $table->string('role', 10);    // 'user' | 'model' — cast bằng ChatRole enum
            $table->longText('content');    // Nội dung tin nhắn
            $table->unsignedInteger('tokens_used')->nullable(); // Token count từ Gemini
            $table->timestamp('created_at')->useCurrent();

            // Index: lấy messages theo conversation, sắp xếp theo thời gian
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
