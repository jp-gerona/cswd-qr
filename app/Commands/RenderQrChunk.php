<?php

namespace App\Commands;

use App\Libraries\QrBatchPdfGenerator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Renders a single chunk of QR cards to a PDF file. Invoked as a worker process
 * by QrBatchPdfGenerator during parallel batch generation — not meant to be run
 * by hand in normal use.
 */
class RenderQrChunk extends BaseCommand
{
    protected $group       = 'QR';
    protected $name        = 'qr:render-chunk';
    protected $description  = 'Render one chunk of QR cards to a PDF file (parallel batch worker).';
    protected $usage        = 'qr:render-chunk <startNumber> <quantity> <outputPath>';
    protected $arguments    = [
        'startNumber' => 'First control number in the chunk.',
        'quantity'    => 'How many codes the chunk contains.',
        'outputPath'  => 'File path the rendered PDF is written to.',
    ];

    public function run(array $params): int
    {
        if (count($params) < 3) {
            CLI::error('Usage: ' . $this->usage);

            return EXIT_ERROR;
        }

        [$startNumber, $quantity, $outputPath] = $params;

        try {
            $pdfBytes = (new QrBatchPdfGenerator())->renderChunkPdf((int) $startNumber, (int) $quantity);

            if (file_put_contents($outputPath, $pdfBytes) === false) {
                CLI::error('Failed to write chunk PDF to ' . $outputPath);

                return EXIT_ERROR;
            }
        } catch (\Throwable $error) {
            CLI::error($error->getMessage());

            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
