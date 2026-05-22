<?php
$schools = $schools ?? [];
$singleSchoolId = count($schools) === 1 ? (string) ($schools[0]['id'] ?? '') : '';
$periodsBySchoolYear = [];
foreach (($periods ?? []) as $period) {
    $periodsBySchoolYear[(string) $period['schooljaar_id']][] = $period;
}
$teacherDays = ['ma' => 'Ma', 'di' => 'Di', 'wo' => 'Wo', 'do' => 'Do', 'vr' => 'Vr'];
$teacherDayLabels = ['ma' => 'Maandag', 'di' => 'Dinsdag', 'wo' => 'Woensdag', 'do' => 'Donderdag', 'vr' => 'Vrijdag'];
$teacherPeriods = range(1, 9);
$allTeacherSlots = [];
foreach (array_keys($teacherDays) as $teacherDayKey) {
    foreach ($teacherPeriods as $teacherPeriod) {
        $allTeacherSlots[] = $teacherDayKey . '-' . $teacherPeriod;
    }
}
$weekOptionsForSchoolYear = static function (array $schoolYear): array {
    $start = new DateTimeImmutable((string) $schoolYear['startdatum']);
    $end = new DateTimeImmutable((string) $schoolYear['einddatum']);
    $cursor = $start->setISODate((int) $start->format('o'), (int) $start->format('W'), 1);
    $last = $end->setISODate((int) $end->format('o'), (int) $end->format('W'), 1);
    $options = [];

    while ($cursor <= $last) {
        $year = (int) $cursor->format('o');
        $week = (int) $cursor->format('W');
        $options[] = [
            'key' => sprintf('%04d-%02d', $year, $week),
            'label' => $year . ' · week ' . $week,
            'year' => $year,
            'week' => $week,
        ];
        $cursor = $cursor->modify('+1 week');
    }

    return $options;
};
$periodWeekKey = static function (array $period, string $side, array $schoolYear): string {
    $week = (int) ($period['week_' . $side] ?? 0);
    $year = (int) ($period['week_' . $side . '_jaar'] ?? 0);

    if ($year <= 0) {
        $start = new DateTimeImmutable((string) $schoolYear['startdatum']);
        $end = new DateTimeImmutable((string) $schoolYear['einddatum']);
        $startWeek = (int) $start->format('W');
        $year = $week >= $startWeek ? (int) $start->format('o') : (int) $end->format('o');
    }

    return sprintf('%04d-%02d', $year, $week);
};
$periodWeekLabel = static function (array $period, string $side, array $schoolYear) use ($periodWeekKey): string {
    [$year, $week] = explode('-', $periodWeekKey($period, $side, $schoolYear), 2);

    return $year . ' wk ' . (int) $week;
};
?>

