<?= $this->extend("layouts/base") ?>

<?= $this->section("title") ?>
    CSWD QR Code Generator
<?= $this->endSection() ?>

<?= $this->section("description") ?>
    This serves as an application for generating QR codes.
<?= $this->endSection() ?>

<?= $this->section("content") ?>
    <div class="container py-5" style="max-width: 480px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5 mb-3">CSWD QR Code Generator</h1>
                <div id="generateError" class="alert alert-danger d-none" role="alert"></div>
                <form id="generateForm">
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label for="startNumber" class="form-label">Starting control no.</label>
                            <input type="number" class="form-control" id="startNumber" name="startNumber"
                                   min="1" max="999999" value="1" required>
                        </div>
                        <div class="col-6">
                            <label for="endNumber" class="form-label">Ending control no.</label>
                            <input type="number" class="form-control" id="endNumber" name="endNumber"
                                   min="1" max="999999" value="12" required>
                        </div>
                    </div>
                    <div class="form-text mb-3">Up to 10,000 QR codes per batch. 12 QR codes per sheet.</div>
                    <button type="submit" class="btn btn-primary" id="generateButton">Generate</button>
                </form>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section("scripts") ?>
    <script>
        $('#generateForm').on('submit', function (event) {
            event.preventDefault();
            var $button = $('#generateButton');
            var $error = $('#generateError').addClass('d-none').text('');
            $button.prop('disabled', true).text('Generating…');

            $.ajax({
                url: '<?= site_url("generate") ?>',
                method: 'POST',
                data: { startNumber: $('#startNumber').val(), endNumber: $('#endNumber').val() },
                xhrFields: { responseType: 'blob' }
            }).done(function (data, status, xhr) {
                var disposition = xhr.getResponseHeader('Content-Disposition') || '';
                var match = disposition.match(/filename="(.+?)"/);
                var filename = match ? match[1] : 'cswd-qr-batch';
                var url = window.URL.createObjectURL(data);
                var link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            }).fail(function (xhr) {
                var message = 'Generation failed.';
                try { message = JSON.parse(xhr.responseText).error || message; } catch (e) {}
                $error.removeClass('d-none').text(message);
            }).always(function () {
                $button.prop('disabled', false).text('Generate');
            });
        });
    </script>
<?= $this->endSection() ?>
