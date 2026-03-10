<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các trường profile vào bảng users:
     * - username: tên đăng nhập duy nhất
     * - full_name: họ tên đầy đủ
     * - phone: số điện thoại
     * - role: phân quyền (admin / user)
     * - is_active: trạng thái tài khoản
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm sau cột 'name', cho phép null vì bảng đã có dữ liệu cũ
            $table->string('username')->unique()->nullable()->after('name');
            $table->string('full_name')->nullable()->after('username');
            $table->string('phone', 20)->nullable()->after('full_name');
            $table->enum('role', ['admin', 'user'])->default('user')->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Hoàn tác migration.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'full_name', 'phone', 'role', 'is_active']);
        });
    }
};
