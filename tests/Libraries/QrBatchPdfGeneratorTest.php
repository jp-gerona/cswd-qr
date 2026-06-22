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

    public function testTwelveCardsFitOnExactlyOnePage(): void
    {
        $pdfBytes = (new QrBatchPdfGenerator())->renderChunkPdf(1, 12);

        $this->assertSame(1, $this->countPdfPages($pdfBytes), '12 cards (a full 3x4 grid) must fit on exactly one page.');
    }

    public function testThirteenthCardOverflowsToASecondPage(): void
    {
        $pdfBytes = (new QrBatchPdfGenerator())->renderChunkPdf(1, 13);

        $this->assertSame(2, $this->countPdfPages($pdfBytes), '13 cards must spill onto a second page.');
    }

    private function countPdfPages(string $pdfBytes): int
    {
        // Each rendered page is a "/Type /Page" object (not the "/Pages" tree root).
        return preg_match_all('#/Type\s*/Page\b(?!s)#', $pdfBytes);
    }
}
