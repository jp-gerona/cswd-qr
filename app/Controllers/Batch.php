<?php

namespace App\Controllers;

use App\Libraries\QrBatchPlanner;
use App\Libraries\QrBatchPdfGenerator;

class Batch extends BaseController
{
    public function generate()
    {
        $maxControlNumber = QrBatchPlanner::maxControlNumber();
        $validationRules  = [
            'startNumber' => "required|is_natural_no_zero|less_than_equal_to[{$maxControlNumber}]",
            'endNumber'   => "required|is_natural_no_zero|less_than_equal_to[{$maxControlNumber}]",
        ];

        if (! $this->validate($validationRules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => implode(' ', $this->validator->getErrors())]);
        }

        $startNumber = (int) $this->request->getPost('startNumber');
        $endNumber   = (int) $this->request->getPost('endNumber');

        if ($endNumber < $startNumber) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'The ending control number must be greater than or equal to the starting control number.']);
        }

        $quantity = $endNumber - $startNumber + 1;
        if ($quantity > QrBatchPlanner::maxQuantity()) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'The range covers ' . $quantity . ' codes, which exceeds the maximum of ' . QrBatchPlanner::maxQuantity() . ' per batch.']);
        }

        try {
            $result = (new QrBatchPdfGenerator())->generate($startNumber, $quantity);
        } catch (\Throwable $generationError) {
            log_message('error', 'QR batch generation failed: {message}', ['message' => $generationError->getMessage()]);

            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Generation failed. Please try again, or contact support if the problem persists.']);
        }

        $contentType = $result['type'] === 'zip' ? 'application/zip' : 'application/pdf';

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['bytes']);
    }
}
