<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Danh sách trạng thái hợp lệ có thể chuyển tới từ trạng thái hiện tại.
     * State machine một chiều — không quay lui.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending   => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Delivered, self::Cancelled],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Delivered => 'Đã giao hàng',
            self::Cancelled => 'Đã hủy',
        };
    }
}
