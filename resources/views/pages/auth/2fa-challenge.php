<?php
$assetVersion = static function (string $path): string {
    $fullPath = dirname(__DIR__, 4) . '/public' . $path;

    return is_file($fullPath) ? $path . '?v=' . filemtime($fullPath) : $path;
};
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>2FA controle - Roostar V2</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--ink); }
    .auth-card { width: min(100%, 480px); background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { margin: 0 0 12px; font-size: 30px; letter-spacing: 0; color: #111B4E; }
    .auth-card p { margin: 0 0 22px; color: var(--muted); line-height: 1.55; }
    .auth-form { display: grid; gap: 16px; }
    .alert { margin-bottom: 18px; }
  </style>
</head>
<body>
  <main class="auth-card">
    <img src="<?= htmlspecialchars($assetVersion('/assets/images/Roostar_logo.png')) ?>" alt="Roostar" style="width:150px;height:auto;margin-bottom:24px;">
    <h1>2FA controle</h1>
    <p>Voer de 6-cijferige code uit je authenticator-app in om door te gaan.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/2fa/challenge">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
      <label class="form-group">
        <span class="form-label">Authenticatiecode</span>
        <input class="form-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
      </label>
      <button class="btn btn-dark" type="submit">Inloggen</button>
    </form>
  </main>
</body>
</html>
