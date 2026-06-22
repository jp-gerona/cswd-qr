<?php

namespace App\Libraries;

use chillerlan\QRCode\Output\QROutputAbstract;

use function pack, str_repeat, strlen;

/**
 * Pure-PHP PNG output for chillerlan/php-qrcode v6.
 * Produces a grayscale PNG (1 byte per pixel, 8-bit) without requiring ext-gd.
 * Dark modules render as black (0x00), light modules as white (0xFF).
 */
final class QrPngOutput extends QROutputAbstract
{
    public const MIME_TYPE = 'image/png';

    public static function moduleValueIsValid(mixed $value): bool
    {
        return true;
    }

    protected function prepareModuleValue(mixed $value): bool
    {
        return (bool) $value;
    }

    protected function getDefaultModuleValue(bool $isDark): bool
    {
        return $isDark;
    }

    /**
     * Builds a PNG binary from the QR matrix and returns it
     * (or a base64 data URI when $this->options->outputBase64 is true).
     */
    public function dump(string|null $file = null): string
    {
        $imageSize  = $this->length; // moduleCount * scale
        $rawPixels  = $this->buildRawPixelRows($imageSize);
        $pngBinary  = $this->encodeToPng($imageSize, $rawPixels);

        $this->saveToFile($pngBinary, $file);

        if ($this->options->outputBase64) {
            return $this->toBase64DataURI($pngBinary, self::MIME_TYPE);
        }

        return $pngBinary;
    }

    /**
     * Walks the QR matrix and returns an array of raw scanline byte strings.
     * Each scanline is prefixed with a PNG filter byte (0x00 = no filter).
     *
     * @return string[] one element per pixel row
     */
    private function buildRawPixelRows(int $imageSize): array
    {
        $matrixSize = $this->moduleCount;
        $scale      = $this->scale;

        // Build one pixel row per module row, then repeat it $scale times.
        $allScanlines = [];

        for ($moduleRow = 0; $moduleRow < $matrixSize; $moduleRow++) {
            $scanlinePixels = '';

            for ($moduleColumn = 0; $moduleColumn < $matrixSize; $moduleColumn++) {
                $moduleType  = $this->matrix->get($moduleColumn, $moduleRow);
                $isDarkPixel = $this->moduleValues[$moduleType] ?? false;
                $pixelByte   = $isDarkPixel ? "\x00" : "\xFF";
                // Each module is $scale pixels wide.
                $scanlinePixels .= str_repeat($pixelByte, $scale);
            }

            // PNG filter byte (0 = None) prepended to each scanline.
            $filteredScanline = "\x00" . $scanlinePixels;

            // Each module is $scale pixels tall — repeat the scanline.
            for ($scaleRow = 0; $scaleRow < $scale; $scaleRow++) {
                $allScanlines[] = $filteredScanline;
            }
        }

        return $allScanlines;
    }

    /**
     * Encodes the raw scanlines into a valid PNG binary.
     * Uses an 8-bit grayscale colour mode (no alpha).
     */
    private function encodeToPng(int $imageSize, array $allScanlines): string
    {
        $rawImageData = implode('', $allScanlines);

        // zlib-compress the raw scanlines (the IDAT chunk payload). Level 6 is
        // the zlib default: near-max ratio for these tiny 1-bit images at a
        // fraction of level 9's CPU cost — meaningful across thousands of codes.
        $compressedImageData = gzcompress($rawImageData, 6);

        $pngSignature = "\x89PNG\r\n\x1a\n";

        // IHDR: width, height, bit depth (8), colour type (0 = grayscale),
        //        compression (0), filter (0), interlace (0).
        $ihdrData  = pack('NNCCCCC', $imageSize, $imageSize, 8, 0, 0, 0, 0);
        $ihdrChunk = $this->buildPngChunk('IHDR', $ihdrData);

        $idatChunk = $this->buildPngChunk('IDAT', $compressedImageData);
        $iendChunk = $this->buildPngChunk('IEND', '');

        return $pngSignature . $ihdrChunk . $idatChunk . $iendChunk;
    }

    /**
     * Wraps $data in a PNG chunk with the given 4-byte $type and a CRC32 checksum.
     */
    private function buildPngChunk(string $chunkType, string $chunkData): string
    {
        $dataLength = strlen($chunkData);
        $crc32Value = crc32($chunkType . $chunkData);

        return pack('N', $dataLength) . $chunkType . $chunkData . pack('N', $crc32Value & 0xFFFFFFFF);
    }
}