<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Instellingen</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v12c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/></svg>
        Beheer stamdata voor roosters en systeeminstellingen.
      </div>
    </div>
    <nav class="settings-tabs segmented tabs-inline" aria-label="Stamdata tabs">
      <?php foreach (($tabs ?? []) as $tabKey => $tabLabel): ?>
        <a class="<?= ($activeTab ?? '') === $tabKey ? 'active' : '' ?>" href="/stamdata?tab=<?= htmlspecialchars((string) $tabKey) ?>">
          <?= htmlspecialchars((string) $tabLabel) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<?php if (($activeTab ?? 'vakken') === 'schooljaren'): ?>
  <div id="schoolyear-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="schoolyear-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="schoolyear-create-title" class="modal-title">Schooljaar aanmaken</div>
          <div class="muted text-sm">Maak per school een schooljaar aan voordat je klassen indeelt.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/schooljaar">
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
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Schooljaar aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Schooljaren</div>
        <div class="muted text-sm">Alle schooljaren binnen jouw scope.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="schoolyear-create-modal">Schooljaar aanmaken</button>
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
            <th>Periodes</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
            <?php $schoolYearPeriods = $periodsBySchoolYear[(string) $schoolYear['id']] ?? []; ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $schoolYear['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) $schoolYear['school_naam']) ?></td>
              <td class="muted"><?= htmlspecialchars(date('d-m-Y', strtotime((string) $schoolYear['startdatum']))) ?></td>
              <td class="muted"><?= htmlspecialchars(date('d-m-Y', strtotime((string) $schoolYear['einddatum']))) ?></td>
              <td>
                <div class="inline-pill-list">
                  <?php foreach ($schoolYearPeriods as $period): ?>
                    <span class="soft-pill">
                      <?= htmlspecialchars((string) $period['naam']) ?> ·
                      <?= htmlspecialchars($periodWeekLabel($period, 'van', $schoolYear)) ?> -
                      <?= htmlspecialchars($periodWeekLabel($period, 'tot', $schoolYear)) ?>
                    </span>
                  <?php endforeach; ?>
                  <?php if ($schoolYearPeriods === []): ?>
                    <span class="muted">Geen periodes</span>
                  <?php endif; ?>
                </div>
              </td>
              <td><span class="status <?= !empty($schoolYear['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($schoolYear['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="schoolyear-edit-<?= htmlspecialchars((string) $schoolYear['id']) ?>">Bewerken</button>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="period-manage-<?= htmlspecialchars((string) $schoolYear['id']) ?>">Periodes</button>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($schoolYears)): ?>
            <tr><td colspan="7" class="muted">Nog geen schooljaren aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
    <div id="schoolyear-edit-<?= htmlspecialchars((string) $schoolYear['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="schoolyear-edit-title-<?= htmlspecialchars((string) $schoolYear['id']) ?>" hidden>
      <div class="modal modal-lg app-modal">
        <div class="modal-head">
          <div>
            <div id="schoolyear-edit-title-<?= htmlspecialchars((string) $schoolYear['id']) ?>" class="modal-title">Schooljaar bewerken</div>
            <div class="muted text-sm">Werk de periode en status van dit schooljaar bij.</div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/schooljaar/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars((string) $schoolYear['id']) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $schoolYear['school_id']) ?>">
          <div class="modal-body">
            <div class="app-modal-grid">
              <div class="form-group">
                <label class="form-label">School</label>
                <input class="form-input" type="text" value="<?= htmlspecialchars((string) $schoolYear['school_naam']) ?>" disabled>
              </div>
              <div class="form-group">
                <label class="form-label">Naam</label>
                <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $schoolYear['naam']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Startdatum</label>
                <input class="form-input" type="date" name="startdatum" value="<?= htmlspecialchars((string) $schoolYear['startdatum']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Einddatum</label>
                <input class="form-input" type="date" name="einddatum" value="<?= htmlspecialchars((string) $schoolYear['einddatum']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <label class="modal-picker-item">
                  <input type="hidden" name="active" value="0">
                  <input type="checkbox" name="active" value="1" <?= !empty($schoolYear['active']) ? 'checked' : '' ?>>
                  <span><strong>Actief</strong><small>Beschikbaar in roosters</small></span>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Schooljaar opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
    <?php $schoolYearPeriods = $periodsBySchoolYear[(string) $schoolYear['id']] ?? []; ?>
    <?php $schoolYearWeekOptions = $weekOptionsForSchoolYear($schoolYear); ?>
    <div id="period-manage-<?= htmlspecialchars((string) $schoolYear['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="period-manage-title-<?= htmlspecialchars((string) $schoolYear['id']) ?>" hidden>
      <div class="modal modal-xl app-modal">
        <div class="modal-head">
          <div>
            <div id="period-manage-title-<?= htmlspecialchars((string) $schoolYear['id']) ?>" class="modal-title">Periodes beheren</div>
            <div class="muted text-sm"><?= htmlspecialchars((string) $schoolYear['naam']) ?> · <?= htmlspecialchars((string) $schoolYear['school_naam']) ?></div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="period-manager">
            <div class="period-manager-list">
              <?php if ($schoolYearPeriods !== []): ?>
                <div class="period-manager-head">
                  <span>Periode</span>
                  <span>Van</span>
                  <span>Tot en met</span>
                  <span>Status</span>
                  <span>Acties</span>
                </div>
              <?php endif; ?>
              <?php foreach ($schoolYearPeriods as $period): ?>
                <form method="post" action="/schooljaar/periodes/bewerk" class="period-manager-row">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars((string) $schoolYear['id']) ?>">
                  <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $schoolYear['school_id']) ?>">
                  <input type="hidden" name="periode_id" value="<?= htmlspecialchars((string) $period['id']) ?>">
                  <div class="form-group">
                    <label class="form-label sr-only">Naam</label>
                    <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $period['naam']) ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label sr-only">Week van</label>
                    <select class="form-select" name="week_van_key" required>
                      <?php foreach ($schoolYearWeekOptions as $weekOption): ?>
                        <option value="<?= htmlspecialchars((string) $weekOption['key']) ?>" <?= $weekOption['key'] === $periodWeekKey($period, 'van', $schoolYear) ? 'selected' : '' ?>>
                          <?= htmlspecialchars((string) $weekOption['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label sr-only">Week tot</label>
                    <select class="form-select" name="week_tot_key" required>
                      <?php foreach ($schoolYearWeekOptions as $weekOption): ?>
                        <option value="<?= htmlspecialchars((string) $weekOption['key']) ?>" <?= $weekOption['key'] === $periodWeekKey($period, 'tot', $schoolYear) ? 'selected' : '' ?>>
                          <?= htmlspecialchars((string) $weekOption['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <label class="period-active-toggle">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" <?= !empty($period['active']) ? 'checked' : '' ?>>
                    <span>Actief</span>
                  </label>
                  <div class="period-manager-actions">
                    <button class="btn btn-dark btn-sm" type="submit">Opslaan</button>
                    <button class="btn btn-outline btn-sm danger-soft" type="submit" form="period-delete-<?= htmlspecialchars((string) $period['id']) ?>">Verwijderen</button>
                  </div>
                </form>
                <form id="period-delete-<?= htmlspecialchars((string) $period['id']) ?>" method="post" action="/schooljaar/periodes/verwijder">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars((string) $schoolYear['id']) ?>">
                  <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $schoolYear['school_id']) ?>">
                  <input type="hidden" name="periode_id" value="<?= htmlspecialchars((string) $period['id']) ?>">
                </form>
              <?php endforeach; ?>
              <?php if ($schoolYearPeriods === []): ?>
                <div class="period-manager-empty">Nog geen periodes voor dit schooljaar.</div>
              <?php endif; ?>
            </div>

            <form method="post" action="/schooljaar/periodes" class="period-manager-create">
              <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
              <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars((string) $schoolYear['id']) ?>">
              <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $schoolYear['school_id']) ?>">
              <div class="period-manager-create-head">
                <strong>Periode toevoegen</strong>
              </div>
              <div class="period-manager-create-grid">
                <div class="form-group">
                  <label class="form-label sr-only">Naam</label>
                  <input class="form-input" type="text" name="naam" placeholder="Periode 1" required>
                </div>
                <div class="form-group">
                  <label class="form-label sr-only">Week van</label>
                  <select class="form-select" name="week_van_key" required>
                    <option value="">Van</option>
                    <?php foreach ($schoolYearWeekOptions as $weekOption): ?>
                      <option value="<?= htmlspecialchars((string) $weekOption['key']) ?>"><?= htmlspecialchars((string) $weekOption['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label sr-only">Week tot</label>
                  <select class="form-select" name="week_tot_key" required>
                    <option value="">Tot en met</option>
                    <?php foreach ($schoolYearWeekOptions as $weekOption): ?>
                      <option value="<?= htmlspecialchars((string) $weekOption['key']) ?>"><?= htmlspecialchars((string) $weekOption['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn-dark btn-sm" type="submit">Toevoegen</button>
              </div>
            </form>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Sluiten</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (($activeTab ?? 'vakken') === 'klassen'): ?>
  <div id="class-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="class-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="class-create-title" class="modal-title">Klas aanmaken</div>
          <div class="muted text-sm">Gebruik herkenbare namen zoals 1A, H4B of M3Z.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/klassen">
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
              <label class="form-label">Schooljaar</label>
              <select class="form-select" name="schooljaar_id">
                <option value="">Geen schooljaar</option>
                <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
                  <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>"><?= htmlspecialchars((string) $schoolYear['naam']) ?> · <?= htmlspecialchars((string) $schoolYear['school_naam']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Opleiding</label>
              <select class="form-select" name="opleiding_id">
                <option value="">Geen opleiding</option>
                <?php foreach (($programs ?? []) as $program): ?>
                  <option value="<?= htmlspecialchars((string) $program['id']) ?>"><?= htmlspecialchars((string) $program['naam']) ?> · <?= htmlspecialchars((string) $program['school_naam']) ?></option>
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
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Klas aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Klassen</div>
        <div class="muted text-sm">Alle klassen binnen jouw school- of scholengroep-scope.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="class-create-modal">Klas aanmaken</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Klas</th>
            <th>Schooljaar</th>
            <th>Opleiding</th>
            <th>Leerjaar</th>
            <th>School</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($classes ?? []) as $class): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $class['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($class['schooljaar_naam'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) ($class['opleiding_naam'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) ($class['leerjaar'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) $class['school_naam']) ?></td>
              <td><span class="status <?= !empty($class['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($class['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="class-edit-<?= htmlspecialchars((string) $class['id']) ?>">Bewerken</button>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($classes)): ?>
            <tr><td colspan="7" class="muted">Nog geen klassen aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($classes ?? []) as $class): ?>
    <div id="class-edit-<?= htmlspecialchars((string) $class['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="class-edit-title-<?= htmlspecialchars((string) $class['id']) ?>" hidden>
      <div class="modal modal-lg app-modal">
        <div class="modal-head">
          <div>
            <div id="class-edit-title-<?= htmlspecialchars((string) $class['id']) ?>" class="modal-title">Klas bewerken</div>
            <div class="muted text-sm">Werk klasnaam, opleiding, leerjaar en status bij.</div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/klassen/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="klas_id" value="<?= htmlspecialchars((string) $class['id']) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $class['school_id']) ?>">
          <div class="modal-body">
            <div class="app-modal-grid">
              <div class="form-group">
                <label class="form-label">School</label>
                <input class="form-input" type="text" value="<?= htmlspecialchars((string) $class['school_naam']) ?>" disabled>
              </div>
              <div class="form-group">
                <label class="form-label">Schooljaar</label>
                <select class="form-select" name="schooljaar_id">
                  <option value="">Geen schooljaar</option>
                  <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
                    <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>" <?= (string) ($class['schooljaar_id'] ?? '') === (string) $schoolYear['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $schoolYear['naam']) ?> · <?= htmlspecialchars((string) $schoolYear['school_naam']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Opleiding</label>
                <select class="form-select" name="opleiding_id">
                  <option value="">Geen opleiding</option>
                  <?php foreach (($programs ?? []) as $program): ?>
                    <option value="<?= htmlspecialchars((string) $program['id']) ?>" <?= (string) ($class['opleiding_id'] ?? '') === (string) $program['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $program['naam']) ?> · <?= htmlspecialchars((string) $program['school_naam']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Klasnaam</label>
                <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $class['naam']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Leerjaar</label>
                <input class="form-input" type="number" name="leerjaar" min="1" max="8" value="<?= htmlspecialchars((string) ($class['leerjaar'] ?? '')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <label class="modal-picker-item">
                  <input type="hidden" name="active" value="0">
                  <input type="checkbox" name="active" value="1" <?= !empty($class['active']) ? 'checked' : '' ?>>
                  <span><strong>Actief</strong><small>Beschikbaar voor roosters</small></span>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Klas opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (($activeTab ?? 'vakken') === 'vakken'): ?>
  <div id="subject-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="subject-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="subject-create-title" class="modal-title">Vak aanmaken</div>
          <div class="muted text-sm">Vaknamen worden encrypted opgeslagen; codes blijven bruikbaar voor import/export.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/vakken">
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
              <label class="form-label">Vaknaam</label>
              <input class="form-input" type="text" name="naam" required>
            </div>
            <div class="form-group">
              <label class="form-label">Code</label>
              <input class="form-input" type="text" name="code" placeholder="WIS">
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Vak aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Vakken</div>
        <div class="muted text-sm">Alle vakken binnen jouw scope.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="subject-create-modal">Vak aanmaken</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Vak</th>
            <th>Code</th>
            <th>School</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($subjects ?? []) as $subject): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $subject['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($subject['code'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) $subject['school_naam']) ?></td>
              <td><span class="status <?= !empty($subject['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($subject['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="subject-edit-<?= htmlspecialchars((string) $subject['id']) ?>">Bewerken</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($subjects)): ?>
            <tr><td colspan="5" class="muted">Nog geen vakken aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($subjects ?? []) as $subject): ?>
    <div id="subject-edit-<?= htmlspecialchars((string) $subject['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="subject-edit-title-<?= htmlspecialchars((string) $subject['id']) ?>" hidden>
      <div class="modal modal-lg app-modal">
        <div class="modal-head">
          <div>
            <div id="subject-edit-title-<?= htmlspecialchars((string) $subject['id']) ?>" class="modal-title">Vak bewerken</div>
            <div class="muted text-sm">Werk de vaknaam, code en status bij.</div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/vakken/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="vak_id" value="<?= htmlspecialchars((string) $subject['id']) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $subject['school_id']) ?>">
          <div class="modal-body">
            <div class="app-modal-grid">
              <div class="form-group">
                <label class="form-label">School</label>
                <input class="form-input" type="text" value="<?= htmlspecialchars((string) $subject['school_naam']) ?>" disabled>
              </div>
              <div class="form-group">
                <label class="form-label">Vaknaam</label>
                <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $subject['naam']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="<?= htmlspecialchars((string) ($subject['code'] ?? '')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <label class="modal-picker-item">
                  <input type="hidden" name="active" value="0">
                  <input type="checkbox" name="active" value="1" <?= !empty($subject['active']) ? 'checked' : '' ?>>
                  <span><strong>Actief</strong><small>Beschikbaar in vakkenpakketten</small></span>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Vak opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (($activeTab ?? 'vakken') === 'lokalen'): ?>
  <div id="location-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="location-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="location-create-title" class="modal-title">Locatie aanmaken</div>
          <div class="muted text-sm">Gebruik locaties voor eigen gebouwen en externe leslocaties.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/locaties">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="school_id" value="<?= htmlspecialchars($singleSchoolId !== '' ? $singleSchoolId : (string) ($schools[0]['id'] ?? '')) ?>">
        <div class="modal-body">
          <div class="app-modal-grid room-modal-grid">
            <div class="form-group">
              <label class="form-label">Locatie</label>
              <input class="form-input" type="text" name="naam" placeholder="Sporthal De Brug" required>
            </div>
            <label class="modal-picker-item">
              <input type="checkbox" name="extern" value="1">
              <span><strong>Externe locatie</strong><small>Buiten het eigen schoolgebouw</small></span>
            </label>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Locatie aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <div id="room-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="room-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="room-create-title" class="modal-title">Lokaal aanmaken</div>
          <div class="muted text-sm">Lokalen kunnen later worden gebruikt voor capaciteit en beschikbaarheid.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/lokalen">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <div class="modal-body">
          <div class="app-modal-grid room-modal-grid">
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
              <label class="form-label">Locatie</label>
              <select class="form-select" name="locatie_id" required data-room-location-select>
                <option value="">Kies een locatie</option>
                <?php foreach (($locations ?? []) as $location): ?>
                  <option value="<?= htmlspecialchars((string) $location['id']) ?>" data-external="<?= !empty($location['extern']) ? '1' : '0' ?>">
                    <?= htmlspecialchars((string) $location['naam']) ?><?= !empty($location['extern']) ? ' · extern' : '' ?>
                  </option>
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
          </div>
          <div class="form-group room-external-hours" data-room-external-hours hidden>
            <div class="availability-tools">
              <label class="form-label">Inzetbare uren voor dit externe lokaal</label>
              <div class="availability-actions">
                <button class="btn btn-ghost btn-sm" type="button" data-check-grid="all">Alles selecteren</button>
                <button class="btn btn-ghost btn-sm" type="button" data-check-grid="none">Deselecteren</button>
              </div>
            </div>
            <div class="teacher-availability-grid" style="--day-count: <?= count($teacherDays) ?>">
              <div class="teacher-availability-corner">Uur</div>
              <?php foreach ($teacherDays as $label): ?>
                <div class="teacher-availability-head"><?= htmlspecialchars($label) ?></div>
              <?php endforeach; ?>
              <?php foreach ($teacherPeriods as $period): ?>
                <div class="teacher-availability-hour"><?= htmlspecialchars((string) $period) ?></div>
                <?php foreach (array_keys($teacherDays) as $dayKey): ?>
                  <?php $slotKey = $dayKey . '-' . $period; ?>
                  <label class="teacher-slot-check" title="<?= htmlspecialchars($teacherDayLabels[$dayKey] . ' uur ' . $period) ?>">
                    <input type="checkbox" name="available_slots[]" value="<?= htmlspecialchars($slotKey) ?>" checked>
                  </label>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <div class="form-group">
          <label class="form-label">Vakken die in dit lokaal gegeven kunnen worden</label>
          <div class="modal-picker-list">
            <?php foreach (($subjects ?? []) as $subject): ?>
              <label class="modal-picker-item">
                <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>">
                <span>
                  <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                  <?php if (!empty($subject['code'])): ?>
                    <small><?= htmlspecialchars((string) $subject['code']) ?></small>
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?>
              <span class="muted text-sm">Maak eerst vakken aan.</span>
            <?php endif; ?>
          </div>
        </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Lokaal aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Lokalen</div>
        <div class="muted text-sm">Alle lokalen en locaties binnen jouw scope.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-outline" type="button" data-open-modal="location-create-modal">Locatie aanmaken</button>
        <button class="btn btn-dark" type="button" data-open-modal="room-create-modal">Lokaal aanmaken</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Lokaal</th>
            <th>Capaciteit</th>
            <th>Vakken</th>
            <th>Locatie</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($rooms ?? []) as $room): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $room['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($room['capaciteit'] ?? '-')) ?></td>
              <td class="muted">
                <?php if (!empty($room['subjects'])): ?>
                  <?= htmlspecialchars(implode(', ', array_map(static fn (array $subject): string => (string) ($subject['code'] ?: $subject['naam']), $room['subjects']))) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="muted">
                <?= htmlspecialchars((string) $room['locatie_naam']) ?>
                <?php if (!empty($room['locatie_extern'])): ?>
                  <span class="soft-pill">Extern</span>
                <?php endif; ?>
              </td>
              <td><span class="status <?= !empty($room['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($room['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="room-edit-<?= htmlspecialchars((string) $room['id']) ?>">Bewerken</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($rooms)): ?>
            <tr><td colspan="6" class="muted">Nog geen lokalen aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($rooms ?? []) as $room): ?>
    <?php
      $roomSubjectIds = array_map(static fn (array $subject): string => (string) $subject['id'], $room['subjects'] ?? []);
      $roomAvailableSlots = $room['available_slots'] ?? $allTeacherSlots;
      $roomAvailableSlots = is_array($roomAvailableSlots) ? $roomAvailableSlots : $allTeacherSlots;
    ?>
    <div id="room-edit-<?= htmlspecialchars((string) $room['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="room-edit-title-<?= htmlspecialchars((string) $room['id']) ?>" hidden>
      <div class="modal modal-lg app-modal">
        <div class="modal-head">
          <div>
            <div id="room-edit-title-<?= htmlspecialchars((string) $room['id']) ?>" class="modal-title">Lokaal bewerken</div>
            <div class="muted text-sm">Werk lokaalnaam, capaciteit, vakken en status bij.</div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/lokalen/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="lokaal_id" value="<?= htmlspecialchars((string) $room['id']) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $room['school_id']) ?>">
          <div class="modal-body">
            <div class="app-modal-grid room-modal-grid">
              <div class="form-group">
                <label class="form-label">Locatie</label>
                <select class="form-select" name="locatie_id" required data-room-location-select>
                  <option value="">Kies een locatie</option>
                  <?php foreach (($locations ?? []) as $location): ?>
                    <?php if ((string) $location['school_id'] !== (string) $room['school_id']) { continue; } ?>
                    <option value="<?= htmlspecialchars((string) $location['id']) ?>" data-external="<?= !empty($location['extern']) ? '1' : '0' ?>" <?= (string) ($room['locatie_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string) $location['naam']) ?><?= !empty($location['extern']) ? ' · extern' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Lokaal</label>
                <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $room['naam']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Capaciteit</label>
                <input class="form-input" type="number" name="capaciteit" min="1" value="<?= htmlspecialchars((string) ($room['capaciteit'] ?? '')) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <label class="modal-picker-item">
                  <input type="hidden" name="active" value="0">
                  <input type="checkbox" name="active" value="1" <?= !empty($room['active']) ? 'checked' : '' ?>>
                  <span><strong>Actief</strong><small>Beschikbaar voor roosters</small></span>
                </label>
              </div>
            </div>
            <div class="form-group room-external-hours" data-room-external-hours <?= !empty($room['locatie_extern']) ? '' : 'hidden' ?>>
              <div class="availability-tools">
                <label class="form-label">Inzetbare uren voor dit externe lokaal</label>
                <div class="availability-actions">
                  <button class="btn btn-ghost btn-sm" type="button" data-check-grid="all">Alles selecteren</button>
                  <button class="btn btn-ghost btn-sm" type="button" data-check-grid="none">Deselecteren</button>
                </div>
              </div>
              <div class="teacher-availability-grid" style="--day-count: <?= count($teacherDays) ?>">
                <div class="teacher-availability-corner">Uur</div>
                <?php foreach ($teacherDays as $label): ?>
                  <div class="teacher-availability-head"><?= htmlspecialchars($label) ?></div>
                <?php endforeach; ?>
                <?php foreach ($teacherPeriods as $period): ?>
                  <div class="teacher-availability-hour"><?= htmlspecialchars((string) $period) ?></div>
                  <?php foreach (array_keys($teacherDays) as $dayKey): ?>
                    <?php $slotKey = $dayKey . '-' . $period; ?>
                    <label class="teacher-slot-check" title="<?= htmlspecialchars($teacherDayLabels[$dayKey] . ' uur ' . $period) ?>">
                      <input type="checkbox" name="available_slots[]" value="<?= htmlspecialchars($slotKey) ?>" <?= in_array($slotKey, $roomAvailableSlots, true) ? 'checked' : '' ?>>
                    </label>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Vakken die in dit lokaal gegeven kunnen worden</label>
              <div class="modal-picker-list">
                <?php foreach (($subjects ?? []) as $subject): ?>
                  <label class="modal-picker-item">
                    <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= in_array((string) $subject['id'], $roomSubjectIds, true) ? 'checked' : '' ?>>
                    <span>
                      <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                      <?php if (!empty($subject['code'])): ?>
                        <small><?= htmlspecialchars((string) $subject['code']) ?></small>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                  <span class="muted text-sm">Maak eerst vakken aan.</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Lokaal opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (($activeTab ?? 'vakken') === 'opleidingen'): ?>
  <div id="program-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="program-create-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="program-create-title" class="modal-title">Opleiding aanmaken</div>
          <div class="muted text-sm">Koppel direct het vakkenpakket aan deze opleiding.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    <form method="post" action="/opleidingen">
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
            <label class="form-label">Opleiding</label>
            <input class="form-input" type="text" name="naam" placeholder="Havo onderbouw" required>
          </div>

          <div class="form-group">
            <label class="form-label">Code</label>
            <input class="form-input" type="text" name="code" placeholder="HAVO-OB">
          </div>

          <div class="form-group">
            <label class="form-label">Niveau</label>
            <input class="form-input" type="text" name="niveau" placeholder="Havo">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Vakkenpakket</label>
          <div class="program-subject-hours-list">
            <?php if (!empty($periods)): ?>
              <div class="program-subject-hours-head" style="--period-count: <?= max(1, count($periods)) ?>">
                <span>Vak</span>
                <span>Keuze</span>
                <?php foreach (($periods ?? []) as $period): ?>
                  <span><?= htmlspecialchars((string) $period['naam']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php foreach (($subjects ?? []) as $subject): ?>
              <div class="program-subject-hours-row" style="--period-count: <?= max(1, count($periods)) ?>">
                <label class="program-subject-check">
                  <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>">
                  <span>
                  <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                  <?php if (!empty($subject['code'])): ?>
                    <small><?= htmlspecialchars((string) $subject['code']) ?></small>
                  <?php endif; ?>
                  </span>
                </label>
                <label class="program-elective-check" title="Leerlingen kunnen dit vak kiezen">
                  <input type="checkbox" name="elective_subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>">
                </label>
                <?php foreach (($periods ?? []) as $period): ?>
                  <input class="form-input subject-hours-input" type="number" name="subject_hours[<?= htmlspecialchars((string) $subject['id']) ?>][<?= htmlspecialchars((string) $period['id']) ?>]" min="0" max="40" value="3" aria-label="<?= htmlspecialchars((string) $subject['naam']) ?> <?= htmlspecialchars((string) $period['naam']) ?> uren per week">
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?>
              <span class="muted text-sm">Maak eerst vakken aan.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
        <button class="btn btn-dark" type="submit">Opleiding aanmaken</button>
      </div>
    </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Opleidingen</div>
        <div class="muted text-sm">Alle opleidingen met hun vakkenpakket binnen jouw scope.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="program-create-modal">Opleiding aanmaken</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Opleiding</th>
            <th>Code</th>
            <th>Niveau</th>
            <th>Vakkenpakket</th>
            <th>School</th>
            <th>Status</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($programs ?? []) as $program): ?>
            <?php $programSubjectIds = array_map(static fn (array $subject): string => (string) $subject['id'], $program['subjects'] ?? []); ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $program['naam']) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($program['code'] ?? '-')) ?></td>
              <td class="muted"><?= htmlspecialchars((string) ($program['niveau'] ?? '-')) ?></td>
              <td class="muted">
                <?php if (!empty($program['subjects'])): ?>
                  <?= htmlspecialchars(implode(', ', array_map(static fn (array $subject): string => (string) ($subject['code'] ?: $subject['naam']), $program['subjects']))) ?>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td class="muted"><?= htmlspecialchars((string) $program['school_naam']) ?></td>
              <td><span class="status <?= !empty($program['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($program['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td>
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="program-edit-<?= htmlspecialchars((string) $program['id']) ?>">Bewerken</button>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($programs)): ?>
            <tr><td colspan="7" class="muted">Nog geen opleidingen aangemaakt.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($programs ?? []) as $program): ?>
    <?php
      $programSubjectIds = array_map(static fn (array $subject): string => (string) $subject['id'], $program['subjects'] ?? []);
      $programSubjectsById = [];
      foreach (($program['subjects'] ?? []) as $programSubject) {
          $programSubjectsById[(string) $programSubject['id']] = $programSubject;
      }
      $programPeriods = array_values(array_filter(($periods ?? []), static fn (array $period): bool => (string) ($period['school_id'] ?? '') === (string) ($program['school_id'] ?? '')));
    ?>
    <div id="program-edit-<?= htmlspecialchars((string) $program['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="program-edit-title-<?= htmlspecialchars((string) $program['id']) ?>" hidden>
      <div class="modal modal-lg app-modal">
        <div class="modal-head">
          <div>
            <div id="program-edit-title-<?= htmlspecialchars((string) $program['id']) ?>" class="modal-title">Opleiding bewerken</div>
            <div class="muted text-sm">Werk de opleiding en het vakkenpakket bij.</div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/opleidingen/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="opleiding_id" value="<?= htmlspecialchars((string) $program['id']) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $program['school_id']) ?>">

          <div class="modal-body">
            <div class="app-modal-grid">
              <div class="form-group">
                <label class="form-label">School</label>
                <input class="form-input" type="text" value="<?= htmlspecialchars((string) $program['school_naam']) ?>" disabled>
              </div>

              <div class="form-group">
                <label class="form-label">Opleiding</label>
                <input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $program['naam']) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="<?= htmlspecialchars((string) ($program['code'] ?? '')) ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Niveau</label>
                <input class="form-input" type="text" name="niveau" value="<?= htmlspecialchars((string) ($program['niveau'] ?? '')) ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Status</label>
                <label class="modal-picker-item">
                  <input type="hidden" name="active" value="0">
                  <input type="checkbox" name="active" value="1" <?= !empty($program['active']) ? 'checked' : '' ?>>
                  <span><strong>Actief</strong><small>Beschikbaar voor klassen</small></span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Vakkenpakket</label>
              <div class="program-subject-hours-list">
                <?php if (!empty($programPeriods)): ?>
                  <div class="program-subject-hours-head" style="--period-count: <?= max(1, count($programPeriods)) ?>">
                    <span>Vak</span>
                    <span>Keuze</span>
                    <?php foreach ($programPeriods as $period): ?>
                      <span><?= htmlspecialchars((string) $period['naam']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php foreach (($subjects ?? []) as $subject): ?>
                  <?php $existingSubject = $programSubjectsById[(string) $subject['id']] ?? null; ?>
                  <div class="program-subject-hours-row" style="--period-count: <?= max(1, count($programPeriods)) ?>">
                    <label class="program-subject-check">
                      <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= in_array((string) $subject['id'], $programSubjectIds, true) ? 'checked' : '' ?>>
                      <span>
                        <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                        <?php if (!empty($subject['code'])): ?>
                          <small><?= htmlspecialchars((string) $subject['code']) ?></small>
                        <?php endif; ?>
                      </span>
                    </label>
                    <label class="program-elective-check" title="Leerlingen kunnen dit vak kiezen">
                      <input type="checkbox" name="elective_subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= !empty($existingSubject['keuzevak']) ? 'checked' : '' ?>>
                    </label>
                    <?php foreach ($programPeriods as $period): ?>
                      <?php $hours = (int) (($existingSubject['periode_uren'] ?? [])[(string) $period['id']] ?? ($existingSubject['uren_per_week'] ?? 0)); ?>
                      <input class="form-input subject-hours-input" type="number" name="subject_hours[<?= htmlspecialchars((string) $subject['id']) ?>][<?= htmlspecialchars((string) $period['id']) ?>]" min="0" max="40" value="<?= $hours ?>" aria-label="<?= htmlspecialchars((string) $subject['naam']) ?> <?= htmlspecialchars((string) $period['naam']) ?> uren per week">
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                  <span class="muted text-sm">Maak eerst vakken aan.</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Opleiding opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (($activeTab ?? 'vakken') === 'leraren'): ?>
  <div id="teacher-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="teacher-create-title" hidden>
    <div class="modal modal-xl app-modal">
      <div class="modal-head">
        <div>
          <div id="teacher-create-title" class="modal-title">Leraar aanmaken</div>
          <div class="muted text-sm">Maak direct een account aan en leg vakken en werkuren vast.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/leraren">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <div class="modal-body">
          <div class="app-modal-grid teacher-modal-grid">
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
              <label class="form-label">Naam</label>
              <input class="form-input" type="text" name="name" placeholder="Voornaam Achternaam" required>
            </div>
            <div class="form-group">
              <label class="form-label">E-mail</label>
              <input class="form-input" type="email" name="email" placeholder="leraar@roostar.local" required>
            </div>
            <div class="form-group">
              <label class="form-label">Wachtwoord</label>
              <input class="form-input" type="password" name="password" minlength="8" required>
            </div>
            <div class="form-group">
              <label class="form-label">Berekende uren</label>
              <div class="calculated-hours" data-teacher-hours-summary>40 uur/week · max 8 per dag</div>
            </div>
          </div>

          <div class="teacher-editor-grid">
            <div>
              <div class="form-label">Vakken</div>
              <div class="teacher-subject-list">
                <?php foreach (($subjects ?? []) as $subject): ?>
                  <label class="modal-picker-item">
                    <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>">
                    <span>
                      <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                      <small><?= htmlspecialchars((string) ($subject['code'] ?? '')) ?></small>
                    </span>
                  </label>
                <?php endforeach; ?>
                <?php if (empty($subjects)): ?>
                  <span class="muted text-sm">Maak eerst vakken aan.</span>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <div class="form-label">Werkuren</div>
              <div class="teacher-availability-grid" style="--day-count: <?= count($teacherDays) ?>" data-teacher-availability-grid>
                <div class="teacher-availability-corner">Uur</div>
                <?php foreach ($teacherDays as $label): ?>
                  <div class="teacher-availability-head"><?= htmlspecialchars($label) ?></div>
                <?php endforeach; ?>
                <?php foreach ($teacherPeriods as $period): ?>
                  <div class="teacher-availability-hour"><?= htmlspecialchars((string) $period) ?></div>
                  <?php foreach (array_keys($teacherDays) as $dayKey): ?>
                    <?php $slotKey = $dayKey . '-' . $period; ?>
                    <label class="teacher-slot-check" title="<?= htmlspecialchars($teacherDayLabels[$dayKey] . ' uur ' . $period) ?>">
                      <input type="checkbox" name="available_slots[]" value="<?= htmlspecialchars($slotKey) ?>" checked>
                    </label>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Leraar aanmaken</button>
        </div>
      </form>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Leraren</div>
        <div class="muted text-sm">Account, bevoegdheden en roosterprofiel staan hier bij elkaar.</div>
      </div>
      <div class="view-actions">
        <button class="btn btn-dark" type="button" data-open-modal="teacher-create-modal">Nieuwe leraar</button>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Naam</th>
            <th>E-mail</th>
            <th>School</th>
            <th>Vakken</th>
            <th>Uren</th>
            <th>Status</th>
            <th>Acties</th>
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
              <td>
                <div class="inline-pill-list">
                  <?php foreach (($teacher['subjects'] ?? []) as $subject): ?>
                    <span class="soft-pill"><?= htmlspecialchars((string) ($subject['code'] ?: $subject['naam'])) ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($teacher['subjects'])): ?>
                    <span class="muted">Geen vakken</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="muted"><?= htmlspecialchars((string) $teacher['max_uren_per_week']) ?> wk · <?= htmlspecialchars((string) $teacher['max_uren_per_dag']) ?> dag</td>
              <td><span class="status <?= !empty($teacher['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($teacher['active']) ? 'Actief' : 'Inactief' ?></span></td>
              <td class="actions-cell">
                <div class="table-actions">
                  <button class="btn btn-outline btn-sm" type="button" data-open-modal="teacher-edit-<?= htmlspecialchars((string) $teacher['id']) ?>">Bewerken</button>
                  <form method="post" action="/leraren/verwijder">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                    <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $teacher['school_id']) ?>">
                    <input type="hidden" name="teacher_id" value="<?= htmlspecialchars((string) $teacher['id']) ?>">
                    <button class="btn btn-ghost btn-sm btn-danger-link" type="submit">Verwijderen</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($teachers)): ?>
            <tr><td colspan="7" class="muted">Nog geen leraren gevonden.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach (($teachers ?? []) as $teacher): ?>
    <?php
      $teacherSubjectIds = array_map(static fn (array $subject): string => (string) $subject['id'], $teacher['subjects'] ?? []);
      $teacherAvailableSlots = $teacher['available_slots'] ?? $allTeacherSlots;
      $teacherAvailableSlots = is_array($teacherAvailableSlots) ? $teacherAvailableSlots : $allTeacherSlots;
    ?>
    <div id="teacher-edit-<?= htmlspecialchars((string) $teacher['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="teacher-edit-title-<?= htmlspecialchars((string) $teacher['id']) ?>" hidden>
      <div class="modal modal-xl app-modal">
        <div class="modal-head">
          <div>
            <div id="teacher-edit-title-<?= htmlspecialchars((string) $teacher['id']) ?>" class="modal-title">Leraar bewerken</div>
            <div class="muted text-sm"><?= htmlspecialchars((string) $teacher['email']) ?></div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form method="post" action="/leraren/bewerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) $teacher['school_id']) ?>">
          <input type="hidden" name="teacher_id" value="<?= htmlspecialchars((string) $teacher['id']) ?>">
          <div class="modal-body">
            <div class="app-modal-grid teacher-modal-grid">
              <div class="form-group">
                <label class="form-label">Naam</label>
                <input class="form-input" type="text" name="name" value="<?= htmlspecialchars((string) $teacher['naam']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">E-mail</label>
                <input class="form-input" type="email" name="email" value="<?= htmlspecialchars((string) $teacher['email']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Berekende uren</label>
                <div class="calculated-hours" data-teacher-hours-summary><?= htmlspecialchars((string) $teacher['max_uren_per_week']) ?> uur/week · max <?= htmlspecialchars((string) $teacher['max_uren_per_dag']) ?> per dag</div>
              </div>
              <label class="modal-picker-item">
                <input type="checkbox" name="active" value="1" <?= !empty($teacher['active']) ? 'checked' : '' ?>>
                <span>
                  <strong>Actief</strong>
                  <small>Beschikbaar in de roosterplanning</small>
                </span>
              </label>
            </div>

            <div class="teacher-editor-grid">
              <div>
                <div class="form-label">Vakken</div>
                <div class="teacher-subject-list">
                  <?php foreach (($subjects ?? []) as $subject): ?>
                    <label class="modal-picker-item">
                      <input type="checkbox" name="subject_ids[]" value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= in_array((string) $subject['id'], $teacherSubjectIds, true) ? 'checked' : '' ?>>
                      <span>
                        <strong><?= htmlspecialchars((string) $subject['naam']) ?></strong>
                        <small><?= htmlspecialchars((string) ($subject['code'] ?? '')) ?></small>
                      </span>
                    </label>
                  <?php endforeach; ?>
                  <?php if (empty($subjects)): ?>
                    <span class="muted text-sm">Maak eerst vakken aan.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div>
                <div class="form-label">Werkuren</div>
                <div class="teacher-availability-grid" style="--day-count: <?= count($teacherDays) ?>" data-teacher-availability-grid>
                  <div class="teacher-availability-corner">Uur</div>
                  <?php foreach ($teacherDays as $label): ?>
                    <div class="teacher-availability-head"><?= htmlspecialchars($label) ?></div>
                  <?php endforeach; ?>
                  <?php foreach ($teacherPeriods as $period): ?>
                    <div class="teacher-availability-hour"><?= htmlspecialchars((string) $period) ?></div>
                    <?php foreach (array_keys($teacherDays) as $dayKey): ?>
                      <?php $slotKey = $dayKey . '-' . $period; ?>
                      <label class="teacher-slot-check" title="<?= htmlspecialchars($teacherDayLabels[$dayKey] . ' uur ' . $period) ?>">
                        <input type="checkbox" name="available_slots[]" value="<?= htmlspecialchars($slotKey) ?>" <?= in_array($slotKey, $teacherAvailableSlots, true) ? 'checked' : '' ?>>
                      </label>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <button class="btn btn-dark" type="submit">Leraar opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
