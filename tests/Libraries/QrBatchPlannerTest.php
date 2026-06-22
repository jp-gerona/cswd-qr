<?php

namespace Tests\Libraries;

use App\Libraries\QrBatchPlanner;
use CodeIgniter\Test\CIUnitTestCase;

final class QrBatchPlannerTest extends CIUnitTestCase
{
    public function testFormatControlNumberPadsToSixDigits(): void
    {
        $this->assertSame('000001', QrBatchPlanner::formatControlNumber(1));
        $this->assertSame('012345', QrBatchPlanner::formatControlNumber(12345));
    }

    public function testControlNumbersAreSequentialFromStart(): void
    {
        $this->assertSame(['000001', '000002', '000003'], QrBatchPlanner::controlNumbers(3));
        $this->assertSame(['000010', '000011'], QrBatchPlanner::controlNumbers(2, 10));
    }

    public function testPageCountRoundsUpAtTwelvePerPage(): void
    {
        $this->assertSame(1, QrBatchPlanner::pageCount(12));
        $this->assertSame(2, QrBatchPlanner::pageCount(13));
    }

    public function testChunkCountRoundsUpAtFiftyPagesPerChunk(): void
    {
        $this->assertSame(1, QrBatchPlanner::chunkCount(600));   // 50 pages
        $this->assertSame(2, QrBatchPlanner::chunkCount(601));   // 51 pages
    }
}
