<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

final class QrBatchPdfGenerator
{
    public function renderChunkPdf(int $startNumber, int $quantityInChunk): string
    {
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
        // TODO: implemented in Task 6
        throw new \RuntimeException('generate() not yet implemented — see Task 6.');
    }
}
