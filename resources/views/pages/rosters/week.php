<?php
$overview = $overview ?? ['views' => ['class' => []], 'days' => [], 'dates' => [], 'issues' => []];
$views = $overview['views'] ?? ['class' => [], 'teacher' => [], 'room' => []];
$selectedLabel = (string) ($views['class'][0]['label'] ?? 'Rooster');
$selectedSub = (string) ($views['class'][0]['sub'] ?? '');
$yearOptions = range(max(2020, (int) $year - 3), min(2035, (int) $year + 5));
?>

<section class="generation-page">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Rooster</div>
      <h1 class="page-title">Weekrooster</h1>
      <p class="muted">Week <?= (int) $week ?><?= !empty($overview['period']['naam']) ? ' · ' . htmlspecialchars((string) $overview['period']['naam']) : '' ?>. Ziekte en vervanging liggen bovenop het basisrooster.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= count($overview['issues'] ?? []) ?> aandachtspunt(en)</strong>
        <span><?= !empty($overview['period']['school_naam']) ? htmlspecialchars((string) $overview['period']['school_naam']) : 'Geen periode gevonden' ?></span>
      </div>
    </div>
  </div>

  <?php if (empty($views['class'] ?? [])): ?>
    <section class="card schedule-card">
      <div class="schedule-head">
        <div>
          <h2 class="schedule-title">Geen rooster</h2>
          <div class="schedule-sub">Genereer eerst een rooster voor de periode waarin deze week valt.</div>
        </div>
        <form class="schedule-head-actions week-picker-controls" method="get" action="/rooster">
          <span class="control-cluster">
            <a class="icon-btn" href="/rooster?week=<?= (int) $previousWeek ?>&jaar=<?= (int) $year ?>" aria-label="Vorige week">&lt;</a>
            <select class="form-select" name="week" aria-label="Week" onchange="this.form.submit()">
              <?php for ($weekOption = 1; $weekOption <= 53; $weekOption++): ?>
                <option value="<?= $weekOption ?>" <?= $weekOption === (int) $week ? 'selected' : '' ?>>Week <?= $weekOption ?></option>
              <?php endfor; ?>
            </select>
            <a class="icon-btn" href="/rooster?week=<?= (int) $nextWeek ?>&jaar=<?= (int) $year ?>" aria-label="Volgende week">&gt;</a>
          </span>
          <select class="form-select" name="jaar" aria-label="Jaar" onchange="this.form.submit()">
            <?php foreach ($yearOptions as $yearOption): ?>
              <option value="<?= $yearOption ?>" <?= $yearOption === (int) $year ? 'selected' : '' ?>><?= $yearOption ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="schedule-grid-layer">
        <div class="calendar-empty-overlay">
          <div>
            <strong>Geen lessen gevonden</strong>
            <span>Kies een week binnen een gegenereerde periode.</span>
          </div>
        </div>
      </div>
    </section>
  <?php else: ?>
    <section class="card schedule-card roster-overview" id="week-roster-overview">
      <div class="schedule-head">
        <div>
          <h2 class="schedule-title" data-roster-title><?= htmlspecialchars($selectedLabel) ?></h2>
          <div class="schedule-sub" data-roster-sub><?= htmlspecialchars($selectedSub) ?><?= $selectedSub !== '' ? ' · ' : '' ?>Week <?= (int) $week ?></div>
        </div>
        <div class="schedule-head-actions roster-overview-controls">
          <form class="week-picker-controls" method="get" action="/rooster">
            <span class="control-cluster">
              <a class="icon-btn" href="/rooster?week=<?= (int) $previousWeek ?>&jaar=<?= (int) $year ?>" aria-label="Vorige week">&lt;</a>
              <select class="form-select" name="week" aria-label="Week" onchange="this.form.submit()">
                <?php for ($weekOption = 1; $weekOption <= 53; $weekOption++): ?>
                  <option value="<?= $weekOption ?>" <?= $weekOption === (int) $week ? 'selected' : '' ?>>Week <?= $weekOption ?></option>
                <?php endfor; ?>
              </select>
              <a class="icon-btn" href="/rooster?week=<?= (int) $nextWeek ?>&jaar=<?= (int) $year ?>" aria-label="Volgende week">&gt;</a>
            </span>
            <select class="form-select" name="jaar" aria-label="Jaar" onchange="this.form.submit()">
              <?php foreach ($yearOptions as $yearOption): ?>
                <option value="<?= $yearOption ?>" <?= $yearOption === (int) $year ? 'selected' : '' ?>><?= $yearOption ?></option>
              <?php endforeach; ?>
            </select>
          </form>
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
                <?php foreach (($overview['days'] ?? []) as $day): ?>
                  <th>
                    <?= htmlspecialchars((string) $day) ?>
                    <small><?= !empty($overview['dates'][$day]) ? htmlspecialchars(date('d-m', strtotime((string) $overview['dates'][$day]))) : '' ?></small>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($periods ?? []) as $periodIndex => $range): ?>
                <tr>
                  <td class="time-cell">
                    <strong><?= htmlspecialchars((string) $range[0]) ?></strong>
                    <span><?= htmlspecialchars((string) $range[1]) ?></span>
                  </td>
                  <?php for ($dayIndex = 0; $dayIndex < 5; $dayIndex++): ?>
                    <td class="editor-drop-cell" data-roster-cell="<?= ((int) $periodIndex - 1) ?>-<?= (int) $dayIndex ?>"><span class="drop-cell-tint"></span></td>
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
        const root = document.getElementById('week-roster-overview');
        if (!root) return;

        const views = <?= json_encode($views, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const weekLabel = <?= json_encode('Week ' . (int) $week, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const weekNumber = <?= (int) $week ?>;
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
          const conflict = lessonStore.find((candidate) => (
            candidate.id !== lesson.id
            && Number(candidate.periodIndex) === Number(periodIndex)
            && Number(candidate.dayIndex) === Number(dayIndex)
            && (
              candidate.classId === lesson.classId
              || candidate.teacherId === lesson.teacherId
              || candidate.roomId === lesson.roomId
            )
          ));
          return conflict ? { state: 'blocked', reason: 'Botst met klas, leraar of lokaal' } : { state: 'ok', reason: 'Past op dit uur' };
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
          body.set('week', String(weekNumber));
          body.set('period_index', String(periodIndex));
          body.set('day_index', String(dayIndex));

          const response = await fetch('/rooster/week/lessen/verplaats', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
          });
          return response.json();
        };
        const renderLesson = (lesson) => {
          const subject = lesson.subject?.code || lesson.subject?.naam || '';
          const secondary = mode === 'class' ? lesson.teacher?.naam : lesson.class?.naam;
          const side = mode === 'room'
            ? lesson.teacher?.naam
            : lesson.room?.naam;
          const sideSub = mode === 'class'
            ? `${lesson.room?.capaciteit ?? '-'} pl.`
            : (mode === 'teacher' ? 'Lokaal' : 'Leraar');
          const status = lesson.status === 'sick'
            ? '<span class="lesson-status danger">Ziek</span>'
            : (lesson.status === 'cancelled'
              ? '<span class="lesson-status danger">Uitgeroosterd</span>'
              : (lesson.status === 'replaced' ? '<span class="lesson-status ok">Vervanging</span>' : ''));

          return `
            <div class="lesson-block ${escapeHtml(lesson.color || 'lesson-blue')} ${['sick', 'cancelled'].includes(lesson.status) ? 'conflict' : ''}" draggable="true" data-lesson-id="${escapeHtml(lesson.id)}">
              <div class="lesson-layout">
                <div class="lesson-main">
                  <span class="lesson-subject">${escapeHtml(subject)}</span>
                  <span class="lesson-teacher">${escapeHtml(secondary || '')}</span>
                  ${status}
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
          cells.forEach((cell) => { cell.innerHTML = '<span class="drop-cell-tint"></span>'; });

          if (!item) {
            title.textContent = 'Rooster';
            sub.textContent = 'Geen gegevens';
            return;
          }

          title.textContent = item.label;
          sub.textContent = [item.sub, weekLabel].filter(Boolean).join(' · ');
          targetSelect.value = String(index);
          cells.forEach((cell) => {
            const [periodIndex, dayIndex] = cell.dataset.rosterCell.split('-');
            const lessons = visibleLessonsForCell(item, Number(periodIndex), Number(dayIndex));
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
            if (!lesson || validation.state === 'blocked') return;

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

  <?php if (!empty($overview['issues'])): ?>
    <section class="card generation-status-card mt-card">
      <div class="generation-panel-head">
        <div>
          <div class="eyebrow">Weekissues</div>
          <h2>Ziekte en vervanging</h2>
        </div>
      </div>
      <div class="editor-issue-list">
        <?php foreach ($overview['issues'] as $issue): ?>
          <div class="editor-issue soft"><strong>Actie nodig</strong><span><?= htmlspecialchars((string) $issue) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</section>
