<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'street',
        'ward',
        'district',
        'city',
        'role',
        'is_active',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'role'              => UserRole::class,
        ];
    }

    // -------------------------------------------------------------------------
    // Phân quyền
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isUser(): bool
    {
        return $this->role === UserRole::User;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /** Giỏ hàng — quan hệ 1-1 */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** Các đơn hàng đã đặt */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Các phiếu nhập đã lập (với tư cách admin) */
    public function imports(): HasMany
    {
        return $this->hasMany(Import::class, 'admin_id');
    }

    /** Lịch sử thay đổi trạng thái đơn hàng (với tư cách admin) */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'admin_id');
    }
}
