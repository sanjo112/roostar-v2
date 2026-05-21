<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'Roostar') ?> - Roostar V2</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="main">
      <?php require __DIR__ . '/../partials/topbar.php'; ?>
      <?= $content ?? '' ?>
    </main>
  </div>

  <script id="roostar-notifications" type="application/json"><?= json_encode($notifications ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
  <script id="roostar-csrf" type="application/json"><?= json_encode(['token' => \Roostar\Core\Security\Csrf::token()], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
  <script src="/assets/js/app.js" defer></script>
</body>
</html>
