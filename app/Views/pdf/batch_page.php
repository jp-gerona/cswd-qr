<?php /** @var array $cells */ ?>
<div class="page">
    <div class="grid">
        <?php foreach (array_chunk($cells, 3) as $rowCells): ?>
            <div class="row">
                <?php foreach ($rowCells as $cell): ?>
                    <div class="cell">
                        <div class="header">City Social Welfare and Development</div>
                        <div class="field">Barangay: <span class="line"></span></div>
                        <div class="field">Name: <span class="line"></span></div>
                        <div class="qr"><img src="<?= esc($cell['qrDataUri'], 'attr') ?>" alt="QR"></div>
                        <div class="control-label">Control No.:</div>
                        <div class="control-number"><?= esc($cell['controlNumber']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>