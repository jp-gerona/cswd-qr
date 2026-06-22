<?php

namespace Tests\Libraries;

use App\Libraries\QrBatchPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class QrBatchPdfGeneratorTest extends CIUnitTestCase
{
    public function testRenderChunkPdfReturnsPdfBytes(): void
    {
        $pdfBytes = (new QrBatchPdfGenerator())->renderChunkPdf(1, 12);

        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertStringContainsString('%%EOF', $pdfBytes);
    }
}
