<?php
$assetVersion = static function (string $path): string {
    $fullPath = dirname(__DIR__, 4) . '/public' . $path;

    return is_file($fullPath) ? $path . '?v=' . filemtime($fullPath) : $path;
};
$otpauth = (string) ($otpauthUri ?? '');
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>2FA instellen - Roostar V2</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--ink); }
    .auth-card { width: min(100%, 520px); background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { margin: 0 0 12px; font-size: 30px; letter-spacing: 0; color: #111B4E; }
    .auth-card p { margin: 0 0 22px; color: var(--muted); line-height: 1.55; }
    .qr-box { display: grid; place-items: center; margin: 0 0 22px; padding: 18px; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
    .qr-box svg { width: min(100%, 260px); height: auto; display: block; }
    .secret-box { display: grid; gap: 8px; margin: 0 0 22px; padding: 16px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); }
    .secret-box strong { font-family: var(--mono); font-size: 18px; word-break: break-all; }
    .auth-form { display: grid; gap: 16px; }
    .alert { margin-bottom: 18px; }
  </style>
</head>
<body>
  <main class="auth-card">
    <img src="<?= htmlspecialchars($assetVersion('/assets/images/Roostar_logo.png')) ?>" alt="Roostar" style="width:150px;height:auto;margin-bottom:24px;">
    <h1>2FA instellen</h1>
    <p>Scan de QR-code met je authenticator-app. Lukt scannen niet, voer dan de sleutel handmatig in. Bevestig daarna met de 6-cijferige code.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <?php if (!empty($qrCodeSvg)): ?>
      <div class="qr-box">
        <?= $qrCodeSvg ?>
      </div>
    <?php endif; ?>

    <div class="secret-box">
      <span class="muted text-sm">Sleutel</span>
      <strong><?= htmlspecialchars((string) $secret) ?></strong>
      <?php if ($otpauth !== ''): ?>
        <a class="text-sm" href="<?= htmlspecialchars($otpauth) ?>">Open in authenticator-app</a>
      <?php endif; ?>
    </div>

    <form class="auth-form" method="post" action="/2fa/setup">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
      <label class="form-group">
        <span class="form-label">Code uit app</span>
        <input class="form-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
      </label>
      <button class="btn btn-dark" type="submit">2FA activeren</button>
    </form>
  </main>
</body>
</html>
