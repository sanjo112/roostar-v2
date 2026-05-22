<?php
$overview = $overview ?? ['views' => ['class' => []], 'days' => [], 'dates' => [], 'period' => null, 'issues' => []];
$classes = $overview['views']['class'] ?? [];
$days = $overview['days'] ?? ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag'];
$dates = $overview['dates'] ?? [];
$periods = $periods ?? [];
$schoolName = (string) ($overview['period']['school_naam'] ?? '');
$periodName = (string) ($overview['period']['naam'] ?? '');
$lessonStatus = static function (array $lesson): string {
    return match ((string) ($lesson['status'] ?? 'normal')) {
        'cancelled' => 'Uitgeroosterd',
        'replaced' => 'Vervangen',
        'sick' => 'Ziek',
        default => '',
    };
};
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Rooster week <?= (int) $week ?> <?= (int) $year ?></title>
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      color: #1f2937;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      background: #fff;
    }
    .export-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 14px;
      border: 1px solid #d9e2ef;
      border-radius: 8px;
      margin-bottom: 12px;
      background: #f8fafc;
    }
    .export-toolbar strong { display: block; font-size: 18px; color: #111827; }
    .export-toolbar span { color: #64748b; }
    .print-btn {
      border: 1px solid #2563eb;
      border-radius: 7px;
      padding: 9px 13px;
      background: #2563eb;
      color: #fff;
      font-weight: 700;
      cursor: pointer;
    }
    .roster-page {
      page-break-after: always;
      margin-bottom: 14px;
    }
    .roster-page:last-child { page-break-after: auto; }
    .roster-title {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 8px;
    }
    .roster-title h1 {
      margin: 0;
      font-size: 20px;
      color: #111827;
    }
    .roster-title p {
      margin: 3px 0 0;
      color: #64748b;
    }
    .roster-meta {
      text-align: right;
      color: #64748b;
      line-height: 1.45;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      border: 1px solid #d9e2ef;
    }
    th, td {
      border: 1px solid #d9e2ef;
      vertical-align: top;
    }
    th {
      padding: 7px 6px;
      background: #f3f6fb;
      color: #475569;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    th small {
      display: block;
      margin-top: 2px;
      color: #94a3b8;
      font-size: 10px;
      letter-spacing: 0;
      text-transform: none;
    }
    td {
      min-height: 58px;
      height: 58px;
      padding: 5px;
      background: #fff;
    }
    .time-cell {
      width: 70px;
      background: #f8fafc;
      color: #334155;
      text-align: center;
      vertical-align: middle;
    }
    .time-cell strong { display: block; font-size: 12px; }
    .time-cell span { color: #64748b; }
    .lesson {
      display: grid;
      gap: 2px;
      padding: 6px 7px;
      border-left: 3px solid #2563eb;
      border-radius: 5px;
      background: #eff6ff;
      color: #1e293b;
      line-height: 1.25;
      break-inside: avoid;
    }
    .lesson + .lesson { margin-top: 4px; }
    .lesson.replaced { border-left-color: #059669; background: #ecfdf5; }
    .lesson.sick,
    .lesson.cancelled { border-left-color: #dc2626; background: #fef2f2; }
    .lesson-top {
      display: flex;
      justify-content: space-between;
      gap: 6px;
      font-weight: 700;
    }
    .lesson-meta {
      display: flex;
      justify-content: space-between;
      gap: 6px;
      color: #64748b;
      font-size: 10px;
    }
    .status {
      color: #b91c1c;
      font-size: 10px;
      font-weight: 700;
    }
    .empty {
      padding: 28px;
      border: 1px dashed #d9e2ef;
      border-radius: 8px;
      color: #64748b;
      text-align: center;
    }
    @media print {
      .export-toolbar { display: none; }
      body { font-size: 10px; }
      .roster-page { margin: 0; }
    }
  </style>
</head>
<body>
  <div class="export-toolbar">
    <div>
      <strong>Rooster export</strong>
      <span>Week <?= (int) $week ?> · <?= (int) $year ?><?= $periodName !== '' ? ' · ' . htmlspecialchars($periodName) : '' ?><?= $schoolName !== '' ? ' · ' . htmlspecialchars($schoolName) : '' ?></span>
    </div>
    <button class="print-btn" type="button" onclick="window.print()">PDF opslaan</button>
  </div>

  <?php if (empty($classes)): ?>
    <div class="empty">Geen rooster gevonden voor week <?= (int) $week ?>.</div>
  <?php endif; ?>

  <?php foreach ($classes as $class): ?>
    <section class="roster-page">
      <div class="roster-title">
        <div>
          <h1><?= htmlspecialchars((string) ($class['label'] ?? 'Klas')) ?></h1>
          <p><?= htmlspecialchars(trim((string) ($class['sub'] ?? ''))) ?></p>
        </div>
        <div class="roster-meta">
          <div>Week <?= (int) $week ?> · <?= (int) $year ?></div>
          <?php if ($periodName !== ''): ?><div><?= htmlspecialchars($periodName) ?></div><?php endif; ?>
          <div>Gegenereerd <?= htmlspecialchars((string) $generatedAt) ?></div>
        </div>
      </div>

      <table>
        <colgroup>
          <col style="width: 72px">
          <col span="5">
        </colgroup>
        <thead>
          <tr>
            <th>Tijd</th>
            <?php foreach ($days as $day): ?>
              <th>
                <?= htmlspecialchars((string) $day) ?>
                <small><?= !empty($dates[$day]) ? htmlspecialchars(date('d-m-Y', strtotime((string) $dates[$day]))) : '' ?></small>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($periods as $periodIndex => $range): ?>
            <tr>
              <td class="time-cell">
                <strong><?= htmlspecialchars((string) $range[0]) ?></strong>
                <span><?= htmlspecialchars((string) $range[1]) ?></span>
              </td>
              <?php for ($dayIndex = 0; $dayIndex < 5; $dayIndex++): ?>
                <td>
                  <?php foreach (($class['lessons'][(int) $periodIndex - 1][$dayIndex] ?? []) as $lesson): ?>
                    <?php $status = $lessonStatus($lesson); ?>
                    <div class="lesson <?= htmlspecialchars((string) ($lesson['status'] ?? 'normal')) ?>">
                      <div class="lesson-top">
                        <span><?= htmlspecialchars((string) ($lesson['subject']['code'] ?? $lesson['subject']['naam'] ?? '')) ?></span>
                        <span><?= htmlspecialchars((string) ($lesson['room']['naam'] ?? '')) ?></span>
                      </div>
                      <div class="lesson-meta">
                        <span><?= htmlspecialchars((string) ($lesson['teacher']['naam'] ?? '')) ?></span>
                        <span><?= htmlspecialchars((string) ($lesson['room']['capaciteit'] ?? '')) ?> pl.</span>
                      </div>
                      <?php if ($status !== ''): ?><div class="status"><?= htmlspecialchars($status) ?></div><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>

  <script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
  </script>
</body>
</html>
