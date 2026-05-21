<?php

declare(strict_types=1);

namespace Roostar\Modules\Setup;

use PDO;
use Roostar\Core\Database\Connection;
use Roostar\Core\Database\MigrationRunner;
use Roostar\Core\Http\Request;
use Roostar\Core\Http\Response;

final class DatabaseSetupController
{
    public function show(Request $request): Response
    {
        return Response::html($this->render([
            'values' => $this->currentValues(),
            'token' => $request->string('token'),
            'messages' => [],
        ]));
    }

    public function store(Request $request): Response
    {
        $messages = [];
        $values = [
            'APP_ENV' => $request->string('APP_ENV', 'production'),
            'APP_DEBUG' => $request->string('APP_DEBUG', 'false'),
            'APP_URL' => $request->string('APP_URL'),
            'DB_HOST' => $request->string('DB_HOST', 'localhost'),
            'DB_PORT' => $request->string('DB_PORT', '3306'),
            'DB_NAME' => $request->string('DB_NAME'),
            'DB_USER' => $request->string('DB_USER'),
            'DB_PASS' => (string) $request->input('DB_PASS', ''),
            'ENCRYPTION_KEY' => $request->string('ENCRYPTION_KEY') ?: $this->generateKey(),
            'SETUP_TOKEN' => $request->string('SETUP_TOKEN') ?: $this->configuredToken(),
        ];

        try {
            $this->assertSetupAllowed($request);
            $this->validate($values);
            $this->writeEnv($values);
            $this->configureConnection($values);
            $this->createDatabase($values);
            $ran = $this->runMigrations();
            $messages[] = ['type' => 'success', 'text' => 'Database setup is gelukt.'];
            $messages[] = ['type' => 'info', 'text' => $ran === [] ? 'Geen nieuwe migraties.' : 'Migraties uitgevoerd: ' . implode(', ', $ran)];
        } catch (\Throwable $error) {
            $messages[] = ['type' => 'error', 'text' => $error->getMessage()];
        }

        return Response::html($this->render([
            'values' => $values,
            'token' => $request->string('token') ?: $values['SETUP_TOKEN'],
            'messages' => $messages,
        ]));
    }

    public function isSetupRequest(Request $request): bool
    {
        return $request->path === '/setup/database';
    }

    public function setupTokenRequired(): bool
    {
        return $this->configuredToken() !== '';
    }

    public function tokenIsValid(Request $request): bool
    {
        $token = $this->configuredToken();

        return $token === '' || hash_equals($token, $request->string('token'));
    }

    private function render(array $data): string
    {
        $values = $data['values'];
        $messages = $data['messages'];
        $token = (string) ($data['token'] ?? '');
        $action = '/setup/database' . ($token !== '' ? '?token=' . rawurlencode($token) : '');
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Roostar database setup</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;650;750&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { min-height: 100vh; display: grid; place-items: center; padding: 32px; }
    .setup-shell { width: min(920px, 100%); }
    .setup-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .14); overflow: hidden; }
    .setup-head { padding: 24px 28px; border-bottom: 1px solid var(--line); }
    .setup-body { padding: 24px 28px; display: grid; gap: 18px; }
    .setup-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .setup-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 6px; }
    .setup-message { padding: 12px 14px; border-radius: 8px; border: 1px solid var(--line); font-size: 13px; }
    .setup-message.success { background: var(--green-soft); color: var(--green); border-color: color-mix(in srgb, var(--green) 30%, var(--line)); }
    .setup-message.error { background: var(--coral-soft); color: var(--coral); border-color: color-mix(in srgb, var(--coral) 30%, var(--line)); }
    .setup-message.info { background: var(--blue-soft); color: var(--blue); border-color: color-mix(in srgb, var(--blue) 30%, var(--line)); }
    @media (max-width: 720px) { .setup-grid { grid-template-columns: 1fr; } body { padding: 16px; } }
  </style>
