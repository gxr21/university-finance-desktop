<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\HttpException;
use App\Support\Money;

final class PaymentValidator
{
    public static function amount(string $amount): string
    {
        $n = Money::norm($amount);
        if (Money::cmp($n, '0') <= 0) {
            throw HttpException::unprocessable('لا يمكن تسجيل دفعة صفرية أو سالبة.');
        }
        return $n;
    }
}
