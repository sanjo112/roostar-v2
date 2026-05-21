<?php
$schools = $schools ?? [];
$teachers = $teachers ?? [];
$absences = $absences ?? [];
$impact = $impact ?? ['absence' => null, 'lessons' => [], 'summary' => ['lessen' => 0, 'opgevangen' => 0, 'uitgeroosterd' => 0, 'open' => 0, 'klassen' => 0, 'dagen' => 0]];
$summary = $impact['summary'] ?? ['lessen' => 0, 'opgevangen' => 0, 'uitgeroosterd' => 0, 'open' => 0, 'klassen' => 0, 'dagen' => 0];
$selectedAbsence = $impact['absence'] ?? null;
$singleSchoolId = count($schools) === 1 ? (string) ($schools[0]['id'] ?? '') : '';
$selectedTeacherId = $selectedAbsence ? (string) ($selectedAbsence['leraar_id'] ?? '') : '';
$selectedDateFrom = $selectedAbsence ? (string) ($selectedAbsence['datum_van'] ?? $today) : (string) $today;
$impactDates = array_values(array_filter(array_map(static fn (array $lesson): string => (string) ($lesson['datum'] ?? ''), $impact['lessons'] ?? [])));
$selectedDateTo = $selectedAbsence && !empty($selectedAbsence['datum_tot'])
    ? (string) $selectedAbsence['datum_tot']
    : ($impactDates !== [] ? max($impactDates) : $selectedDateFrom);
?>

