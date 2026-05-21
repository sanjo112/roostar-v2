<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Roosterbasis</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/><circle cx="10" cy="8" r="3.5"/></svg>
        Leraren komen uit gebruikersbeheer, zodat account, rechten en roosterprofiel dezelfde bron delen.
      </div>
    </div>
    <div class="view-actions">
      <a class="btn btn-dark" href="/gebruikers/nieuw">Nieuwe leraar</a>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Leraren</div>
      <div class="muted text-sm">Alle actieve en inactieve gebruikers met de rol leraar binnen jouw scope.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Naam</th>
          <th>E-mail</th>
          <th>School</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($teachers ?? []) as $teacher): ?>
          <tr>
            <td>
              <span class="assignee">
                <span class="avatar-sm av-1"><?= htmlspecialchars(strtoupper(substr((string) $teacher['naam'], 0, 1))) ?></span>
                <strong><?= htmlspecialchars((string) $teacher['naam']) ?></strong>
              </span>
            </td>
            <td class="muted"><?= htmlspecialchars((string) $teacher['email']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $teacher['school_naam']) ?></td>
            <td><span class="status <?= !empty($teacher['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($teacher['active']) ? 'Actief' : 'Inactief' ?></span></td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($teachers)): ?>
          <tr><td colspan="4" class="muted">Nog geen leraren gevonden. Maak eerst een gebruiker met rol leraar aan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
