<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Master tunables for the QR batch generator.
 *
 * Every knob the batch flow uses — QR payload, output filenames, grid layout,
 * chunking, control-number format and batch quantity — lives here so there are
 * no magic numbers/strings scattered across the libraries. Any value can be
 * overridden per environment from .env (prefix "qrbatchsettings."), e.g.:
 *
 *   qrbatchsettings.qrUrlPrefix = "https://example.gov.ph/qr/"
 *   qrbatchsettings.maxQuantity = 20000
 */
class QrBatchSettings extends BaseConfig
{
    /**
     * Text prepended to each control number to form the QR payload.
     * Empty string encodes the bare control number ("000001"). A value like
     * "https://example.gov.ph/qr/" encodes "https://example.gov.ph/qr/000001".
     * Include a trailing slash yourself if the URL needs one — it is
     * concatenated verbatim.
     */
    public string $qrUrlPrefix = "";

    /**
     * QR cards per printed page. The PDF page template is a fixed 3x4 grid,
     * so changing this without updating app/Views/pdf/* will misalign cards.
     */
    public int $cellsPerPage = 12;

    /**
     * Pages per chunk. A batch larger than one chunk is rendered as several
     * PDFs (in parallel worker processes) and bundled into a ZIP.
     */
    public int $pagesPerChunk = 50;

    /**
     * Zero-padded width of a control number ("000001" = width 6). Also caps the
     * largest control number to 10^width - 1 (999999 for width 6).
     */
    public int $controlNumberWidth = 6;

    /**
     * Hard upper bound on codes generated in a single batch.
     */
    public int $maxQuantity = 25000;

    /**
     * Filename for a single-chunk batch (served as application/pdf).
     */
    public string $singlePdfFileName = "cswd-qr-batch.pdf";

    /**
     * sprintf pattern for a multi-chunk batch bundle (served as application/zip).
     * Two %s arguments: the batch's first and last control numbers, each
     * zero-padded to controlNumberWidth (e.g. "cswd-qr-batch-000500-002500.zip").
     */
    public string $zipFileNamePattern = "cswd-qr-batch-%s-%s.zip";

    /**
     * sprintf pattern naming each chunk PDF inside the ZIP. Two %s arguments:
     * the chunk's first and last control numbers, each zero-padded to
     * controlNumberWidth (e.g. "batch-000500-001100.pdf"). Padding keeps the
     * archive entries in numeric order when sorted lexically.
     */
    public string $chunkPdfNamePattern = "batch-%s-%s.pdf";
}
