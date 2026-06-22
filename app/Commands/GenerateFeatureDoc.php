<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Dompdf\Dompdf;
use Dompdf\Options;

class GenerateFeatureDoc extends BaseCommand
{
    protected $group       = 'Docs';
    protected $name        = 'docs:feature';
    protected $description = 'Render the feature-overview documentation PDF into docs/.';

    public function run(array $params): void
    {
        $documentHtml = view('pdf/feature_overview');

        $options = new Options();
        $options->set('fontDir', WRITEPATH . 'fonts');
        $options->set('fontCache', WRITEPATH . 'fonts');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($documentHtml);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $outputPath = ROOTPATH . 'docs/cswd-qr-feature-overview.pdf';
        file_put_contents($outputPath, $dompdf->output());

        CLI::write('Wrote ' . $outputPath, 'green');
    }
}
