<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inloggen - Roostar V2</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body {
      min-height: 100vh;
      margin: 0;
      background: #fff;
      color: var(--ink);
    }

    .login-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: minmax(420px, 0.98fr) minmax(420px, 1fr);
      background: #fff;
    }

    .login-visual {
      position: relative;
      min-height: 100vh;
      overflow: hidden;
      background:
        linear-gradient(180deg, rgba(10, 24, 44, 0.04) 0%, rgba(10, 24, 44, 0.22) 48%, rgba(7, 17, 31, 0.72) 100%),
        url('/assets/images/login-visual.png') center / cover no-repeat;
    }

    .login-visual::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 35% 62%, rgba(255,255,255,0.16), transparent 30%);
      pointer-events: none;
    }

    .login-brand {
      position: absolute;
      top: 42px;
      left: 46px;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      min-height: 62px;
      padding: 12px 18px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.56);
      box-shadow: 0 18px 50px rgba(15, 23, 42, 0.14);
      backdrop-filter: blur(10px);
    }

    .login-brand img {
      width: 172px;
      max-width: 42vw;
      height: auto;
      display: block;
    }

    .login-visual-copy {
      position: absolute;
      left: 52px;
      right: 52px;
      bottom: 54px;
      z-index: 1;
      color: #fff;
    }

    .login-visual-copy h1 {
      margin: 0 0 12px;
      max-width: 560px;
      font-size: clamp(36px, 4.4vw, 66px);
      line-height: 1.02;
      letter-spacing: 0;
      font-weight: 700;
    }

    .login-visual-copy p {
      margin: 0;
      max-width: 430px;
      font-size: 18px;
      line-height: 1.45;
      color: rgba(255, 255, 255, 0.88);
      font-weight: 500;
    }

    .login-panel {
      min-height: 100vh;
      display: grid;
      align-items: center;
      padding: 56px clamp(32px, 8vw, 132px);
      background: #fff;
    }

    .login-form-wrap {
      width: 100%;
      max-width: 560px;
      margin: 0 auto;
    }

    .login-kicker {
      margin: 0 0 16px;
      color: var(--blue);
      font-size: 13px;
      font-weight: 650;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .login-title {
      margin: 0 0 12px;
      font-size: clamp(34px, 4vw, 52px);
      line-height: 1.05;
      letter-spacing: 0;
      color: #111B4E;
      font-weight: 700;
    }

    .login-subtitle {
      margin: 0 0 42px;
      color: var(--muted);
      font-size: 17px;
      line-height: 1.5;
    }

    .login-form {
      display: grid;
      gap: 22px;
    }

    .login-field {
      display: grid;
      gap: 9px;
    }

    .login-field .form-label {
      color: #111B4E;
      font-size: 14px;
      font-weight: 600;
    }

    .login-input {
      width: 100%;
      min-height: 64px;
      padding: 0 20px;
      border: 1px solid #111B4E;
      border-radius: 10px;
      background: #fff;
      color: #111B4E;
      font: inherit;
      font-size: 17px;
      transition: border-color .15s ease, box-shadow .15s ease;
    }

    .login-input:focus {
      outline: none;
      border-color: var(--blue);
      box-shadow: 0 0 0 4px rgba(47, 111, 237, 0.12);
    }

    .password-row {
      position: relative;
    }

    .password-row .login-input {
      padding-right: 54px;
    }

    .password-toggle {
      position: absolute;
      top: 50%;
      right: 14px;
      transform: translateY(-50%);
      width: 34px;
      height: 34px;
      border: 0;
      border-radius: 50%;
      background: transparent;
      color: var(--muted);
      display: grid;
      place-items: center;
      cursor: pointer;
    }

    .password-toggle:hover {
      background: var(--bg);
      color: var(--blue);
    }

    .password-toggle svg {
      width: 20px;
      height: 20px;
    }

    .login-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      color: var(--muted);
      font-size: 14px;
    }

    .remember {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      color: #111B4E;
      user-select: none;
    }

    .remember input {
      width: 16px;
      height: 16px;
      accent-color: var(--blue);
    }

    .login-help {
      color: var(--muted);
      text-decoration: none;
    }

    .login-help:hover {
      color: var(--blue);
    }

    .login-submit {
      width: 100%;
      min-height: 64px;
      margin-top: 12px;
      justify-content: center;
      border-radius: 10px;
      background: #1F2937;
      border-color: #1F2937;
      font-size: 17px;
      font-weight: 700;
    }

    .login-submit:hover {
      background: #111827;
      border-color: #111827;
    }

    .login-alert {
      margin-bottom: 24px;
    }

    @media (max-width: 960px) {
      .login-shell {
        grid-template-columns: 1fr;
      }

      .login-visual {
        min-height: 38vh;
      }

      .login-panel {
        min-height: auto;
        padding: 36px 22px 44px;
      }

      .login-brand {
        top: 24px;
        left: 24px;
      }

      .login-visual-copy {
        left: 28px;
        right: 28px;
        bottom: 28px;
      }

      .login-visual-copy h1 {
        font-size: 34px;
      }

      .login-visual-copy p {
        font-size: 15px;
      }
    }

    @media (max-width: 560px) {
      .login-visual {
        min-height: 32vh;
      }

      .login-brand {
        min-height: 52px;
        padding: 10px 14px;
      }

      .login-brand img {
        width: 136px;
      }

      .login-visual-copy h1 {
        font-size: 28px;
      }

      .login-visual-copy p {
        display: none;
      }

      .login-title {
        font-size: 32px;
      }

      .login-subtitle {
        margin-bottom: 30px;
        font-size: 15px;
      }

      .login-meta {
        align-items: flex-start;
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="login-visual" aria-label="Roostar planning">
      <a class="login-brand" href="/login" aria-label="Roostar">
        <img src="/assets/images/Roostar_logo.png" alt="Roostar">
      </a>

      <div class="login-visual-copy">
        <h1>Plan de schooldag met rust</h1>
        <p>Roosters, vervanging en schoolbeheer in een veilige, overzichtelijke omgeving.</p>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-form-wrap">
        <p class="login-kicker">Roostar V2</p>
        <h1 class="login-title">Welkom terug</h1>
        <p class="login-subtitle">Log in met je gebruikersnaam en wachtwoord.</p>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error login-alert"><?= htmlspecialchars((string) $error) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="/login">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">

          <label class="login-field">
            <span class="form-label">Gebruikersnaam of e-mailadres</span>
            <input class="login-input" type="email" name="email" autocomplete="username" required>
          </label>

          <label class="login-field">
            <span class="form-label">Wachtwoord</span>
            <span class="password-row">
              <input id="password" class="login-input" type="password" name="password" autocomplete="current-password" required>
              <button class="password-toggle" type="button" aria-label="Toon wachtwoord" onclick="togglePassword()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </span>
          </label>

          <div class="login-meta">
            <label class="remember">
              <input type="checkbox" name="remember" value="1">
              Onthoud mij
            </label>
            <a class="login-help" href="/login">Wachtwoord vergeten?</a>
          </div>

          <button class="btn btn-dark login-submit" type="submit">Inloggen</button>
        </form>
      </div>
    </section>
  </main>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
