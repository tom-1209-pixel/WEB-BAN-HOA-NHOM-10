<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Giữ lịch sử dù sản phẩm bị ẩn — không cho xóa sản phẩm nếu đã bán
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->integer('quantity')->unsigned();

            // Snapshot giá bán tại thời điểm đặt hàng — không tham chiếu products.selling_price
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2); // = quantity * unit_price
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
