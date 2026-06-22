<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

final class QrBatchPdfGenerator
{
    /**
     * A full 50-page chunk with embedded SVG QR codes needs roughly 180 MB to
     * render in dompdf — more than the 128 MB a default web request is given.
     * Raise the limit only when it is currently lower so we never shrink it.
     */
    private const RENDER_MEMORY_LIMIT_BYTES = 512 * 1024 * 1024;

    /**
     * A full 50-page chunk takes well over the default 30s web execution cap to
     * render in dompdf. set_time_limit() resets the timer; renderChunkPdf runs
     * once per chunk, so each chunk is granted a fresh allowance.
     */
    private const RENDER_TIME_LIMIT_SECONDS = 300;

    public function renderChunkPdf(int $startNumber, int $quantityInChunk): string
    {
        $this->ensureRenderMemoryLimit();
        set_time_limit(self::RENDER_TIME_LIMIT_SECONDS);

        // dompdf embeds PNGs via the GD extension. Fail fast with a clear,
        // actionable message instead of dompdf's cryptic deep-stack exception.
        if (! extension_loaded('gd')) {
            throw new \RuntimeException(
                'The PHP GD extension is required to embed PNG QR codes. '
                . 'Install it (e.g. "sudo port install php82-gd") and restart the server.'
            );
        }

        $qrImageGenerator = new QrImageGenerator();
        $controlNumbers   = QrBatchPlanner::controlNumbers($quantityInChunk, $startNumber);

        $pagesHtml  = '';
        $pageNumber = 0;
        foreach (array_chunk($controlNumbers, QrBatchPlanner::CELLS_PER_PAGE) as $pageControlNumbers) {
            $pageNumber++;
            $cells = [];
            foreach ($pageControlNumbers as $controlNumber) {
                $cells[] = [
                    'controlNumber' => $controlNumber,
                    'qrDataUri'     => $qrImageGenerator->dataUri($controlNumber),
                ];
            }
            // Pad the final page with blank cells so every page is a full 3x4
            // grid — otherwise a short last row stretches its columns/height and
            // the cards lose their consistent size.
            while (count($cells) < QrBatchPlanner::CELLS_PER_PAGE) {
                $cells[] = ['controlNumber' => '', 'qrDataUri' => ''];
            }
            $pagesHtml .= view('pdf/batch_page', [
                'cells'       => $cells,
                'isFirstPage' => $pageNumber === 1,
            ]);
            unset($cells); // release per-page QR data
        }

        $documentHtml = view('pdf/_styles') . $pagesHtml;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('fontDir', WRITEPATH . 'fonts');
        $options->set('fontCache', WRITEPATH . 'fonts');
        $options->set('defaultFont', 'Roboto');

        $dompdf = new Dompdf($options);
        $this->registerRobotoFont($dompdf);
        $dompdf->loadHtml($documentHtml);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function ensureRenderMemoryLimit(): void
    {
        $currentMemoryLimit = trim((string) ini_get('memory_limit'));

        // An unlimited limit ("-1") already has all the headroom we need.
        if ($currentMemoryLimit === '-1') {
            return;
        }

        if ($this->parseMemoryLimitToBytes($currentMemoryLimit) < self::RENDER_MEMORY_LIMIT_BYTES) {
            ini_set('memory_limit', (string) self::RENDER_MEMORY_LIMIT_BYTES);
        }
    }

    private function parseMemoryLimitToBytes(string $memoryLimit): int
    {
        $numericValue = (int) $memoryLimit;
        $unitSuffix   = strtolower(substr($memoryLimit, -1));

        return match ($unitSuffix) {
            'g'     => $numericValue * 1024 * 1024 * 1024,
            'm'     => $numericValue * 1024 * 1024,
            'k'     => $numericValue * 1024,
            default => $numericValue,
        };
    }

    private function registerRobotoFont(Dompdf $dompdf): void
    {
        // The source TTF lives in app/Fonts/; dompdf parses it once and writes
        // its .ufm metrics cache into WRITEPATH/fonts (set as fontDir/fontCache
        // above). Two different directories on purpose — don't conflate them.
        // Bold is registered to the upright Regular face deliberately: the
        // variable-font weight axis is ignored by php-font-lib, and the only
        // available "Bold" file was the italic face (wrong style).
        $fontMetrics = $dompdf->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'Roboto', 'style' => 'normal', 'weight' => 'normal'],
            APPPATH . 'Fonts/Roboto-Regular.ttf'
        );
        $fontMetrics->registerFont(
            ['family' => 'Roboto', 'style' => 'normal', 'weight' => 'bold'],
            APPPATH . 'Fonts/Roboto-Regular.ttf'
        );
        // Roboto Mono drives the control number (monospace) per the design.
        $fontMetrics->registerFont(
            ['family' => 'Roboto Mono', 'style' => 'normal', 'weight' => 'normal'],
            APPPATH . 'Fonts/RobotoMono-Regular.ttf'
        );
    }

    /**
     * Generate PDF or ZIP of all QR cards for a given quantity.
     * Declared here for Task 7 signature awareness; implemented in Task 6.
     *
     * @return array{type: string, bytes: string, filename: string}
     */
    public function generate(int $startNumber, int $quantity): array
    {
        $codesPerChunk = QrBatchPlanner::PAGES_PER_CHUNK * QrBatchPlanner::CELLS_PER_PAGE;
        $chunkCount    = QrBatchPlanner::chunkCount($quantity);

        if ($chunkCount === 1) {
            return [
                'type'     => 'pdf',
                'bytes'    => $this->renderChunkPdf($startNumber, $quantity),
                'filename' => 'cswd-qr-batch.pdf',
            ];
        }

        // Multi-chunk batches are bundled with ZipArchive (ext-zip). Fail fast
        // with a clear message rather than a generic "class not found" 500.
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(
                'The PHP zip extension (ZipArchive) is required for multi-file batches. '
                . 'Install it (e.g. "sudo port install php82-zip") and restart the server.'
            );
        }

        $zipFilePath = tempnam(sys_get_temp_dir(), 'cswd-qr-zip');

        try {
            $zipArchive  = new \ZipArchive();
            $openResult  = $zipArchive->open($zipFilePath, \ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new \RuntimeException('ZipArchive::open() failed with code: ' . $openResult);
            }

            $remainingQuantity = $quantity;
            $nextStartNumber   = $startNumber;
            for ($chunkIndex = 1; $chunkIndex <= $chunkCount; $chunkIndex++) {
                $quantityInChunk = min($codesPerChunk, $remainingQuantity);
                $chunkPdfBytes   = $this->renderChunkPdf($nextStartNumber, $quantityInChunk);
                $entryName       = sprintf('batch-%03d.pdf', $chunkIndex);
                $zipArchive->addFromString($entryName, $chunkPdfBytes);

                $nextStartNumber   += $quantityInChunk;
                $remainingQuantity -= $quantityInChunk;
                unset($chunkPdfBytes); // release rendered chunk
            }
            $zipArchive->close();

            $zipBytes = file_get_contents($zipFilePath);
            if ($zipBytes === false) {
                throw new \RuntimeException('Failed to read assembled ZIP from temp file.');
            }
        } finally {
            // Always remove the temp file, even if a chunk render threw mid-way.
            if (is_file($zipFilePath)) {
                unlink($zipFilePath);
            }
        }

        return [
            'type'     => 'zip',
            'bytes'    => $zipBytes,
            'filename' => 'cswd-qr-batch.zip',
        ];
    }
}
