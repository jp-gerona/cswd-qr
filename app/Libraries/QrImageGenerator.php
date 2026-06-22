<?php

namespace App\Libraries;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;

final class QrImageGenerator
{
    private QRCode $qrCodeInstance;

    public function __construct()
    {
        $this->qrCodeInstance = new QRCode(new QROptions([
            'outputInterface' => QrPngOutput::class,
            'eccLevel'        => EccLevel::M,
            'scale'           => 5,
            'outputBase64'    => true,
        ]));
    }

    public function dataUri(string $content): string
    {
        // chillerlan v6 returns a full data URI when outputBase64 is true.
        return $this->qrCodeInstance->render($content);
    }
}
