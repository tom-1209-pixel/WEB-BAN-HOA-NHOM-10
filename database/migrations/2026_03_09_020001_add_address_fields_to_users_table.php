<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các trường địa chỉ vào bảng users:
     * street, ward, district, city — dùng làm địa chỉ mặc định khi checkout.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('street')->nullable()->after('phone');
            $table->string('ward', 100)->nullable()->after('street');
            $table->string('district', 100)->nullable()->after('ward');
            $table->string('city', 100)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['street', 'ward', 'district', 'city']);
        });
    }
};
