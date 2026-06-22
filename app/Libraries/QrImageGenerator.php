<?php

namespace App\Libraries;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;

final class QrImageGenerator
{
    private QRCode $qrCodeInstance;
    private QRCode $svgQrCodeInstance;

    public function __construct()
    {
        $this->qrCodeInstance = new QRCode(new QROptions([
            'outputInterface' => QrPngOutput::class,
            'eccLevel'        => EccLevel::M,
            'scale'           => 5,
            'outputBase64'    => true,
        ]));

        $this->svgQrCodeInstance = new QRCode(new QROptions([
            'outputInterface'  => QRMarkupSVG::class,
            'eccLevel'         => EccLevel::M,
            'outputBase64'     => true,
            'svgAddXmlHeader'  => false,
        ]));
    }

    public function dataUri(string $content): string
    {
        // chillerlan v6 returns a full data URI when outputBase64 is true.
        return $this->qrCodeInstance->render($content);
    }

    /**
     * Returns an SVG data URI for the given content.
     * Used by the PDF renderer, which requires no ext-gd (unlike PNG).
     */
    public function svgDataUri(string $content): string
    {
        return $this->svgQrCodeInstance->render($content);
    }
}
