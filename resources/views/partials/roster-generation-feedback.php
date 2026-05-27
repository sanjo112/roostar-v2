<?php
$job = is_array($job ?? null) ? $job : [];
$modalId = (string) ($modalId ?? 'generation-feedback-modal');
$autoOpen = (bool) ($autoOpen ?? false);
$stats = is_array($job['stats'] ?? null) ? $job['stats'] : [];
$resultStats = is_array($stats['result'] ?? null) ? $stats['result'] : [];
$validation = is_array($stats['validation'] ?? null) ? $stats['validation'] : [];
$issues = [];

foreach (($stats['issues'] ?? []) as $issue) {
    if (is_scalar($issue) && trim((string) $issue) !== '') {
        $issues[] = trim((string) $issue);
    }
}

foreach (($validation['errors'] ?? []) as $issue) {
    if (is_scalar($issue) && trim((string) $issue) !== '') {
        $issues[] = trim((string) $issue);
    }
}

$issues = array_values(array_unique($issues));
$status = (string) ($job['status'] ?? 'completed');
$statusLabel = [
    'queued' => 'In wachtrij',
    'running' => 'Bezig',
    'completed' => 'Klaar',
    'failed' => 'Mislukt',
][$status] ?? $status;
$resultPercent = $job['result_percent'] ?? null;
$lessonCount = (int) ($job['lesson_count'] ?? ($resultStats['lessons'] ?? 0));
$requestCount = (int) ($job['lesson_request_count'] ?? ($resultStats['lessonRequests'] ?? 0));
$unplaced = max(0, $requestCount - $lessonCount);
$hardViolations = (int) ($job['hard_violations'] ?? ($resultStats['hardViolations'] ?? 0));
$softViolations = (int) ($job['soft_violations'] ?? ($resultStats['softViolations'] ?? 0));
$errorMessage = trim((string) ($job['error_message'] ?? ''));
$summary = [];

if ($status === 'failed') {
    $summary[] = $errorMessage !== '' ? $errorMessage : 'De generatie is afgebroken voordat er een rooster kon worden opgeslagen.';
}

if ($requestCount > 0 && $lessonCount < $requestCount) {
    $summary[] = $lessonCount . ' van ' . $requestCount . ' lesaanvragen zijn geplaatst. ' . $unplaced . ' aanvraag(en) hebben geen plek gevonden.';
}

if ($hardViolations > 0) {
    $summary[] = $hardViolations . ' harde regel(s) zijn niet gehaald. Dit moet eerst worden opgelost voor een bruikbaar rooster.';
}

if ($softViolations > 0) {
    $summary[] = $softViolations . ' voorkeur(en) zijn niet gehaald. Het rooster is bruikbaar, maar kan beter.';
}

if ($summary === []) {
    $summary[] = 'De generator heeft geen specifieke blokkade opgeslagen, maar het resultaat verdient controle.';
}
?>

<div id="<?= htmlspecialchars($modalId) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId) ?>-title" <?= $autoOpen ? 'data-auto-open-modal' : 'hidden' ?>>
  <div class="modal modal-lg app-modal roster-feedback-modal">
    <div class="modal-head">
      <div>
        <div class="eyebrow">Roostergeneratie</div>
        <div id="<?= htmlspecialchars($modalId) ?>-title" class="modal-title">Feedback op resultaat</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <div class="queue-detail">
        <div class="queue-detail-grid">
          <div>
            <span>Status</span>
            <strong><?= htmlspecialchars($statusLabel) ?></strong>
          </div>
          <div>
            <span>Gelukt</span>
            <strong><?= $resultPercent === null ? '-' : (int) $resultPercent . '%' ?></strong>
          </div>
          <div>
            <span>Lessen</span>
            <strong><?= $lessonCount ?> / <?= $requestCount ?></strong>
          </div>
          <div>
            <span>Hard</span>
            <strong><?= $hardViolations ?></strong>
          </div>
          <div>
            <span>Soft</span>
            <strong><?= $softViolations ?></strong>
          </div>
          <div>
            <span>Aangemaakt</span>
            <strong><?= !empty($job['created_at']) ? htmlspecialchars(date('d-m H:i', strtotime((string) $job['created_at']))) : '-' ?></strong>
          </div>
        </div>

        <div class="queue-detail-message">
          <strong>Samenvatting</strong>
          <?php foreach ($summary as $line): ?>
            <p><?= htmlspecialchars($line) ?></p>
          <?php endforeach; ?>
        </div>

        <div class="queue-detail-message">
          <strong>Aandachtspunten</strong>
          <?php if ($issues === []): ?>
            <p>Geen losse aandachtspunten opgeslagen bij deze run.</p>
          <?php else: ?>
            <ul class="feedback-issue-list">
              <?php foreach (array_slice($issues, 0, 30) as $issue): ?>
                <li><?= htmlspecialchars($issue) ?></li>
              <?php endforeach; ?>
            </ul>
            <?php if (count($issues) > 30): ?>
              <p><?= count($issues) - 30 ?> extra aandachtspunt(en) verborgen.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="modal-foot">
      <button class="btn btn-outline" type="button" data-close-modal>Sluiten</button>
    </div>
  </div>
</div>
