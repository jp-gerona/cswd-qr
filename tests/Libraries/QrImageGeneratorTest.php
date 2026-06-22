<?php

namespace Tests\Libraries;

use App\Libraries\QrImageGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class QrImageGeneratorTest extends CIUnitTestCase
{
    public function testDataUriIsBase64Png(): void
    {
        $dataUri = (new QrImageGenerator())->dataUri('000001');

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $base64Payload = substr($dataUri, strlen('data:image/png;base64,'));
        $decodedBytes  = base64_decode($base64Payload, true);

        $this->assertNotFalse($decodedBytes);
        // PNG magic number.
        $this->assertSame("\x89PNG", substr($decodedBytes, 0, 4));
    }
}
