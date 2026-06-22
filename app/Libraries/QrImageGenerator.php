<?php

namespace App\Libraries;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;

final class QrImageGenerator
{
    private QROptions $pngOptions;
    private QROptions $svgOptions;

    public function __construct()
    {
        $this->pngOptions = new QROptions([
            'outputInterface' => QrPngOutput::class,
            'eccLevel'        => EccLevel::M,
            // scale 4 (4px/module) keeps the code crisp at the 1.5in print size
            // while producing a smaller PNG that dompdf embeds faster than scale 5.
            'scale'           => 4,
            'outputBase64'    => true,
        ]);

        $this->svgOptions = new QROptions([
            'outputInterface'  => QRMarkupSVG::class,
            'eccLevel'         => EccLevel::M,
            'outputBase64'     => true,
            'svgAddXmlHeader'  => false,
        ]);
    }

    public function dataUri(string $content): string
    {
        // A fresh QRCode per call: a reused instance accumulates data segments
        // across render() calls and eventually exceeds QR capacity.
        // chillerlan v6 returns a full data URI when outputBase64 is true.
        return (new QRCode($this->pngOptions))->render($content);
    }

    /**
     * Returns an SVG data URI for the given content. Optional alternative output
     * mode: the PDF renderer currently embeds PNG via dataUri() (which needs
     * ext-gd). SVG needs no image extension, so this is the fallback to switch to
     * if an ext-gd-free deployment is ever required.
     */
    public function svgDataUri(string $content): string
    {
        // Fresh QRCode per call — see dataUri() for why reuse is unsafe.
        return (new QRCode($this->svgOptions))->render($content);
    }
}
