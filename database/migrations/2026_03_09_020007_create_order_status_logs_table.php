<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng append-only — chỉ ghi thêm, không bao giờ update/delete.
     * Dùng để audit toàn bộ lịch sử chuyển trạng thái đơn hàng.
     */
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Cho phép null: end-user cũng có thể cancel từ pending (trước khi admin confirm)
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('from_status', ['pending', 'confirmed', 'delivered', 'cancelled']);
            $table->enum('to_status', ['pending', 'confirmed', 'delivered', 'cancelled']);
            $table->text('note')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
