<?php
use Roostar\Modules\Rosters\Engine\Model\LessonAssignment;
use Roostar\Modules\Rosters\Engine\Model\SchedulingInput;
use Roostar\Modules\Rosters\Engine\SchedulingRunResult;

/** @var SchedulingRunResult $result */
/** @var SchedulingInput $input */
?>

<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Rooster engine</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 17V7l8-4 8 4v10l-8 4-8-4Z"/><path d="m4 7 8 4 8-4M12 11v10"/></svg>
        Modulaire pipeline: basisrooster, validatie, score en optimalisatie-stappen.
      </div>
    </div>
    <div class="view-actions">
      <span class="status <?= $result->score->validation->isValid() ? 'st-done' : 'st-block' ?>">
        <?= $result->score->validation->isValid() ? 'Geldig rooster' : 'Ongeldig rooster' ?>
      </span>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-navy"></div><div class="kpi-label">Score</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= htmlspecialchars((string) $result->score->value) ?></div></div>
      <div class="kpi-sub">centrale scorefunctie</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-green"></div><div class="kpi-label">Lessen</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= count($result->schedule->assignments()) ?></div></div>
      <div class="kpi-sub">ingepland in demo-run</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-coral"></div><div class="kpi-label">Harde fouten</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= $result->score->validation->hardCount() ?></div></div>
      <div class="kpi-sub">maken rooster ongeldig</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-purple"></div><div class="kpi-label">Zachte punten</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= $result->score->validation->softCount() ?></div></div>
      <div class="kpi-sub">optimalisatie-kandidaten</div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Pipeline</div>
      <div class="muted text-sm">Elke stap krijgt hetzelfde rooster, probeert een verbetering en levert alleen een betere variant terug.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Stap</th>
          <th>Score</th>
          <th>Hard</th>
          <th>Zacht</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result->steps as $step): ?>
          <tr>
            <td><strong><?= htmlspecialchars((string) $step['name']) ?></strong></td>
            <td class="muted"><?= htmlspecialchars((string) $step['score']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $step['hard_count']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $step['soft_count']) ?></td>
            <td>
              <span class="status <?= !empty($step['valid']) ? 'st-done' : 'st-block' ?>">
                <?= !empty($step['valid']) ? (!empty($step['improved']) ? 'Verbeterd' : 'Ok') : 'Blokkerend' ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Rooster resultaat</div>
      <div class="muted text-sm">Demo-output van de engine. Later vervangen we de demo-input door echte lesverdeling uit de database.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Klas</th>
          <th>Vak</th>
          <th>Docent</th>
          <th>Lokaal</th>
          <th>Moment</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result->schedule->assignments() as $assignment): ?>
          <?php /** @var LessonAssignment $assignment */ ?>
          <tr>
            <td><strong><?= htmlspecialchars((string) ($input->classGroup($assignment->classGroupId)?->name ?? $assignment->classGroupId)) ?></strong></td>
            <td class="muted"><?= htmlspecialchars((string) ($input->subject($assignment->subjectId)?->name ?? $assignment->subjectId)) ?></td>
            <td class="muted"><?= htmlspecialchars((string) ($input->teacher($assignment->teacherId)?->name ?? $assignment->teacherId)) ?></td>
            <td class="muted"><?= htmlspecialchars((string) ($input->room($assignment->roomId)?->name ?? $assignment->roomId)) ?></td>
            <td class="muted"><?= htmlspecialchars((string) ($input->slot($assignment->slotId)?->label ?? $assignment->slotId)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Uitleg</div>
      <div class="muted text-sm">De explanation layer maakt regels begrijpelijk voor de planner.</div>
    </div>
  </div>

  <?php if (empty($result->explanations)): ?>
    <div class="muted text-sm">Geen harde fouten of zachte verbeterpunten gevonden.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Regel</th>
            <th>Uitleg</th>
            <th>Penalty</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result->explanations as $explanation): ?>
            <tr>
              <td><span class="badge <?= $explanation['severity'] === 'hard' ? '' : 'blue' ?>"><?= htmlspecialchars((string) $explanation['severity']) ?></span></td>
              <td class="muted"><?= htmlspecialchars((string) $explanation['rule']) ?></td>
              <td><?= htmlspecialchars((string) $explanation['message']) ?></td>
              <td class="muted"><?= htmlspecialchars((string) $explanation['penalty']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