<section class="generation-page">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Ziekte</div>
      <h1 class="page-title">Ziekte en vervanging</h1>
      <p class="muted">Meld een leraar ziek en zie direct welke lessen in het opgeslagen rooster geraakt worden.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= count($absences) ?> actief</strong>
        <span><?= (int) ($summary['open'] ?? 0) ?> open lessen in selectie</span>
      </div>
    </div>
  </div>

  <section class="card overview-card">
    <div class="overview-head">
      <div>
        <div class="eyebrow">Nieuwe ziekmelding</div>
        <div class="sync">Zonder einddatum tonen we de komende 14 schooldagen binnen periodes.</div>
      </div>
    </div>

    <form method="post" action="/ziekte">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
      <div class="manual-form-grid">
        <div class="form-group">
          <label class="form-label">School</label>
          <select class="form-select" name="school_id" required>
            <?php if ($singleSchoolId === ''): ?>
              <option value="">Kies school</option>
            <?php endif; ?>
            <?php foreach ($schools as $school): ?>
              <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $school['naam']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Leraar</label>
          <select class="form-select" name="leraar_id" required>
            <option value="">Kies leraar</option>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= htmlspecialchars((string) $teacher['id']) ?>">
                <?= htmlspecialchars((string) $teacher['naam']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Vanaf</label>
          <input class="form-input" type="date" name="datum_van" value="<?= htmlspecialchars((string) $today) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tot en met</label>
          <input class="form-input" type="date" name="datum_tot">
        </div>
        <div class="form-group">
          <label class="form-label">Opmerking</label>
          <input class="form-input" type="text" name="opmerking" placeholder="Bijv. griep, vervolg onbekend">
        </div>
      </div>
      <div class="generation-actions">
        <button class="btn btn-dark generation-button" type="submit">Ziek melden</button>
      </div>
    </form>
  </section>

  <section class="row-grid sickness-layout mt-card">
    <div class="card tasks-card">
      <div class="tasks-head">
        <div>
          <div class="eyebrow">Actieve ziekmeldingen</div>
          <div class="muted text-sm"><?= count($absences) ?> melding(en)</div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Leraar</th>
              <th>Periode</th>
              <th>Impact</th>
              <th>Acties</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($absences as $absence): ?>
              <tr class="<?= (string) $selectedAbsenceId === (string) $absence['id'] ? 'row-selected' : '' ?>">
                <td>
                  <a class="table-link" href="/ziekte?id=<?= urlencode((string) $absence['id']) ?>">
                    <strong><?= htmlspecialchars((string) $absence['leraar_naam']) ?></strong>
                    <?php if (!empty($absence['opmerking'])): ?>
                      <span class="muted text-sm"><?= htmlspecialchars((string) $absence['opmerking']) ?></span>
                    <?php endif; ?>
                  </a>
                </td>
                <td class="muted">
                  <?= htmlspecialchars(date('d-m-Y', strtotime((string) $absence['datum_van']))) ?>
                  <?= !empty($absence['datum_tot']) ? 't/m ' . htmlspecialchars(date('d-m-Y', strtotime((string) $absence['datum_tot']))) : 'open' ?>
                </td>
                <td>
                  <div class="impact-badges">
                    <span class="badge coral"><?= (int) ($absence['impact']['open'] ?? 0) ?> open</span>
                    <span class="badge green"><?= (int) ($absence['impact']['opgevangen'] ?? 0) ?> opgevangen</span>
                    <span class="badge navy"><?= (int) ($absence['impact']['uitgeroosterd'] ?? 0) ?> uitgeroosterd</span>
                  </div>
                </td>
                <td>
                  <form method="post" action="/ziekte/hersteld" class="action-row-sm">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                    <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $absence['id']) ?>">
                    <input type="hidden" name="datum_tot" value="<?= htmlspecialchars((string) $today) ?>">
                    <a class="btn btn-outline btn-sm" href="/ziekte?id=<?= urlencode((string) $absence['id']) ?>">Impact</a>
                    <button class="btn btn-dark btn-sm" type="submit">Hersteld</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($absences === []): ?>
              <tr><td colspan="4" class="muted">Geen actieve ziekmeldingen.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card tasks-card">
      <div class="tasks-head">
        <div>
          <div class="eyebrow">Impact</div>
          <div class="muted text-sm">
            <?= $selectedAbsence ? htmlspecialchars((string) $selectedAbsence['leraar_naam']) : 'Selecteer een ziekmelding' ?>
          </div>
        </div>
      </div>

      <div class="impact-summary">
        <div><strong><?= (int) ($summary['lessen'] ?? 0) ?></strong><span>Lessen</span></div>
        <div><strong><?= (int) ($summary['opgevangen'] ?? 0) ?></strong><span>Opgevangen</span></div>
        <div><strong><?= (int) ($summary['uitgeroosterd'] ?? 0) ?></strong><span>Uitgeroosterd</span></div>
        <div><strong><?= (int) ($summary['open'] ?? 0) ?></strong><span>Open</span></div>
      </div>

      <?php if ($selectedAbsence && !empty($impact['lessons'])): ?>
        <details class="replacement-range-panel">
          <summary class="btn btn-outline btn-sm">Langdurige vervanging</summary>
          <form method="post" action="/ziekte/vervanging/langdurig" class="replacement-range-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
            <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $selectedAbsenceId) ?>">
            <div class="replacement-range-head">
              <strong>Langdurige vervanging</strong>
              <span>Pas meerdere lessen in deze ziekmelding tegelijk aan.</span>
            </div>
            <div class="replacement-range-grid">
              <div class="form-group">
                <label class="form-label">Vervanger</label>
                <select class="form-select" name="vervanger_id" required>
                  <option value="">Kies vervanger</option>
                  <?php foreach ($teachers as $teacher): ?>
                    <?php if ((string) $teacher['id'] === $selectedTeacherId) { continue; } ?>
                    <option value="<?= htmlspecialchars((string) $teacher['id']) ?>">
                      <?= htmlspecialchars((string) $teacher['naam']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Vanaf</label>
                <input class="form-input" type="date" name="datum_van" value="<?= htmlspecialchars($selectedDateFrom) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label">Tot en met</label>
                <input class="form-input" type="date" name="datum_tot" value="<?= htmlspecialchars($selectedDateTo) ?>">
              </div>
              <button class="btn btn-dark btn-sm" type="submit">Toepassen</button>
            </div>
            <div class="lesson-hour-checks" aria-label="Lesuren">
              <?php for ($hour = 1; $hour <= 9; $hour++): ?>
                <label>
                  <input type="checkbox" name="uren[]" value="<?= $hour ?>" checked>
                  <span>Uur <?= $hour ?></span>
                </label>
              <?php endfor; ?>
            </div>
          </form>
        </details>
      <?php endif; ?>

      <div class="impact-list">
        <?php foreach (($impact['lessons'] ?? []) as $lesson): ?>
          <?php
            $isCancelled = (string) ($lesson['oplossing'] ?? '') === 'uitgeroosterd';
            $isCovered = !$isCancelled && !empty($lesson['vervanger_id']);
          ?>
          <div class="impact-item <?= $isCancelled ? 'is-cancelled' : ($isCovered ? 'is-covered' : '') ?>">
            <div class="impact-item-head">
              <div>
                <strong><?= htmlspecialchars((string) $lesson['vak']) ?></strong>
                <span><?= htmlspecialchars((string) $lesson['klas']) ?> · <?= htmlspecialchars((string) $lesson['periode']) ?></span>
              </div>
              <div class="impact-meta">
                <span><?= htmlspecialchars(date('d-m-Y', strtotime((string) $lesson['datum']))) ?></span>
                <span><?= htmlspecialchars((string) $lesson['dag']) ?> uur <?= (int) $lesson['lesuur'] ?></span>
                <span><?= htmlspecialchars((string) $lesson['lokaal']) ?></span>
              </div>
            </div>

            <?php if ($isCancelled): ?>
              <div class="impact-replacement">
                <span>Oplossing</span>
                <strong>Uitgeroosterd</strong>
                <form method="post" action="/ziekte/vervanging/verwijder" class="action-row-sm subtle-top">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $selectedAbsenceId) ?>">
                  <input type="hidden" name="les_id" value="<?= htmlspecialchars((string) $lesson['id']) ?>">
                  <input type="hidden" name="datum" value="<?= htmlspecialchars((string) $lesson['datum']) ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Opnieuw openzetten</button>
                </form>
              </div>
            <?php elseif ($isCovered): ?>
              <div class="impact-replacement">
                <span>Vervanger</span>
                <strong><?= htmlspecialchars((string) $lesson['vervanger_naam']) ?></strong>
                <form method="post" action="/ziekte/vervanging/verwijder" class="action-row-sm subtle-top">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $selectedAbsenceId) ?>">
                  <input type="hidden" name="les_id" value="<?= htmlspecialchars((string) $lesson['id']) ?>">
                  <input type="hidden" name="datum" value="<?= htmlspecialchars((string) $lesson['datum']) ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Verwijderen</button>
                </form>
              </div>
            <?php else: ?>
              <div class="replacement-choice-row">
                <form method="post" action="/ziekte/vervanging" class="replacement-inline-form">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $selectedAbsenceId) ?>">
                  <input type="hidden" name="les_id" value="<?= htmlspecialchars((string) $lesson['id']) ?>">
                  <input type="hidden" name="datum" value="<?= htmlspecialchars((string) $lesson['datum']) ?>">
                  <select class="form-select" name="vervanger_id" required>
                    <option value="">Kies vervanger</option>
                    <?php foreach (($lesson['vervangers'] ?? []) as $replacement): ?>
                      <option value="<?= htmlspecialchars((string) $replacement['id']) ?>">
                        <?= htmlspecialchars((string) $replacement['naam']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-dark btn-sm" type="submit" <?= empty($lesson['vervangers']) ? 'disabled' : '' ?>>Vervangen</button>
                </form>
                <form method="post" action="/ziekte/uitroosteren" class="cancel-inline-form">
                  <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                  <input type="hidden" name="ziekte_id" value="<?= htmlspecialchars((string) $selectedAbsenceId) ?>">
                  <input type="hidden" name="les_id" value="<?= htmlspecialchars((string) $lesson['id']) ?>">
                  <input type="hidden" name="datum" value="<?= htmlspecialchars((string) $lesson['datum']) ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Uitroosteren</button>
                </form>
              </div>
              <?php if (empty($lesson['vervangers'])): ?>
                <span class="muted text-sm">Geen bevoegde beschikbare vervanger gevonden.</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($selectedAbsence && empty($impact['lessons'])): ?>
          <div class="impact-empty">Geen lessen geraakt in de gekozen periode.</div>
        <?php elseif (!$selectedAbsence): ?>
          <div class="impact-empty">Klik op een ziekmelding om de lessen te zien.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</section>