</head>
<body>
  <main class="setup-shell">
    <section class="setup-card">
      <div class="setup-head">
        <div class="eyebrow">Roostar setup</div>
        <h1 class="page-title">Database instellen</h1>
        <p class="muted">Vul de databasegegevens in. Roostar schrijft de `.env`, maakt de database aan en draait de migraties.</p>
      </div>
      <form method="post" action="<?= $escape($action) ?>">
        <div class="setup-body">
          <?php foreach ($messages as $message): ?>
            <div class="setup-message <?= $escape($message['type']) ?>"><?= $escape($message['text']) ?></div>
          <?php endforeach; ?>
          <div class="setup-grid">
            <div class="form-group"><label class="form-label">App URL</label><input class="form-input" name="APP_URL" value="<?= $escape($values['APP_URL'] ?? '') ?>" placeholder="https://app.roostar.nl"></div>
            <div class="form-group"><label class="form-label">Omgeving</label><select class="form-select" name="APP_ENV"><option value="production" <?= ($values['APP_ENV'] ?? '') === 'production' ? 'selected' : '' ?>>production</option><option value="local" <?= ($values['APP_ENV'] ?? '') === 'local' ? 'selected' : '' ?>>local</option></select></div>
            <div class="form-group"><label class="form-label">DB host</label><input class="form-input" name="DB_HOST" value="<?= $escape($values['DB_HOST'] ?? 'localhost') ?>" required></div>
            <div class="form-group"><label class="form-label">DB poort</label><input class="form-input" name="DB_PORT" value="<?= $escape($values['DB_PORT'] ?? '3306') ?>" required></div>
            <div class="form-group"><label class="form-label">Database naam</label><input class="form-input" name="DB_NAME" value="<?= $escape($values['DB_NAME'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label">Database gebruiker</label><input class="form-input" name="DB_USER" value="<?= $escape($values['DB_USER'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label">Database wachtwoord</label><input class="form-input" type="password" name="DB_PASS" value="<?= $escape($values['DB_PASS'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Setup token</label><input class="form-input" name="SETUP_TOKEN" value="<?= $escape($values['SETUP_TOKEN'] ?? '') ?>" placeholder="Laat leeg om geen token te gebruiken"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Encryption key</label>
            <input class="form-input" name="ENCRYPTION_KEY" value="<?= $escape($values['ENCRYPTION_KEY'] ?? '') ?>" placeholder="Wordt automatisch gegenereerd">
          </div>
          <input type="hidden" name="APP_DEBUG" value="false">
          <div class="setup-actions">
            <button class="btn btn-dark" type="submit">Opslaan en migreren</button>
          </div>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }

    private function assertSetupAllowed(Request $request): void
    {
        if (!$this->tokenIsValid($request)) {
            throw new \RuntimeException('Ongeldige setup token.');
        }
    }

    private function validate(array $values): void
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'ENCRYPTION_KEY'] as $key) {
            if ((string) ($values[$key] ?? '') === '') {
                throw new \InvalidArgumentException($key . ' is verplicht.');
            }
        }
    }

    private function configureConnection(array $values): void
    {
        Connection::configure([
            'host' => $values['DB_HOST'],
            'port' => $values['DB_PORT'],
            'database' => $values['DB_NAME'],
            'username' => $values['DB_USER'],
            'password' => $values['DB_PASS'],
            'charset' => 'utf8mb4',
        ]);
    }

    private function createDatabase(array $values): void
    {
        $server = Connection::server();
        $database = str_replace('`', '``', (string) $values['DB_NAME']);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function runMigrations(): array
    {
        $runner = new MigrationRunner(Connection::get(), dirname(__DIR__, 3) . '/database/migrations');

        return $runner->run();
    }

    private function writeEnv(array $values): void
    {
        $path = dirname(__DIR__, 3) . '/.env';
        $content = '';
        foreach ($values as $key => $value) {
            $content .= $key . '=' . $this->envValue((string) $value) . PHP_EOL;
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Kon .env niet schrijven. Controleer schrijfrechten.');
        }
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#="\']/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }

    private function currentValues(): array
    {
        return [
            'APP_ENV' => $_ENV['APP_ENV'] ?? 'production',
            'APP_DEBUG' => $_ENV['APP_DEBUG'] ?? 'false',
            'APP_URL' => $_ENV['APP_URL'] ?? '',
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'localhost',
            'DB_PORT' => $_ENV['DB_PORT'] ?? '3306',
            'DB_NAME' => $_ENV['DB_NAME'] ?? '',
            'DB_USER' => $_ENV['DB_USER'] ?? '',
            'DB_PASS' => $_ENV['DB_PASS'] ?? '',
            'ENCRYPTION_KEY' => $_ENV['ENCRYPTION_KEY'] ?? '',
            'SETUP_TOKEN' => $_ENV['SETUP_TOKEN'] ?? '',
        ];
    }

    private function configuredToken(): string
    {
        return (string) ($_ENV['SETUP_TOKEN'] ?? '');
    }

    private function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
