<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class BatchTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testRejectsMissingQuantity(): void
    {
        $result = $this->post('generate', []);
        $result->assertStatus(400);
    }

    public function testRejectsQuantityAboveMax(): void
    {
        $result = $this->post('generate', ['quantity' => 10001]);
        $result->assertStatus(400);
    }

    public function testValidQuantityReturnsPdfDownload(): void
    {
        $result = $this->post('generate', ['quantity' => 12]);
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/pdf');
    }
}
