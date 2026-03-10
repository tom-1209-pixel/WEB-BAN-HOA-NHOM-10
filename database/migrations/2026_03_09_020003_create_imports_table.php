<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();

            // Admin lập phiếu — giữ lịch sử dù admin bị xóa (restrictOnDelete)
            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Nhà cung cấp tùy chọn — 1 lần nhập có thể không rõ NCC
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->nullOnDelete();

            $table->text('note')->nullable();

            // drafting: đang soạn có thể sửa; completed: khóa hoàn toàn
            $table->enum('status', ['drafting', 'completed'])->default('drafting');

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
