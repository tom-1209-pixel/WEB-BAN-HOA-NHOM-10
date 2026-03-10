<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete(); // Khi sản phẩm bị xóa, tự xóa khỏi giỏ

            $table->integer('quantity')->unsigned();
            $table->timestamp('added_at')->useCurrent();

            // Mỗi sản phẩm chỉ xuất hiện 1 lần trong giỏ — tăng quantity nếu thêm lại
            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
