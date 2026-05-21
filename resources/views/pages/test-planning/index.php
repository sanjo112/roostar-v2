<?php
$schools = $schools ?? [];
$schoolYears = $schoolYears ?? [];
$periods = $periods ?? [];
$testWeeks = $testWeeks ?? [];
$tests = $tests ?? [];
$subjects = $subjects ?? [];
$programs = $programs ?? [];
$rooms = $rooms ?? [];
$teachers = $teachers ?? [];
$selectedSchoolYearId = (string) ($selectedSchoolYearId ?? '');
$selectedTestWeekId = (string) ($selectedTestWeekId ?? '');
$singleSchoolId = count($schools) === 1 ? (string) ($schools[0]['id'] ?? '') : '';
$selectedTestWeek = null;
foreach ($testWeeks as $testWeek) {
    if ((string) $testWeek['id'] === $selectedTestWeekId) {
        $selectedTestWeek = $testWeek;
        break;
    }
}
$dayLabels = ['ma' => 'Maandag', 'di' => 'Dinsdag', 'wo' => 'Woensdag', 'do' => 'Donderdag', 'vr' => 'Vrijdag'];
$slots = [];
foreach ($dayLabels as $dayKey => $dayLabel) {
    for ($hour = 1; $hour <= 9; $hour++) {
        $slots[$dayKey . '-' . $hour] = substr($dayLabel, 0, 2) . ' uur ' . $hour;
    }
}
?>

