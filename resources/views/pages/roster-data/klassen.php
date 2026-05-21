<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Roosterbasis</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Klassen worden encrypted opgeslagen en gekoppeld aan een schooljaar.
      </div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Nieuwe klas</div>
      <div class="muted text-sm">Gebruik herkenbare namen zoals 1A, H4B of M3Z.</div>
    </div>
  </div>

  <form method="post" action="/klassen" class="form-grid">
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
      <label class="form-label">Schooljaar</label>
      <select class="form-select" name="schooljaar_id">
        <option value="">Geen schooljaar</option>
        <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
          <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>"><?= htmlspecialchars((string) $schoolYear['naam']) ?> · <?= htmlspecialchars((string) $schoolYear['school_naam']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Klasnaam</label>
      <input class="form-input" type="text" name="naam" required>
    </div>

    <div class="form-group">
      <label class="form-label">Leerjaar</label>
      <input class="form-input" type="number" name="leerjaar" min="1" max="8">
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Klas aanmaken</button>
    </div>
  </form>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Klassen</div>
      <div class="muted text-sm">Alle klassen binnen jouw school- of scholengroep-scope.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Klas</th>
          <th>Schooljaar</th>
          <th>Leerjaar</th>
          <th>School</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($classes ?? []) as $class): ?>
          <tr>
            <td><strong><?= htmlspecialchars((string) $class['naam']) ?></strong></td>
            <td class="muted"><?= htmlspecialchars((string) ($class['schooljaar_naam'] ?? '-')) ?></td>
            <td class="muted"><?= htmlspecialchars((string) ($class['leerjaar'] ?? '-')) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $class['school_naam']) ?></td>
            <td><span class="status <?= !empty($class['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($class['active']) ? 'Actief' : 'Inactief' ?></span></td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($classes)): ?>
          <tr><td colspan="5" class="muted">Nog geen klassen aangemaakt.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
