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

        // Describe every chunk up front: its control-number range and the temp
        // PDF file a worker will write it to.
        $chunks            = [];
        $remainingQuantity = $quantity;
        $nextStartNumber   = $startNumber;
        for ($chunkIndex = 1; $chunkIndex <= $chunkCount; $chunkIndex++) {
            $quantityInChunk = min($codesPerChunk, $remainingQuantity);
            $chunks[]        = [
                'index'    => $chunkIndex,
                'start'    => $nextStartNumber,
                'quantity' => $quantityInChunk,
                'path'     => tempnam(sys_get_temp_dir(), 'cswd-qr-chunk'),
            ];
            $nextStartNumber   += $quantityInChunk;
            $remainingQuantity -= $quantityInChunk;
        }

        $zipFilePath = tempnam(sys_get_temp_dir(), 'cswd-qr-zip');

        try {
            // Render the chunks (in parallel when the platform allows it) into
            // their temp files, then bundle those files into the ZIP in order.
            $this->renderChunksToFiles($chunks);

            $zipArchive = new \ZipArchive();
            $openResult = $zipArchive->open($zipFilePath, \ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new \RuntimeException('ZipArchive::open() failed with code: ' . $openResult);
            }
            foreach ($chunks as $chunk) {
                $zipArchive->addFile($chunk['path'], sprintf('batch-%03d.pdf', $chunk['index']));
            }
            $zipArchive->close();

            $zipBytes = file_get_contents($zipFilePath);
            if ($zipBytes === false) {
                throw new \RuntimeException('Failed to read assembled ZIP from temp file.');
            }
        } finally {
            // Always remove temp files, even if a chunk render threw mid-way.
            foreach ($chunks as $chunk) {
                if (is_file($chunk['path'])) {
                    unlink($chunk['path']);
                }
            }
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

    /**
     * Render each chunk descriptor to its temp PDF file. Uses parallel worker
     * processes when proc_open is available (dompdf is the bottleneck and PHP is
     * single-threaded, so multiple processes are the only real speed-up); falls
     * back to sequential in-process rendering otherwise.
     *
     * @param list<array{index:int,start:int,quantity:int,path:string}> $chunks
     */
    private function renderChunksToFiles(array $chunks): void
    {
        if (function_exists('proc_open') && is_file(ROOTPATH . 'spark')) {
            $this->renderChunksInParallel($chunks);

            return;
        }

        foreach ($chunks as $chunk) {
            file_put_contents($chunk['path'], $this->renderChunkPdf($chunk['start'], $chunk['quantity']));
        }
    }

    /**
     * Spawn up to workerCount() "php spark qr:render-chunk" processes at a time,
     * each writing one chunk's PDF to its temp path. Blocks until all finish.
     *
     * @param list<array{index:int,start:int,quantity:int,path:string}> $chunks
     */
    private function renderChunksInParallel(array $chunks): void
    {
        $maxWorkers = min($this->workerCount(), count($chunks));
        $queue      = $chunks;
        $running    = [];

        while ($queue !== [] || $running !== []) {
            while (count($running) < $maxWorkers && $queue !== []) {
                $chunk    = array_shift($queue);
                $errPath  = $chunk['path'] . '.err';
                $command  = [
                    PHP_BINARY,
                    ROOTPATH . 'spark',
                    'qr:render-chunk',
                    (string) $chunk['start'],
                    (string) $chunk['quantity'],
                    $chunk['path'],
                ];
                // stdout discarded; stderr captured to a file so there is no pipe
                // buffer to drain (and thus no risk of a deadlocked worker).
                $descriptors = [
                    1 => ['file', '/dev/null', 'w'],
                    2 => ['file', $errPath, 'w'],
                ];
                $process = proc_open($command, $descriptors, $pipes, ROOTPATH);
                if (! is_resource($process)) {
                    throw new \RuntimeException('Failed to spawn QR render worker for chunk ' . $chunk['index'] . '.');
                }
                $running[] = ['process' => $process, 'chunk' => $chunk, 'errPath' => $errPath];
            }

            usleep(50000); // 50ms between polls

            foreach ($running as $key => $worker) {
                $status = proc_get_status($worker['process']);
                if ($status['running']) {
                    continue;
                }

                // Read the exit code from the status: proc_close() reaps it too,
                // but returns -1 once proc_get_status() has already collected it.
                $exitCode = $status['exitcode'];
                proc_close($worker['process']);
                $errText  = is_file($worker['errPath']) ? trim((string) file_get_contents($worker['errPath'])) : '';
                if (is_file($worker['errPath'])) {
                    unlink($worker['errPath']);
                }

                if ($exitCode !== 0 || ! is_file($worker['chunk']['path']) || filesize($worker['chunk']['path']) === 0) {
                    throw new \RuntimeException(
                        'QR render worker failed for chunk ' . $worker['chunk']['index']
                        . ($errText !== '' ? ': ' . $errText : ' (exit code ' . $exitCode . ').')
                    );
                }

                unset($running[$key]);
            }
        }
    }

    /**
     * Number of worker processes to run concurrently. dompdf rendering is
     * CPU-bound, so we match PHYSICAL cores — using logical (hyper-threaded)
     * cores oversubscribes the CPU and makes the whole batch slower than running
     * sequentially. Capped at 8 to bound peak memory (each dompdf worker can
     * hold a few hundred MB).
     */
    private function workerCount(): int
    {
        $detectedCores = 0;
        if (function_exists('shell_exec')) {
            // macOS reports physical cores directly; Linux uses nproc (logical,
            // an acceptable approximation when physical detection is absent).
            $output = @shell_exec('sysctl -n hw.physicalcpu 2>/dev/null');
            if ($output === null || trim((string) $output) === '') {
                $output = @shell_exec('nproc 2>/dev/null');
            }
            $detectedCores = (int) trim((string) $output);
        }

        if ($detectedCores < 1) {
            $detectedCores = 2; // conservative default when detection is unavailable
        }

        return min($detectedCores, 8);
    }
}
