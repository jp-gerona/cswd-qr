<?php
/** @var array $cells */
/** @var bool $isFirstPage */
?>
<div class="page<?= ($isFirstPage ?? true) ? "" : " page-break" ?>">
    <div class="grid">
        <?php foreach (array_chunk($cells, 3) as $rowCells): ?>
            <div class="row">
                <?php foreach ($rowCells as $cell): ?>
                    <div class="cell">
                        <div class="header">CITY OF BIÑAN</div>
                        <div class="field-row">
                            <span class="field-label">Barangay:</span>
                            <span class="field-line"></span>
                        </div>
                        <div class="field-row">
                            <span class="field-label">Name:</span>
                            <span class="field-line"></span>
                        </div>
                        <div class="qr"><img src="<?= esc($cell["qrDataUri"], "attr") ?>" alt="QR"></div>
                        <div class="control-label">Control No.:</div>
                        <div class="control-number"><?= esc($cell["controlNumber"]) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
