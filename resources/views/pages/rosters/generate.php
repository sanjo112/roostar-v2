<section class="generation-page">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Rooster genereren</div>
      <h1 class="page-title">Weekrooster voorstel</h1>
      <p class="muted">Genereer tegelijk een rooster voor alle actieve klassen binnen de gekozen periode.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= $generated ? (!empty($generated['stored']) ? 'Concept opgeslagen' : 'Voorstel klaar') : 'Klaar voor generatie' ?></strong>
        <span><?= $generated ? 'Het rooster staat hieronder in de opgeslagen overview' : 'Kies een periode en start de generator' ?></span>
      </div>
      <div class="generation-orb-shell" aria-hidden="true">
        <span class="generation-header-status-video"></span>
      </div>
    </div>
  </div>

  <div class="generation-dashboard has-context-side">
    <div class="generation-main-column">
      <section class="card generation-panel">
        <div class="generation-panel-head">
          <div>
            <div class="eyebrow">Instellingen</div>
            <h2>Generator</h2>
          </div>
        </div>

        <form method="post" action="/roosters/genereren">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <input type="hidden" name="school_id" value="<?= htmlspecialchars((string) ($classesForSchoolYear[0]['school_id'] ?? '')) ?>">

          <div class="generation-control">
            <div class="form-group">
              <label class="form-label">Schooljaar</label>
              <select class="form-select" name="schooljaar_id">
                <?php foreach (($schoolYears ?? []) as $schoolYear): ?>
                  <option value="<?= htmlspecialchars((string) $schoolYear['id']) ?>" <?= (string) $schoolYear['id'] === (string) $selectedSchoolYearId ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $schoolYear['naam']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Periode</label>
              <select class="form-select" name="periode_id" required>
                <?php foreach (($periods ?? []) as $period): ?>
                  <option value="<?= htmlspecialchars((string) $period['id']) ?>" <?= (string) $period['id'] === (string) $selectedPeriodId ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $period['schooljaar_naam']) ?> · <?= htmlspecialchars((string) $period['naam']) ?> · <?= htmlspecialchars((string) ($period['week_van_jaar'] ?? '')) ?> wk <?= htmlspecialchars((string) $period['week_van']) ?> - <?= htmlspecialchars((string) ($period['week_tot_jaar'] ?? '')) ?> wk <?= htmlspecialchars((string) $period['week_tot']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="generation-actions">
            <button class="btn btn-dark generation-button" type="submit">Rooster genereren</button>
          </div>
        </form>
      </section>

      <?php $overviewViews = $generated['views'] ?? []; ?>
      <?php if (empty($overviewViews['class'] ?? [])): ?>
        <section class="card schedule-card">
          <div class="schedule-head">
            <div>
              <h2 class="schedule-title">Roosterpreview</h2>
              <div class="schedule-sub">Nog geen rooster gegenereerd</div>
            </div>
          </div>
          <div class="schedule-grid-layer">
            <div class="calendar-empty-overlay">
              <div>
                <strong>Geen preview</strong>
                <span>Start de generator om voorstellen voor alle klassen te zien.</span>
              </div>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?php if (!empty($overviewViews['class'] ?? [])): ?>
        <section class="card schedule-card roster-overview" id="generated-roster-overview">
          <div class="schedule-head">
            <div>
              <h2 class="schedule-title" data-roster-title><?= htmlspecialchars((string) ($overviewViews['class'][0]['label'] ?? 'Roosterpreview')) ?></h2>
              <div class="schedule-sub" data-roster-sub><?= htmlspecialchars((string) ($overviewViews['class'][0]['sub'] ?? '')) ?><?= !empty($overviewViews['class'][0]['sub'] ?? '') ? ' · ' : '' ?>Opgeslagen conceptrooster<?= !empty($generated['period']['naam']) ? ' · ' . htmlspecialchars((string) $generated['period']['naam']) : '' ?></div>
            </div>
            <div class="schedule-head-actions roster-overview-controls">
              <div class="roster-target-controls">
                <select class="form-select" data-roster-mode aria-label="Roosterweergave">
                  <option value="class">Klas</option>
                  <option value="teacher">Leraar</option>
                  <option value="room">Lokaal</option>
                </select>
                <span class="control-cluster">
                  <button class="icon-btn" type="button" data-roster-prev aria-label="Vorige">&lt;</button>
                  <select class="form-select" data-roster-target aria-label="Kies rooster"></select>
                  <button class="icon-btn" type="button" data-roster-next aria-label="Volgende">&gt;</button>
                </span>
              </div>
            </div>
          </div>

          <div class="schedule-grid-wrap">
            <div class="schedule-grid-layer">
              <table class="schedule-grid">
                <colgroup>
                  <col class="schedule-time-col">
                  <col span="5">
                </colgroup>
                <thead>
                  <tr>
                    <th>Tijd</th>
                    <?php foreach (($generated['days'] ?? []) as $day): ?>
                      <th><?= htmlspecialchars((string) $day) ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody data-roster-body>
                  <?php foreach (($generated['periods'] ?? []) as $periodIndex => $period): ?>
                    <tr>
                      <td class="time-cell">
                        <strong><?= htmlspecialchars((string) $period[0]) ?></strong>
                        <span><?= htmlspecialchars((string) $period[1]) ?></span>
                      </td>
                      <?php for ($dayIndex = 0; $dayIndex < 5; $dayIndex++): ?>
                        <td class="editor-drop-cell" data-roster-cell="<?= (int) $periodIndex ?>-<?= (int) $dayIndex ?>"><span class="drop-cell-tint"></span></td>
                      <?php endfor; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
        <script>
          (() => {
            const root = document.getElementById('generated-roster-overview');
            if (!root) return;

            const views = <?= json_encode($overviewViews, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const periodName = <?= json_encode((string) ($generated['period']['naam'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const csrfToken = <?= json_encode((string) $csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const modeSelect = root.querySelector('[data-roster-mode]');
            const targetSelect = root.querySelector('[data-roster-target]');
            const previousButton = root.querySelector('[data-roster-prev]');
            const nextButton = root.querySelector('[data-roster-next]');
            const title = root.querySelector('[data-roster-title]');
            const sub = root.querySelector('[data-roster-sub]');
            const cells = root.querySelectorAll('[data-roster-cell]');
            let mode = 'class';
            let index = 0;
            let draggedLessonId = null;
            let lessonStore = [];

            const labels = { class: 'Klas', teacher: 'Leraar', room: 'Lokaal' };
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#039;',
            })[char]);

            const collectLessons = () => {
              const unique = new Map();
              (views.class || []).forEach((item) => {
                Object.values(item.lessons || {}).forEach((dayMap) => {
                  Object.values(dayMap || {}).forEach((lessons) => {
                    (lessons || []).forEach((lesson) => unique.set(lesson.id, lesson));
                  });
                });
              });
              lessonStore = Array.from(unique.values());
            };

            const slotKey = (periodIndex, dayIndex) => `${periodIndex}-${dayIndex}`;
            const currentItems = () => views[mode] || [];
            const activeItem = () => currentItems()[index] || null;

            const itemContainsLesson = (item, lesson) => {
              if (!item || !lesson) return false;
              if (mode === 'class') return item.id === lesson.classId;
              if (mode === 'teacher') return item.id === lesson.teacherId;
              return item.id === lesson.roomId;
            };

            const visibleLessonsForCell = (item, periodIndex, dayIndex) => lessonStore.filter((lesson) => (
              itemContainsLesson(item, lesson)
              && Number(lesson.periodIndex) === Number(periodIndex)
              && Number(lesson.dayIndex) === Number(dayIndex)
            ));

            const validateMove = (lesson, periodIndex, dayIndex) => {
              if (!lesson) return { state: 'blocked', reason: 'Geen les geselecteerd' };
              if (Number(lesson.periodIndex) === Number(periodIndex) && Number(lesson.dayIndex) === Number(dayIndex)) {
                return { state: 'current', reason: 'Huidige positie' };
              }

              const conflicts = lessonStore.filter((candidate) => (
                candidate.id !== lesson.id
                && Number(candidate.periodIndex) === Number(periodIndex)
                && Number(candidate.dayIndex) === Number(dayIndex)
                && (
                  candidate.classId === lesson.classId
                  || candidate.teacherId === lesson.teacherId
                  || candidate.roomId === lesson.roomId
                )
              ));

              if (conflicts.length > 0) {
                const conflict = conflicts[0];
                const reasons = [];
                if (conflict.classId === lesson.classId) reasons.push('klas bezet');
                if (conflict.teacherId === lesson.teacherId) reasons.push('leraar bezet');
                if (conflict.roomId === lesson.roomId) reasons.push('lokaal bezet');
                return { state: 'blocked', reason: reasons.join(', ') };
              }

              return { state: 'ok', reason: 'Past op dit uur' };
            };

            const clearDragState = () => {
              cells.forEach((cell) => {
                cell.classList.remove('drag-target', 'drag-ok', 'drag-current', 'drag-warning', 'drag-blocked');
                cell.removeAttribute('title');
              });
            };

            const markDropCells = () => {
              const lesson = lessonStore.find((candidate) => candidate.id === draggedLessonId);
              cells.forEach((cell) => {
                const [periodIndex, dayIndex] = cell.dataset.rosterCell.split('-').map(Number);
                const validation = validateMove(lesson, periodIndex, dayIndex);
                cell.classList.add('drag-target', `drag-${validation.state}`);
                cell.title = validation.reason;
              });
            };

            const persistMove = async (lesson, periodIndex, dayIndex) => {
              const body = new URLSearchParams();
              body.set('_token', csrfToken);
              body.set('lesson_id', lesson.id);
              body.set('period_index', String(periodIndex));
              body.set('day_index', String(dayIndex));

              const response = await fetch('/roosters/lessen/verplaats', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
              });
              return response.json();
            };

            const renderLesson = (lesson) => {
              const subject = lesson.subject?.code || lesson.subject?.naam || '';
              const secondary = mode === 'class'
                ? lesson.teacher?.naam
                : lesson.class?.naam;
              const side = mode === 'room'
                ? lesson.teacher?.naam
                : lesson.room?.naam;
              const sideSub = mode === 'class'
                ? `${lesson.room?.capaciteit ?? '-'} pl.`
                : labels[mode === 'teacher' ? 'room' : 'teacher'];

              return `
                <div class="lesson-block ${escapeHtml(lesson.color || 'lesson-blue')}" draggable="true" data-lesson-id="${escapeHtml(lesson.id)}">
                  <div class="lesson-layout">
                    <div class="lesson-main">
                      <span class="lesson-subject">${escapeHtml(subject)}</span>
                      <span class="lesson-teacher">${escapeHtml(secondary || '')}</span>
                    </div>
                    <div class="lesson-side">
                      <span class="lesson-room">${escapeHtml(side || '')}</span>
                      <span class="lesson-education">${escapeHtml(sideSub)}</span>
                    </div>
                  </div>
                </div>
              `;
            };

            const syncTargetOptions = () => {
              targetSelect.innerHTML = '';
              currentItems().forEach((item, itemIndex) => {
                const option = document.createElement('option');
                option.value = String(itemIndex);
                option.textContent = item.label;
                targetSelect.appendChild(option);
              });
              index = Math.min(index, Math.max(0, currentItems().length - 1));
              targetSelect.value = String(index);
            };

            const render = () => {
              const item = activeItem();
              cells.forEach((cell) => {
                cell.innerHTML = '<span class="drop-cell-tint"></span>';
              });

              if (!item) {
                title.textContent = 'Roosterpreview';
                sub.textContent = 'Geen gegevens';
                return;
              }

              title.textContent = item.label;
              sub.textContent = [item.sub, 'Opgeslagen conceptrooster', periodName].filter(Boolean).join(' · ');
              targetSelect.value = String(index);

              cells.forEach((cell) => {
                const [periodIndex, dayIndex] = cell.dataset.rosterCell.split('-').map(Number);
                const lessons = visibleLessonsForCell(item, periodIndex, dayIndex);
                cell.innerHTML = '<span class="drop-cell-tint"></span>' + lessons.map(renderLesson).join('');
              });

              root.querySelectorAll('[data-lesson-id]').forEach((lessonNode) => {
                lessonNode.addEventListener('dragstart', (event) => {
                  draggedLessonId = lessonNode.dataset.lessonId;
                  lessonNode.classList.add('dragging');
                  event.dataTransfer.effectAllowed = 'move';
                  event.dataTransfer.setData('text/plain', draggedLessonId);
                  markDropCells();
                });
                lessonNode.addEventListener('dragend', () => {
                  lessonNode.classList.remove('dragging');
                  draggedLessonId = null;
                  clearDragState();
                });
              });
            };

            cells.forEach((cell) => {
              cell.addEventListener('dragover', (event) => {
                if (!draggedLessonId) return;
                event.preventDefault();
              });

              cell.addEventListener('drop', async (event) => {
                event.preventDefault();
                const lesson = lessonStore.find((candidate) => candidate.id === draggedLessonId);
                const [periodIndex, dayIndex] = cell.dataset.rosterCell.split('-').map(Number);
                const validation = validateMove(lesson, periodIndex, dayIndex);

                if (!lesson || validation.state === 'blocked') {
                  return;
                }

                const previous = { periodIndex: lesson.periodIndex, dayIndex: lesson.dayIndex };
                lesson.periodIndex = periodIndex;
                lesson.dayIndex = dayIndex;
                clearDragState();
                render();

                try {
                  const result = await persistMove(lesson, periodIndex, dayIndex);
                  if (!result.success) {
                    lesson.periodIndex = previous.periodIndex;
                    lesson.dayIndex = previous.dayIndex;
                    render();
                    window.alert(result.error || 'Verplaatsen is niet gelukt.');
                  }
                } catch (error) {
                  lesson.periodIndex = previous.periodIndex;
                  lesson.dayIndex = previous.dayIndex;
                  render();
                  window.alert('Verplaatsen is niet gelukt.');
                }
              });
            });

            modeSelect.addEventListener('change', () => {
              mode = modeSelect.value;
              index = 0;
              syncTargetOptions();
              render();
            });

            targetSelect.addEventListener('change', () => {
              index = Number(targetSelect.value || 0);
              render();
            });

            previousButton.addEventListener('click', () => {
              const total = currentItems().length;
              if (total < 1) return;
              index = (index - 1 + total) % total;
              render();
            });

            nextButton.addEventListener('click', () => {
              const total = currentItems().length;
              if (total < 1) return;
              index = (index + 1) % total;
              render();
            });

            collectLessons();
            syncTargetOptions();
            render();
          })();
        </script>
      <?php endif; ?>
    </div>

    <aside class="generation-main-column">
      <section class="card generation-status-card">
        <div class="generation-panel-head">
          <div>
            <div class="eyebrow">Readiness</div>
            <h2>Controle</h2>
          </div>
        </div>
        <div class="generation-steps">
          <?php foreach (($readiness ?? []) as $check): ?>
            <div class="generation-step <?= !empty($check['ok']) ? 'done' : 'error' ?>">
              <div class="generation-step-icon"><span><?= !empty($check['ok']) ? '✓' : '!' ?></span></div>
              <div>
                <strong><?= htmlspecialchars((string) $check['label']) ?></strong>
                <span><?= htmlspecialchars((string) $check['detail']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card generation-status-card">
        <div class="generation-panel-head">
          <div>
            <div class="eyebrow">Meldingen</div>
            <h2>Generatorlog</h2>
          </div>
        </div>
        <div class="editor-issue-list">
          <?php foreach (($generated['issues'] ?? []) as $issue): ?>
            <div class="editor-issue soft"><strong>Let op</strong><span><?= htmlspecialchars((string) $issue) ?></span></div>
          <?php endforeach; ?>
          <?php if (empty($generated['issues'])): ?>
            <div class="editor-issue soft"><strong>Geen conflicten</strong><span>De basisregels zijn toegepast zonder meldingen.</span></div>
          <?php endif; ?>
        </div>
      </section>
    </aside>
  </div>
</section>