<div id="testweek-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="testweek-create-title" hidden>
  <div class="modal modal-lg app-modal">
    <div class="modal-head">
      <div>
        <div id="testweek-create-title" class="modal-title">Toetsweek aanmaken</div>
        <div class="muted text-sm">Leg vast welke week als toetsweek telt en hoeveel regulier rooster overblijft.</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="post" action="/toetsweken">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
      <div class="modal-body">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">School</label>
            <select class="form-select" name="school_id" required>
              <?php if ($singleSchoolId === ''): ?><option value="">Kies school</option><?php endif; ?>
              <?php foreach ($schools as $school): ?>
                <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $school['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Schooljaar</label>
            <select class="form-select" name="schooljaar_id" required>
              <option value="">Kies schooljaar</option>
              <?php foreach ($schoolYears as $schoolYear): ?>
                <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>" <?= $selectedSchoolYearId === (string) $schoolYear['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $schoolYear['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Naam</label>
            <input class="form-input" type="text" name="naam" placeholder="Toetsweek 1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Week</label>
            <input class="form-input" type="number" name="week_nummer" min="1" max="53" required>
          </div>
          <div class="form-group">
            <label class="form-label">Lessen %</label>
            <input class="form-input" type="number" name="les_percentage" min="0" max="100" value="50">
          </div>
          <div class="form-group">
            <label class="form-label">Max. lesuren per dag</label>
            <input class="form-input" type="number" name="lesuren_per_dag" min="1" max="9" placeholder="Bijv. 5">
          </div>
          <label class="modal-picker-item">
            <input type="checkbox" name="verkort_rooster" value="1">
            <span><strong>Verkort rooster</strong><small>Minder lesuren of kortere dagen tijdens deze week</small></span>
          </label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
        <button class="btn btn-dark" type="submit">Toetsweek aanmaken</button>
      </div>
    </form>
  </div>
</div>

<section class="generation-page">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Toetsplanning</div>
      <h1 class="page-title">Toetsweken en surveillance</h1>
      <p class="muted">Plan toetsweken, toetsmomenten, lokalen en surveillanten.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= count($testWeeks) ?> toetsweek(en)</strong>
        <span><?= count($tests) ?> toets(en) in selectie</span>
      </div>
    </div>
  </div>

  <section class="card overview-card">
    <div class="overview-head">
      <div>
        <div class="eyebrow">Instellingen</div>
        <div class="sync">Kies een schooljaar en beheer de toetsweken binnen dat jaar.</div>
      </div>
      <div class="view-actions">
        <form method="get" action="/toetsweken" class="filter-form-inline">
          <select class="form-select" name="schooljaar_id" onchange="this.form.submit()">
            <?php foreach ($schoolYears as $schoolYear): ?>
              <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>" <?= $selectedSchoolYearId === (string) $schoolYear['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $schoolYear['naam']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
        <button class="btn btn-dark" type="button" data-open-modal="testweek-create-modal">Nieuwe toetsweek</button>
      </div>
    </div>
  </section>

  <div class="test-planning-layout">
    <section class="card tasks-card">
      <div class="tasks-head">
        <div>
          <div class="eyebrow">Toetsweken</div>
          <div class="muted text-sm">Per week stel je het rooster-effect in.</div>
        </div>
      </div>
      <div class="testweek-list">
        <?php foreach ($testWeeks as $testWeek): ?>
          <a class="testweek-card <?= (string) $testWeek['id'] === $selectedTestWeekId ? 'active' : '' ?>" href="/toetsweken?schooljaar_id=<?= rawurlencode($selectedSchoolYearId) ?>&toetsweek_id=<?= rawurlencode((string) $testWeek['id']) ?>">
            <span>
              <strong><?= htmlspecialchars((string) $testWeek['naam']) ?></strong>
              <small>Week <?= htmlspecialchars((string) $testWeek['week_nummer']) ?><?= !empty($testWeek['periode_naam']) ? ' · ' . htmlspecialchars((string) $testWeek['periode_naam']) : '' ?></small>
            </span>
            <span class="status <?= !empty($testWeek['active']) ? 'st-done' : 'st-wait' ?>"><?= !empty($testWeek['active']) ? 'Actief' : 'Inactief' ?></span>
          </a>
        <?php endforeach; ?>
        <?php if ($testWeeks === []): ?>
          <div class="empty-inline">Nog geen toetsweken voor dit schooljaar.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card tasks-card">
      <div class="tasks-head">
        <div>
          <div class="eyebrow">Planning</div>
          <h3 class="section-title"><?= $selectedTestWeek ? htmlspecialchars((string) $selectedTestWeek['naam']) : 'Geen toetsweek geselecteerd' ?></h3>
          <?php if ($selectedTestWeek): ?>
            <div class="muted text-sm">Week <?= htmlspecialchars((string) $selectedTestWeek['week_nummer']) ?> · <?= htmlspecialchars((string) $selectedTestWeek['les_percentage']) ?>% regulier rooster<?= !empty($selectedTestWeek['verkort_rooster']) ? ' · verkort rooster' : '' ?></div>
          <?php endif; ?>
        </div>
        <?php if ($selectedTestWeek): ?>
          <div class="view-actions">
            <button class="btn btn-outline" type="button" data-open-modal="testweek-edit-modal">Toetsweek bewerken</button>
            <button class="btn btn-dark" type="button" data-open-modal="test-create-modal">Toets toevoegen</button>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($selectedTestWeek): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Toets</th>
                <th>Opleiding</th>
                <th>Moment</th>
                <th>Lokaal</th>
                <th>Surveillance</th>
                <th>Acties</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tests as $test): ?>
                <?php $modalId = 'test-edit-' . htmlspecialchars((string) $test['id']); ?>
                <tr>
                  <td><strong><?= htmlspecialchars((string) $test['naam']) ?></strong><div class="muted"><?= htmlspecialchars((string) ($test['vak_code'] ?: $test['vak_naam'])) ?></div></td>
                  <td class="muted"><?= htmlspecialchars((string) ($test['opleiding_naam'] ?: 'Alle opleidingen')) ?></td>
                  <td><?= htmlspecialchars((string) ($test['datum'] ?: 'Nog geen datum')) ?><div class="muted"><?= htmlspecialchars($slots[(string) $test['tijdslot']] ?? (string) $test['tijdslot']) ?> · <?= htmlspecialchars((string) $test['duur_minuten']) ?> min</div></td>
                  <td class="muted"><?= htmlspecialchars((string) ($test['lokaal_naam'] ?: 'Nog geen lokaal')) ?></td>
                  <td>
                    <div class="chip-row">
                      <?php foreach (($test['surveillance'] ?? []) as $surveillance): ?>
                        <span class="soft-pill"><?= htmlspecialchars((string) $surveillance['naam']) ?></span>
                      <?php endforeach; ?>
                      <?php if (empty($test['surveillance'])): ?>
                        <span class="status st-warn">Geen surveillant</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="actions-cell">
                    <div class="table-actions">
                      <form method="post" action="/toetsweken/surveillance/voorstel">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                        <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars($selectedSchoolYearId) ?>">
                        <input type="hidden" name="toetsweek_id" value="<?= htmlspecialchars($selectedTestWeekId) ?>">
                        <input type="hidden" name="toets_id" value="<?= htmlspecialchars((string) $test['id']) ?>">
                        <button class="btn btn-outline btn-sm" type="submit">Voorstel</button>
                      </form>
                      <button class="btn btn-outline btn-sm" type="button" data-open-modal="<?= $modalId ?>">Bewerken</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($tests === []): ?>
                <tr><td colspan="6" class="muted">Nog geen toetsen gepland voor deze toetsweek.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty-inline">Maak of selecteer een toetsweek om toetsen te plannen.</div>
      <?php endif; ?>
    </section>
  </div>
