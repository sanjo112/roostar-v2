<?php
$customers = $customers ?? [];
$groups = $groups ?? [];
?>

<div id="customer-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="customer-create-title" hidden>
  <div class="modal modal-lg app-modal">
    <div class="modal-head">
      <div>
        <div id="customer-create-title" class="modal-title">Klant aanmaken</div>
        <div class="muted text-sm">Maak een scholengroep, school en optioneel direct een school-admin.</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="post" action="/roostar-admin/klanten">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
      <div class="modal-body">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">Scholengroep</label>
            <input class="form-input" type="text" name="scholengroep_naam" placeholder="Bijv. Stichting Roostar" required>
          </div>
          <div class="form-group">
            <label class="form-label">School</label>
            <input class="form-input" type="text" name="school_naam" placeholder="Bijv. Roostar College" required>
          </div>
          <div class="form-group">
            <label class="form-label">School-admin naam</label>
            <input class="form-input" type="text" name="admin_naam" placeholder="Optioneel">
          </div>
          <div class="form-group">
            <label class="form-label">School-admin e-mail</label>
            <input class="form-input" type="email" name="admin_email" placeholder="admin@school.nl">
          </div>
          <div class="form-group">
            <label class="form-label">School-admin wachtwoord</label>
            <input class="form-input" type="password" name="admin_wachtwoord" minlength="8" placeholder="Minimaal 8 tekens">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
        <button class="btn btn-dark" type="submit">Klant aanmaken</button>
      </div>
    </form>
  </div>
</div>

<section class="generation-page">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Roostar Admin</div>
      <h1 class="page-title">Klanten beheren</h1>
      <p class="muted">Maak scholengroepen, scholen en school-admins aan voor nieuwe klanten.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= count($customers) ?> school/scholen</strong>
        <span><?= count($groups) ?> scholengroep(en)</span>
      </div>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Klanten</div>
        <div class="muted text-sm">Alle scholen in het platform.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="customer-create-modal">Klant aanmaken</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>School</th>
            <th>Scholengroep</th>
            <th>Gebruikers</th>
            <th>Admins</th>
            <th>Aangemaakt</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $customer): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $customer['school_naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) $customer['groep_naam']) ?></td>
              <td class="muted"><?= (int) ($customer['gebruikers_count'] ?? 0) ?></td>
              <td><?= (int) ($customer['admins_count'] ?? 0) > 0 ? '<span class="status st-done">Aanwezig</span>' : '<span class="status st-warn">Geen admin</span>' ?></td>
              <td class="muted"><?= htmlspecialchars(date('d-m-Y', strtotime((string) $customer['created_at']))) ?></td>
              <td class="actions-cell">
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="school-admin-<?= htmlspecialchars((string) $customer['id']) ?>">Admin toevoegen</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($customers === []): ?>
            <tr><td colspan="6" class="muted">Nog geen klanten aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>

<?php foreach ($customers as $customer): ?>
  <div id="school-admin-<?= htmlspecialchars((string) $customer['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="school-admin-title-<?= htmlspecialchars((string) $customer['id']) ?>" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="school-admin-title-<?= htmlspecialchars((string) $customer['id']) ?>" class="modal-title">School-admin toevoegen</div>
          <div class="muted text-sm"><?= htmlspecialchars((string) $customer['school_naam']) ?></div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/roostar-admin/school-admins">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $customer['id']) ?>">
        <div class="modal-body">
          <div class="app-modal-grid">
            <div class="form-group">
              <label class="form-label">Naam</label>
              <input class="form-input" type="text" name="admin_naam" required>
            </div>
            <div class="form-group">
              <label class="form-label">E-mail</label>
              <input class="form-input" type="email" name="admin_email" required>
            </div>
            <div class="form-group">
              <label class="form-label">Wachtwoord</label>
              <input class="form-input" type="password" name="admin_wachtwoord" minlength="8" required>
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Admin aanmaken</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
