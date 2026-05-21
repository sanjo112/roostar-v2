<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Roosterbasis</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Schooljaren vormen de kapstok voor klassen, lessen en roosterpublicaties.
      </div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Nieuw schooljaar</div>
      <div class="muted text-sm">Maak per school een schooljaar aan voordat je klassen indeelt.</div>
    </div>
  </div>

  <form method="post" action="/schooljaar" class="form-grid">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">

    <div class="form-group">
      <label class="form-label">School</label>
      <select class="form-select" name="school_id" required>
        <option value="">Kies een school</option>
        <?php foreach (($schools ?? []) as $school): ?>
          <option value="<?= htmlspecialchars((string) $school['id']) ?>"><?= htmlspecialchars((string) $school['naam']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Naam</label>
      <input class="form-input" type="text" name="naam" placeholder="2026-2027" required>
    </div>

    <div class="form-group">
      <label class="form-label">Startdatum</label>
      <input class="form-input" type="date" name="startdatum" required>
    </div>

    <div class="form-group">
      <label class="form-label">Einddatum</label>
      <input class="form-input" type="date" name="einddatum" required>
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Schooljaar aanmaken</button>
    </div>
  </form>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Schooljaren</div>
      <div class="muted text-sm">Alle schooljaren binnen jouw scope.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Naam</th>
          <th>School</th>
          <th>Start</th>
          <th>Einde</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
          <tr>
            <td><strong><?= htmlspecialchars((string) $schoolYear['naam']) ?></strong></td>
            <td class="muted"><?= htmlspecialchars((string) $schoolYear['school_naam']) ?></td>
            <td class="muted"><?= htmlspecialchars(date('d-m-Y', strtotime((string) $schoolYear['startdatum']))) ?></td>
            <td class="muted"><?= htmlspecialchars(date('d-m-Y', strtotime((string) $schoolYear['einddatum']))) ?></td>
            <td><span class="status <?= !empty($schoolYear['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($schoolYear['active']) ? 'Actief' : 'Inactief' ?></span></td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($schoolYears)): ?>
          <tr><td colspan="5" class="muted">Nog geen schooljaren aangemaakt.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
