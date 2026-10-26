<?php
declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function norm(string|int|float $value): string
    {
        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }
        $value = (string) $value;
        if ($value === '' || !is_numeric($value)) {
            return '0.00';
        }
        return bcadd($value, '0', 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::norm($a), self::norm($b), 2);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::norm($a), self::norm($b), 2);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::norm($a), self::norm($b), 2);
    }

    public static function percent(string $amount, string $rate): string
    {
        return bcdiv(bcmul(self::norm($amount), self::norm($rate), 4), '100', 2);
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::norm($a), self::norm($b), 2);
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? self::norm($a) : self::norm($b);
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::norm($a) : self::norm($b);
    }

    public static function format(string $value): string
    {
        return number_format((float) self::norm($value), 0, '.', ',');
    }

    public static function toArabicWords(string $value): string
    {
        $n = (int) explode('.', self::norm($value))[0];
        if ($n === 0) {
            return 'صفر دينار عراقي فقط لا غير';
        }
        return self::three($n) . ' دينار عراقي فقط لا غير';
    }

    private static function three(int $n): string
    {
        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $teens = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $hundreds = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

        if ($n < 0) {
            return 'سالب ' . self::three(abs($n));
        }
        if ($n >= 1000000) {
            $m = intdiv($n, 1000000);
            $rest = $n % 1000000;
            $head = $m === 1 ? 'مليون' : ($m === 2 ? 'مليونان' : self::three($m) . ' ملايين');
            return $rest ? $head . ' و' . self::three($rest) : $head;
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $rest = $n % 1000;
            $head = $k === 1 ? 'ألف' : ($k === 2 ? 'ألفان' : self::three($k) . ' آلاف');
            return $rest ? $head . ' و' . self::three($rest) : $head;
        }
        $h = intdiv($n, 100);
        $r = $n % 100;
        $parts = [];
        if ($h) {
            $parts[] = $hundreds[$h];
        }
        if ($r >= 10 && $r <= 19) {
            $parts[] = $teens[$r - 10];
        } else {
            $o = $r % 10;
            $t = intdiv($r, 10);
            if ($o) {
                $parts[] = $ones[$o];
            }
            if ($t) {
                $parts[] = $tens[$t];
            }
        }
        return implode(' و', array_filter($parts));
    }
}
