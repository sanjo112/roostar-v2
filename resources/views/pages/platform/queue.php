<?php
$queueStats = $queueStats ?? ['queued' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
$queueJobs = $queueJobs ?? [];
$feedbackJobs = [];
?>

<section class="generation-page">
  <meta http-equiv="refresh" content="5">
  <div class="generation-header">
    <div>
      <div class="eyebrow">Roostar Admin</div>
      <h1 class="page-title">Rooster queue</h1>
      <p class="muted">Beheer capaciteit en volg rooster-generaties zonder opstopping.</p>
    </div>
    <div class="generation-header-status">
      <div class="generation-header-status-copy">
        <strong><?= (int) ($queueStats['running'] ?? 0) ?> actief · <?= (int) ($queueStats['queued'] ?? 0) ?> wachtend</strong>
        <span>Max <?= (int) ($queueMaxConcurrent ?? 1) ?> simultaan</span>
      </div>
    </div>
  </div>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Capaciteit</div>
        <div class="muted text-sm">Aantal rooster-generaties dat tegelijk mag draaien.</div>
      </div>
      <div class="view-actions">
        <form method="post" action="/roostar-admin/queue/instellingen" class="inline-form">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <label class="form-label sr-only" for="queue-max-concurrent">Simultaan</label>
          <input id="queue-max-concurrent" class="form-input compact-input" type="number" min="1" max="10" name="max_concurrent" value="<?= (int) ($queueMaxConcurrent ?? 1) ?>">
          <button class="btn btn-outline btn-sm" type="submit">Opslaan</button>
        </form>
        <form method="post" action="/roostar-admin/queue/verwerk">
          <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
          <button class="btn btn-dark btn-sm" type="submit">Queue verwerken</button>
        </form>
      </div>
    </div>

    <div class="readiness-grid">
      <div class="readiness-card ok">
        <strong><?= (int) ($queueStats['queued'] ?? 0) ?></strong>
        <span>In wachtrij</span>
      </div>
      <div class="readiness-card ok">
        <strong><?= (int) ($queueStats['running'] ?? 0) ?></strong>
        <span>Bezig</span>
      </div>
      <div class="readiness-card ok">
        <strong><?= (int) ($queueStats['completed'] ?? 0) ?></strong>
        <span>Klaar</span>
      </div>
      <div class="readiness-card <?= (int) ($queueStats['failed'] ?? 0) > 0 ? '' : 'ok' ?>">
        <strong><?= (int) ($queueStats['failed'] ?? 0) ?></strong>
        <span>Mislukt</span>
      </div>
    </div>
  </section>

  <section class="card tasks-card">
    <div class="tasks-head">
      <div>
        <div class="eyebrow">Jobs</div>
        <div class="muted text-sm">Laatste rooster-generaties en resultaten.</div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>School</th>
            <th>Periode</th>
            <th>Status</th>
            <th>Voortgang</th>
            <th>Gelukt</th>
            <th>Hard/soft</th>
            <th>Aangemaakt</th>
            <th>Acties</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($queueJobs as $job): ?>
            <?php
              $status = (string) ($job['status'] ?? 'queued');
              $statusLabel = [
                'queued' => 'In wachtrij',
                'running' => 'Bezig',
                'completed' => 'Klaar',
                'failed' => 'Mislukt',
              ][$status] ?? $status;
              $statusClass = [
                'queued' => 'st-muted',
                'running' => 'st-warn',
                'completed' => 'st-done',
                'failed' => 'st-block',
              ][$status] ?? 'st-muted';
              $hasFeedback = $status === 'failed'
                || !empty($job['error_message'])
                || ($status === 'completed' && (
                  (int) ($job['result_percent'] ?? 100) < 100
                  || (int) ($job['hard_violations'] ?? 0) > 0
                  || (int) ($job['soft_violations'] ?? 0) > 0
                ));
              $feedbackModalId = 'queue-feedback-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($job['id'] ?? 'job'));
              if ($hasFeedback) {
                $feedbackJobs[] = ['id' => $feedbackModalId, 'job' => $job];
              }
            ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) ($job['school_naam'] ?? '')) ?></strong></td>
              <td class="muted"><?= htmlspecialchars((string) ($job['schooljaar_naam'] ?? '')) ?> · <?= htmlspecialchars((string) ($job['periode_naam'] ?? '')) ?></td>
              <td><span class="status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
              <td><?= (int) ($job['progress_percent'] ?? 0) ?>%</td>
              <td><?= ($job['result_percent'] ?? null) === null ? '-' : (int) $job['result_percent'] . '%' ?></td>
              <td class="muted"><?= (int) ($job['hard_violations'] ?? 0) ?> / <?= (int) ($job['soft_violations'] ?? 0) ?></td>
              <td class="muted"><?= htmlspecialchars(date('d-m H:i', strtotime((string) $job['created_at']))) ?></td>
              <td>
                <?php if ($hasFeedback): ?>
                  <button class="btn btn-outline btn-sm" type="button" data-open-modal="<?= htmlspecialchars($feedbackModalId) ?>">Feedback</button>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php if (!empty($job['error_message'])): ?>
              <tr><td colspan="8" class="muted"><?= htmlspecialchars((string) $job['error_message']) ?></td></tr>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($queueJobs === []): ?>
            <tr><td colspan="8" class="muted">Nog geen roosterjobs.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php foreach ($feedbackJobs as $feedback): ?>
    <?php
      $job = $feedback['job'];
      $modalId = $feedback['id'];
      $autoOpen = false;
      require dirname(__DIR__, 2) . '/partials/roster-generation-feedback.php';
    ?>
  <?php endforeach; ?>
</section>
