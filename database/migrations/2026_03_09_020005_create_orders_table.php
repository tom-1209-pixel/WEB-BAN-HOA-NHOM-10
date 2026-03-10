<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Snapshot địa chỉ giao hàng tại thời điểm đặt — tách biệt hoàn toàn với users.address
            $table->string('shipping_name', 150);
            $table->string('shipping_phone', 20);
            $table->text('shipping_street');
            $table->string('shipping_ward', 100);
            $table->string('shipping_district', 100);
            $table->string('shipping_city', 100);

            $table->enum('payment_method', ['cash', 'transfer', 'online']);

            // pending → confirmed → delivered (terminal)
            // pending → cancelled (terminal) | confirmed → cancelled (terminal)
            $table->enum('status', ['pending', 'confirmed', 'delivered', 'cancelled'])->default('pending');

            $table->decimal('total_amount', 15, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
