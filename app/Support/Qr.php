<?php
declare(strict_types=1);

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class Qr
{
    public static function svg(string $payload): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 5,
            'imageBase64' => false,
        ]);
        return (new QRCode($options))->render($payload);
    }

    public static function dataUri(string $payload): string
    {
        $svg = self::svg($payload);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
