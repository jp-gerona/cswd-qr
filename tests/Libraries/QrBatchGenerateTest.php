<?php

namespace Tests\Libraries;

use App\Libraries\QrBatchPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class QrBatchGenerateTest extends CIUnitTestCase
{
    public function testSingleChunkReturnsPdf(): void
    {
        $result = (new QrBatchPdfGenerator())->generate(12);

        $this->assertSame('pdf', $result['type']);
        $this->assertSame('cswd-qr-batch.pdf', $result['filename']);
        $this->assertStringStartsWith('%PDF-', $result['bytes']);
    }

    public function testMultiChunkReturnsZip(): void
    {
        // 601 codes => 51 pages => 2 chunks.
        $result = (new QrBatchPdfGenerator())->generate(601);

        $this->assertSame('zip', $result['type']);
        $this->assertSame('cswd-qr-batch.zip', $result['filename']);
        // ZIP local file header magic.
        $this->assertSame("PK\x03\x04", substr($result['bytes'], 0, 4));
    }
}
