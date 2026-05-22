<?php
$schools = $schools ?? [];
$classes = $classes ?? [];
$electiveSubjectsByClass = $electiveSubjectsByClass ?? [];
$students = $students ?? [];
$singleSchoolId = count($schools) === 1 ? (string) ($schools[0]['id'] ?? '') : '';
?>

<div id="student-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="student-create-title" hidden>
  <div class="modal modal-lg app-modal">
    <div class="modal-head">
      <div>
        <div id="student-create-title" class="modal-title">Leerling aanmaken</div>
        <div class="muted text-sm">Maak een leerlingaccount en koppel deze aan een klas.</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="post" action="/leerlingen">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
      <div class="modal-body">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">School</label>
            <select class="form-select" name="school_id" required>
              <?php if ($singleSchoolId === ''): ?>
                <option value="">Kies een school</option>
              <?php endif; ?>
              <?php foreach ($schools as $school): ?>
                <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $school['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Klas</label>
            <select class="form-select" name="klas_id" data-student-class-select>
              <option value="">Nog niet gekoppeld</option>
              <?php foreach ($classes as $class): ?>
                <option value="<?= htmlspecialchars((string) $class['id']) ?>"><?= htmlspecialchars((string) $class['naam']) ?><?= !empty($class['schooljaar_naam']) ? ' · ' . htmlspecialchars((string) $class['schooljaar_naam']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group student-elective-field" data-student-electives hidden>
            <label class="form-label">Keuzevakken</label>
            <div class="modal-picker-list">
              <?php foreach ($classes as $class): ?>
                <?php foreach (($electiveSubjectsByClass[(string) $class['id']] ?? []) as $subject): ?>
                  <label class="modal-picker-item" data-student-elective-class="<?= htmlspecialchars((string) $class['id']) ?>" hidden>
                    <input type="checkbox" name="elective_subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>">
                    <span>
                      <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                      <small><?= htmlspecialchars((string) ($subject['code'] ?? '')) ?></small>
                    </span>
                  </label>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <span class="muted text-sm" data-student-electives-empty>Geen keuzevakken voor deze klas.</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Naam</label>
            <input class="form-input" type="text" name="name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Leerlingnummer</label>
            <input class="form-input" type="text" name="leerlingnummer">
          </div>
          <div class="form-group">
            <label class="form-label">E-mail</label>
            <input class="form-input" type="email" name="email" required>
          </div>
          <div class="form-group">
            <label class="form-label">Wachtwoord</label>
            <input class="form-input" type="password" name="password" minlength="8" required>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
        <button class="btn btn-dark" type="submit">Leerling aanmaken</button>
      </div>
    </form>
  </div>
</div>

<div id="student-import-export-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="student-import-export-title" hidden>
  <div class="modal app-modal">
    <div class="modal-head">
      <div>
        <div id="student-import-export-title" class="modal-title">Leerlingen importeren en exporteren</div>
        <div class="muted text-sm">Gebruik CSV met naam, leerlingnummer, e-mail, klas en keuzevakken.</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form method="post" action="/leerlingen/import" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">School</label>
            <select class="form-select" name="school_id" required>
              <?php if ($singleSchoolId === ''): ?>
                <option value="">Kies een school</option>
              <?php endif; ?>
              <?php foreach ($schools as $school): ?>
                <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $school['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">CSV bestand</label>
            <input class="form-input" type="file" name="csv_file" accept=".csv,text/csv" required>
          </div>
        </div>
        <div class="import-hint">
          Verwachte kolommen: <strong>naam</strong>, <strong>leerlingnummer</strong>, <strong>email</strong>, <strong>wachtwoord</strong>, <strong>klas</strong>, <strong>keuzevakken</strong>, <strong>active</strong>.
          Meerdere keuzevakken mogen met puntkomma in een cel, bijvoorbeeld <strong>WIS;NAT</strong>.
        </div>
        <div class="modal-foot compact-foot">
          <button class="btn btn-dark" type="submit">Importeren</button>
        </div>
      </form>

      <form method="get" action="/leerlingen/export" class="export-panel">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">School</label>
            <select class="form-select" name="school_id" required>
              <?php if ($singleSchoolId === ''): ?>
                <option value="">Kies een school</option>
              <?php endif; ?>
              <?php foreach ($schools as $school): ?>
                <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $school['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-foot compact-foot">
          <button class="btn btn-outline" type="submit">Exporteren</button>
        </div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" type="button" data-close-modal>Sluiten</button>
    </div>
  </div>
</div>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Leerlingen</div>
      <div class="muted text-sm">Beheer leerlingaccounts en klasindeling.</div>
    </div>
    <div class="view-actions">
      <button class="btn btn-outline" type="button" data-open-modal="student-import-export-modal">Import / export</button>
      <button class="btn btn-dark" type="button" data-open-modal="student-create-modal">Nieuwe leerling</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Naam</th>
          <th>Leerlingnummer</th>
          <th>E-mail</th>
          <th>Klas</th>
          <th>Keuzevakken</th>
          <th>Status</th>
          <th>Acties</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $student): ?>
          <tr>
            <td>
              <span class="assignee">
                <span class="avatar-sm av-2"><?= htmlspecialchars(strtoupper(substr((string) $student['naam'], 0, 1))) ?></span>
                <strong><?= htmlspecialchars((string) $student['naam']) ?></strong>
              </span>
            </td>
            <td class="muted"><?= htmlspecialchars((string) ($student['leerlingnummer'] ?? '-')) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $student['email']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) ($student['klas_naam'] ?: 'Niet gekoppeld')) ?></td>
            <td class="muted">
              <?php if (!empty($student['electives'])): ?>
                <?= htmlspecialchars(implode(', ', array_map(static fn (array $subject): string => (string) ($subject['code'] ?: $subject['naam']), $student['electives']))) ?>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><span class="status <?= !empty($student['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($student['active']) ? 'Actief' : 'Inactief' ?></span></td>
            <td class="actions-cell">
              <div class="table-actions">
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="student-edit-<?= htmlspecialchars((string) $student['id']) ?>">Bewerken</button>
                <form method="post" action="/leerlingen/verwijder">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $student['school_id']) ?>">
                  <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) $student['id']) ?>">
                  <button class="btn btn-ghost btn-sm btn-danger-link" type="submit">Verwijderen</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($students === []): ?>
          <tr><td colspan="7" class="muted">Nog geen leerlingen gevonden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($students as $student): ?>
  <?php $studentElectiveIds = array_map(static fn (array $subject): string => (string) $subject['id'], $student['electives'] ?? []); ?>
  <div id="student-edit-<?= htmlspecialchars((string) $student['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="student-edit-title-<?= htmlspecialchars((string) $student['id']) ?>" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="student-edit-title-<?= htmlspecialchars((string) $student['id']) ?>" class="modal-title">Leerling bewerken</div>
          <div class="muted text-sm"><?= htmlspecialchars((string) $student['email']) ?></div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/leerlingen/bewerk">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $student['school_id']) ?>">
        <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) $student['id']) ?>">
        <div class="modal-body">
          <div class="app-modal-grid">
            <div class="form-group">
              <label class="form-label">Naam</label>
              <input class="form-input" type="text" name="name" value="<?= htmlspecialchars((string) $student['naam']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Leerlingnummer</label>
              <input class="form-input" type="text" name="leerlingnummer" value="<?= htmlspecialchars((string) ($student['leerlingnummer'] ?? '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">E-mail</label>
              <input class="form-input" type="email" name="email" value="<?= htmlspecialchars((string) $student['email']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Klas</label>
              <select class="form-select" name="klas_id" data-student-class-select>
                <option value="">Niet gekoppeld</option>
                <?php foreach ($classes as $class): ?>
                  <?php if ((string) $class['school_id'] !== (string) $student['school_id']) { continue; } ?>
                  <option value="<?= htmlspecialchars((string) $class['id']) ?>" <?= (string) ($student['klas_id'] ?? '') === (string) $class['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $class['naam']) ?><?= !empty($class['schooljaar_naam']) ? ' · ' . htmlspecialchars((string) $class['schooljaar_naam']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group student-elective-field" data-student-electives <?= !empty($student['klas_id']) ? '' : 'hidden' ?>>
              <label class="form-label">Keuzevakken</label>
              <div class="modal-picker-list">
                <?php foreach ($classes as $class): ?>
                  <?php if ((string) $class['school_id'] !== (string) $student['school_id']) { continue; } ?>
                  <?php foreach (($electiveSubjectsByClass[(string) $class['id']] ?? []) as $subject): ?>
                    <label class="modal-picker-item" data-student-elective-class="<?= htmlspecialchars((string) $class['id']) ?>" <?= (string) ($student['klas_id'] ?? '') === (string) $class['id'] ? '' : 'hidden' ?>>
                      <input type="checkbox" name="elective_subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= in_array((string) $subject['id'], $studentElectiveIds, true) ? 'checked' : '' ?>>
                      <span>
                        <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                        <small><?= htmlspecialchars((string) ($subject['code'] ?? '')) ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <span class="muted text-sm" data-student-electives-empty>Geen keuzevakken voor deze klas.</span>
              </div>
            </div>
            <label class="modal-picker-item">
              <input type="checkbox" name="active" value="1" <?= !empty($student['active']) ? 'checked' : '' ?>>
              <span><strong>Actief</strong><small>Beschikbaar in overzichten</small></span>
            </label>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Leerling opslaan</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
