<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Accountbeveiliging</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/></svg>
        Kies een nieuw wachtwoord voordat je verdergaat.
      </div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Nieuw wachtwoord</div>
      <div class="muted text-sm">Je tijdelijke wachtwoord wordt hierna ongeldig.</div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $error) ?></div>
  <?php endif; ?>

  <form method="post" action="/wachtwoord-wijzigen" class="form-grid">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">

    <div class="form-group">
      <label class="form-label">Tijdelijk of huidig wachtwoord</label>
      <input class="form-input" type="password" name="current_password" autocomplete="current-password" required>
    </div>

    <div class="form-group">
      <label class="form-label">Nieuw wachtwoord</label>
      <input class="form-input" type="password" name="new_password" minlength="10" autocomplete="new-password" required>
    </div>

    <div class="form-group">
      <label class="form-label">Herhaal nieuw wachtwoord</label>
      <input class="form-input" type="password" name="new_password_confirmation" minlength="10" autocomplete="new-password" required>
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Wachtwoord opslaan</button>
      <a class="btn btn-outline" href="/logout">Uitloggen</a>
    </div>
  </form>
</section>
