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

    public function renderChunkPdf(int $startNumber, int $quantityInChunk): string
    {
        $this->ensureRenderMemoryLimit();

        $qrImageGenerator = new QrImageGenerator();
        $controlNumbers   = QrBatchPlanner::controlNumbers($quantityInChunk, $startNumber);

        $pagesHtml = '';
        foreach (array_chunk($controlNumbers, QrBatchPlanner::CELLS_PER_PAGE) as $pageControlNumbers) {
            $cells = [];
            foreach ($pageControlNumbers as $controlNumber) {
                $cells[] = [
                    'controlNumber' => $controlNumber,
                    'qrDataUri'     => $qrImageGenerator->svgDataUri($controlNumber),
                ];
            }
            $pagesHtml .= view('pdf/batch_page', ['cells' => $cells]);
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
        $fontMetrics = $dompdf->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'Roboto', 'style' => 'normal', 'weight' => 'normal'],
            APPPATH . 'Fonts/Roboto-Regular.ttf'
        );
        $fontMetrics->registerFont(
            ['family' => 'Roboto', 'style' => 'normal', 'weight' => 'bold'],
            APPPATH . 'Fonts/Roboto-Regular.ttf'
        );
    }

    /**
     * Generate PDF or ZIP of all QR cards for a given quantity.
     * Declared here for Task 7 signature awareness; implemented in Task 6.
     *
     * @return array{type: string, bytes: string, filename: string}
     */
    public function generate(int $quantity): array
    {
        $codesPerChunk = QrBatchPlanner::PAGES_PER_CHUNK * QrBatchPlanner::CELLS_PER_PAGE;
        $chunkCount    = QrBatchPlanner::chunkCount($quantity);

        if ($chunkCount === 1) {
            return [
                'type'     => 'pdf',
                'bytes'    => $this->renderChunkPdf(1, $quantity),
                'filename' => 'cswd-qr-batch.pdf',
            ];
        }

        $zipFilePath = tempnam(sys_get_temp_dir(), 'cswd-qr-zip');
        $zipArchive  = new \ZipArchive();
        $zipArchive->open($zipFilePath, \ZipArchive::OVERWRITE);

        $remainingQuantity = $quantity;
        $nextStartNumber   = 1;
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
        unlink($zipFilePath);

        return [
            'type'     => 'zip',
            'bytes'    => $zipBytes,
            'filename' => 'cswd-qr-batch.zip',
        ];
    }
}
