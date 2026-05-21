<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Gebruikersbeheer</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 12a9 9 0 0 1 15-6.7L21 8M3 4v4h4M21 20v-4h-4"/></svg>
        Maak een gebruiker aan met expliciete school-scope en permission grants.
      </div>
    </div>
    <div class="view-actions">
      <a class="btn btn-outline" href="/gebruikers">Terug naar gebruikers</a>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Nieuwe gebruiker</div>
      <div class="muted text-sm">Namen worden encrypted opgeslagen. Rechten worden afgeleid uit de rol en school-scope.</div>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars((string) $error) ?></div>
  <?php endif; ?>

  <form method="post" action="/gebruikers" class="form-grid">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">

    <div class="form-group">
      <label class="form-label">Naam</label>
      <input class="form-input" type="text" name="name" required>
    </div>

    <div class="form-group">
      <label class="form-label">E-mailadres</label>
      <input class="form-input" type="email" name="email" required>
    </div>

    <div class="form-group">
      <label class="form-label">Wachtwoord</label>
      <input class="form-input" type="password" name="password" minlength="8" required>
    </div>

    <div class="form-group">
      <label class="form-label">Rol</label>
      <select class="form-select" name="role" required>
        <option value="">Kies een rol</option>
        <option value="afdelingsleider">Afdelingsleider</option>
        <option value="rooster_medewerker">Roostermedewerker</option>
        <option value="leraar">Leraar</option>
        <option value="leerling">Leerling</option>
        <option value="school_admin">School admin</option>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">School</label>
      <select class="form-select" name="school_id" required>
        <option value="">Kies een school</option>
        <?php foreach (($schools ?? []) as $school): ?>
          <option value="<?= htmlspecialchars((string) $school['id']) ?>"><?= htmlspecialchars((string) $school['naam']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Gebruiker aanmaken</button>
      <a class="btn btn-outline" href="/gebruikers">Annuleren</a>
    </div>
  </form>
</section>
