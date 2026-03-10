<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash     = 'cash';
    case Transfer = 'transfer';
    case Online   = 'online';

    public function label(): string
    {
        return match($this) {
            self::Cash     => 'Tiền mặt',
            self::Transfer => 'Chuyển khoản',
            self::Online   => 'Thanh toán trực tuyến',
        };
    }
}
