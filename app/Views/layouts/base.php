<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $this->renderSection("title") ?></title>
    <meta name="description" content="<?= $this->renderSection(
        "description",
    ) ?>" />
    <link rel="shortcut icon" type="image/png" href="/favicon.ico">
    <link href="/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  </head>
  <body>
    <main>
      <?= $this->renderSection("content") ?>
    </main>

    <script src="/jquery.min.js"></script>
    <script src="/bootstrap/js/bootstrap.bundle.min.js"></script>

    <?= $this->renderSection("scripts") ?>
  </body>
</html>
