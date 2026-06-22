<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class BatchTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testRejectsMissingRange(): void
    {
        $result = $this->post('generate', []);
        $result->assertStatus(400);
    }

    public function testRejectsControlNumberAboveSixDigits(): void
    {
        $result = $this->post('generate', ['startNumber' => 1, 'endNumber' => 1000000]);
        $result->assertStatus(400);
    }

    public function testRejectsEndBeforeStart(): void
    {
        $result = $this->post('generate', ['startNumber' => 100, 'endNumber' => 50]);
        $result->assertStatus(400);
    }

    public function testRejectsRangeAboveMax(): void
    {
        // One past the configured maxQuantity cap.
        $endNumber = config('QrBatchSettings')->maxQuantity + 1;
        $result    = $this->post('generate', ['startNumber' => 1, 'endNumber' => $endNumber]);
        $result->assertStatus(400);
    }

    public function testValidRangeReturnsPdfDownload(): void
    {
        $result = $this->post('generate', ['startNumber' => 1, 'endNumber' => 12]);
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/pdf');
    }
}
