<?php
$assetVersion = static function (string $path): string {
    $fullPath = dirname(__DIR__, 4) . '/public' . $path;

    return is_file($fullPath) ? $path . '?v=' . filemtime($fullPath) : $path;
};
$loginVisualPath = is_string($loginVisualPath ?? null) ? $loginVisualPath : null;
$schoolLogoPath = is_string($schoolLogoPath ?? null) ? $schoolLogoPath : null;
?>

<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Instellingen</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
        Schoolinstellingen voor de omgeving.
      </div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Schoollogo</div>
      <div class="muted text-sm">Dit logo verschijnt rechtsboven in de balk, direct links van de helpknop.</div>
    </div>
  </div>

  <?php if (!($canManageLoginVisual ?? false)): ?>
    <div class="empty">
      <div class="title">Geen toegang</div>
      <p class="muted">Je hebt schoolbeheerrechten nodig om het schoollogo aan te passen.</p>
    </div>
  <?php else: ?>
    <div class="profile-grid">
      <div class="profile-field">
        <span>Huidig logo</span>
        <strong><?= $schoolLogoPath ? 'Aangepast' : 'Geen schoollogo' ?></strong>
      </div>
      <?php if ($schoolLogoPath): ?>
        <div class="profile-field">
          <span>Voorbeeld</span>
          <img src="<?= htmlspecialchars($assetVersion($schoolLogoPath)) ?>" alt="" style="width:var(--icon-button-size);height:var(--icon-button-size);object-fit:contain;border-radius:7px;border:1px solid var(--line);padding:3px;">
        </div>
      <?php endif; ?>
      <div class="profile-field">
        <span>Nieuw logo</span>
        <form action="/settings/school-logo" method="post" enctype="multipart/form-data" class="login-visual-settings-form">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <input class="form-input" type="file" name="school_logo" accept="image/png,image/jpeg,image/webp" required>
          <button class="btn btn-primary" type="submit">Uploaden</button>
        </form>
      </div>
      <?php if ($schoolLogoPath): ?>
        <div class="profile-field">
          <span>Verwijderen</span>
          <form action="/settings/school-logo/reset" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <button class="btn btn-outline" type="submit">Logo verwijderen</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Login afbeelding</div>
      <div class="muted text-sm">Deze afbeelding vervangt de standaard visual op de inlogpagina nadat iemand van jouw school is ingelogd op dit apparaat.</div>
    </div>
  </div>

  <?php if (!($canManageLoginVisual ?? false)): ?>
    <div class="empty">
      <div class="title">Geen toegang</div>
      <p class="muted">Je hebt schoolbeheerrechten nodig om de login afbeelding aan te passen.</p>
    </div>
  <?php else: ?>
    <div class="profile-grid">
      <div class="profile-field">
        <span>Huidige afbeelding</span>
        <strong><?= $loginVisualPath ? 'Aangepast' : 'Standaard' ?></strong>
      </div>
      <?php if ($loginVisualPath): ?>
        <div class="profile-field">
          <span>Voorbeeld</span>
          <img src="<?= htmlspecialchars($assetVersion($loginVisualPath)) ?>" alt="" style="width:100%;max-width:360px;aspect-ratio:16/9;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
        </div>
      <?php endif; ?>
      <div class="profile-field">
        <span>Nieuwe afbeelding</span>
        <form action="/settings/login-visual" method="post" enctype="multipart/form-data" class="login-visual-settings-form">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <input class="form-input" type="file" name="login_visual" accept="image/png,image/jpeg,image/webp" required>
          <button class="btn btn-primary" type="submit">Uploaden</button>
        </form>
      </div>
      <?php if ($loginVisualPath): ?>
        <div class="profile-field">
          <span>Terugzetten</span>
          <form action="/settings/login-visual/reset" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <button class="btn btn-outline" type="submit">Standaard gebruiken</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