</section>

<?php if ($selectedTestWeek): ?>
  <div id="testweek-edit-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="testweek-edit-title" hidden>
    <div class="modal modal-lg app-modal">
      <div class="modal-head">
        <div>
          <div id="testweek-edit-title" class="modal-title">Toetsweek bewerken</div>
          <div class="muted text-sm">Week <?= htmlspecialchars((string) $selectedTestWeek['week_nummer']) ?></div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
      <form method="post" action="/toetsweken/bewerk">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars($selectedSchoolYearId) ?>">
        <input type="hidden" name="toetsweek_id" value="<?= htmlspecialchars((string) $selectedTestWeek['id']) ?>">
        <div class="modal-body">
          <div class="app-modal-grid">
            <div class="form-group"><label class="form-label">Naam</label><input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) $selectedTestWeek['naam']) ?>" required></div>
            <div class="form-group"><label class="form-label">Lessen %</label><input class="form-input" type="number" name="les_percentage" min="0" max="100" value="<?= htmlspecialchars((string) $selectedTestWeek['les_percentage']) ?>"></div>
            <div class="form-group"><label class="form-label">Max. lesuren per dag</label><input class="form-input" type="number" name="lesuren_per_dag" min="1" max="9" value="<?= htmlspecialchars((string) ($selectedTestWeek['lesuren_per_dag'] ?? '')) ?>"></div>
            <label class="modal-picker-item"><input type="checkbox" name="verkort_rooster" value="1" <?= !empty($selectedTestWeek['verkort_rooster']) ? 'checked' : '' ?>><span><strong>Verkort rooster</strong><small>Minder lessen tijdens de toetsweek</small></span></label>
            <label class="modal-picker-item"><input type="checkbox" name="active" value="1" <?= !empty($selectedTestWeek['active']) ? 'checked' : '' ?>><span><strong>Actief</strong><small>Beschikbaar voor planning</small></span></label>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit">Toetsweek opslaan</button>
        </div>
      </form>
      <form method="post" action="/toetsweken/verwijder" class="modal-delete-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars($selectedSchoolYearId) ?>">
        <input type="hidden" name="toetsweek_id" value="<?= htmlspecialchars((string) $selectedTestWeek['id']) ?>">
        <button class="btn btn-ghost btn-sm btn-danger-link" type="submit">Toetsweek verwijderen</button>
      </form>
    </div>
  </div>

  <?php
    $testForm = [
      'id' => '',
      'vak_id' => '',
      'opleiding_id' => '',
      'naam' => '',
      'datum' => '',
      'tijdslot' => 'ma-1',
      'duur_minuten' => 50,
      'lokaal_id' => '',
      'aantal_surveillance' => 1,
      'surveillance' => [],
    ];
    $testModals = [['modal_id' => 'test-create-modal', 'title' => 'Toets toevoegen', 'test' => $testForm]];
    foreach ($tests as $test) {
        $testModals[] = ['modal_id' => 'test-edit-' . (string) $test['id'], 'title' => 'Toets bewerken', 'test' => $test];
    }
  ?>
  <?php foreach ($testModals as $modal): ?>
    <?php $test = $modal['test']; $selectedTeacherIds = array_map(static fn (array $item): string => (string) $item['id'], $test['surveillance'] ?? []); ?>
    <div id="<?= htmlspecialchars((string) $modal['modal_id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars((string) $modal['modal_id']) ?>-title" hidden>
      <div class="modal modal-xl app-modal">
        <div class="modal-head">
          <div>
            <div id="<?= htmlspecialchars((string) $modal['modal_id']) ?>-title" class="modal-title"><?= htmlspecialchars((string) $modal['title']) ?></div>
            <div class="muted text-sm"><?= htmlspecialchars((string) $selectedTestWeek['naam']) ?></div>
          </div>
          <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <form method="post" action="/toetsen">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="schooljaar_id" value="<?= htmlspecialchars($selectedSchoolYearId) ?>">
          <input type="hidden" name="toetsweek_id" value="<?= htmlspecialchars($selectedTestWeekId) ?>">
          <input type="hidden" name="toets_id" value="<?= htmlspecialchars((string) ($test['id'] ?? '')) ?>">
          <div class="modal-body">
            <div class="toets-form-grid toets-form-grid-2">
              <div class="form-group"><label class="form-label">Vak</label><select class="form-select" name="vak_id" required><option value="">Kies vak</option><?php foreach ($subjects as $subject): ?><option value="<?= htmlspecialchars((string) $subject['id']) ?>" <?= (string) ($test['vak_id'] ?? '') === (string) $subject['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($subject['code'] ? $subject['code'] . ' · ' : '') . $subject['naam']) ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label class="form-label">Opleiding</label><select class="form-select" name="opleiding_id"><option value="">Alle opleidingen</option><?php foreach ($programs as $program): ?><option value="<?= htmlspecialchars((string) $program['id']) ?>" <?= (string) ($test['opleiding_id'] ?? '') === (string) $program['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $program['naam']) ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label class="form-label">Naam toets</label><input class="form-input" type="text" name="naam" value="<?= htmlspecialchars((string) ($test['naam'] ?? '')) ?>" placeholder="Bijv. SE Nederlands" required></div>
              <div class="form-group"><label class="form-label">Lokaal</label><select class="form-select" name="lokaal_id"><option value="">Nog geen lokaal</option><?php foreach ($rooms as $room): ?><option value="<?= htmlspecialchars((string) $room['id']) ?>" <?= (string) ($test['lokaal_id'] ?? '') === (string) $room['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $room['naam']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="toets-form-grid toets-form-grid-3">
              <div class="form-group"><label class="form-label">Datum</label><input class="form-input" type="date" name="datum" value="<?= htmlspecialchars((string) ($test['datum'] ?? '')) ?>"></div>
              <div class="form-group"><label class="form-label">Tijdslot</label><select class="form-select" name="tijdslot" required><?php foreach ($slots as $slot => $label): ?><option value="<?= htmlspecialchars($slot) ?>" <?= (string) ($test['tijdslot'] ?? 'ma-1') === $slot ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
              <div class="form-group"><label class="form-label">Duur</label><input class="form-input" type="number" name="duur_minuten" min="10" max="240" value="<?= htmlspecialchars((string) ($test['duur_minuten'] ?? 50)) ?>"></div>
            </div>
            <div class="toets-form-grid toets-form-grid-2">
              <div class="form-group"><label class="form-label">Benodigde surveillanten</label><input class="form-input" type="number" name="aantal_surveillance" min="1" max="10" value="<?= htmlspecialchars((string) ($test['aantal_surveillance'] ?? 1)) ?>"></div>
              <div class="form-group"><label class="form-label">Surveillanten</label><div class="modal-picker-list compact-picker"><?php foreach ($teachers as $teacher): ?><label class="modal-picker-item"><input type="checkbox" name="leraar_ids[]" value="<?= htmlspecialchars((string) $teacher['id']) ?>" <?= in_array((string) $teacher['id'], $selectedTeacherIds, true) ? 'checked' : '' ?>><span><strong><?= htmlspecialchars((string) $teacher['naam']) ?></strong><small><?= htmlspecialchars((string) $teacher['email']) ?></small></span></label><?php endforeach; ?></div></div>
            </div>
          </div>
          <div class="modal-foot">
            <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
            <?php if (!empty($test['id'])): ?>
              <button class="btn btn-ghost btn-danger-link" type="submit" formaction="/toetsen/verwijder">Verwijderen</button>
            <?php endif; ?>
            <button class="btn btn-dark" type="submit">Toets opslaan</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
