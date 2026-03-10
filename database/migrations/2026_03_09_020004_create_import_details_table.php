<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_details', function (Blueprint $table) {
            $table->id();

            // Khi xóa phiếu nhập (drafting) → xóa cascade các dòng chi tiết
            $table->foreignId('import_id')
                ->constrained('imports')
                ->cascadeOnDelete();

            // Không cho xóa sản phẩm nếu đã có trong phiếu nhập
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->integer('quantity')->unsigned();
            $table->decimal('import_price', 15, 2);
            $table->decimal('subtotal', 15, 2); // = quantity * import_price, tính trước để query nhanh

            // Mỗi sản phẩm chỉ xuất hiện 1 lần trong 1 phiếu nhập
            $table->unique(['import_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_details');
    }
};
