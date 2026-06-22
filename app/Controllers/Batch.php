<?php

namespace App\Controllers;

use App\Libraries\QrBatchPlanner;
use App\Libraries\QrBatchPdfGenerator;

class Batch extends BaseController
{
    public function generate()
    {
        $validationRules = [
            'quantity' => 'required|is_natural_no_zero|less_than_equal_to[' . QrBatchPlanner::MAX_QUANTITY . ']',
        ];

        if (! $this->validate($validationRules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => implode(' ', $this->validator->getErrors())]);
        }

        $quantity = (int) $this->request->getPost('quantity');
        $result   = (new QrBatchPdfGenerator())->generate($quantity);

        $contentType = $result['type'] === 'zip' ? 'application/zip' : 'application/pdf';

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $contentType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setBody($result['bytes']);
    }
}
