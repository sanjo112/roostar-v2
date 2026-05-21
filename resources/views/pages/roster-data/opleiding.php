<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Opleiding</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
        Vakken en lokalen zijn de eerste bouwstenen voor lessen en roosterblokken.
      </div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Vak toevoegen</div>
      <div class="muted text-sm">Vaknamen worden encrypted opgeslagen; codes blijven bruikbaar voor import/export.</div>
    </div>
  </div>

  <form method="post" action="/vakken" class="form-grid">
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
      <label class="form-label">Vaknaam</label>
      <input class="form-input" type="text" name="naam" required>
    </div>

    <div class="form-group">
      <label class="form-label">Code</label>
      <input class="form-input" type="text" name="code" placeholder="WIS">
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Vak aanmaken</button>
    </div>
  </form>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Lokaal toevoegen</div>
      <div class="muted text-sm">Lokalen kunnen later worden gebruikt voor capaciteit en beschikbaarheid.</div>
    </div>
  </div>

  <form method="post" action="/lokalen" class="form-grid">
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
      <label class="form-label">Lokaal</label>
      <input class="form-input" type="text" name="naam" required>
    </div>

    <div class="form-group">
      <label class="form-label">Capaciteit</label>
      <input class="form-input" type="number" name="capaciteit" min="1">
    </div>

    <div class="form-actions">
      <button class="btn btn-dark" type="submit">Lokaal aanmaken</button>
    </div>
  </form>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Vakken en lokalen</div>
      <div class="muted text-sm">Dit blijft bewust compact tot de lesverdeling erbij komt.</div>
    </div>
  </div>

  <div class="split-grid">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Vak</th>
            <th>Code</th>
            <th>School</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($subjects ?? []) as $subject): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $subject['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($subject['code'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) $subject['school_naam']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($subjects)): ?>
            <tr><td colspan="3" class="muted">Nog geen vakken aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Lokaal</th>
            <th>Capaciteit</th>
            <th>School</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($rooms ?? []) as $room): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $room['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($room['capaciteit'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) $room['school_naam']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($rooms)): ?>
            <tr><td colspan="3" class="muted">Nog geen lokalen aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
