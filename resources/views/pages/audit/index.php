<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Security</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h6M9 15h6M9 7h3"/><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/></svg>
        Logins en beheeracties binnen jouw school-scope.
      </div>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-navy"></div><div class="kpi-label">Events</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= count($events ?? []) ?></div></div>
      <div class="kpi-sub">laatste auditregels</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-green"></div><div class="kpi-label">Scope</div></div>
      <div class="kpi-value-row"><div class="kpi-num">School</div></div>
      <div class="kpi-sub">geen data buiten rechten</div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Auditlog</div>
      <div class="muted text-sm">Gebeurtenissen worden server-side vastgelegd, inclusief IP-adres en actor.</div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Tijd</th>
          <th>Actie</th>
          <th>Door</th>
          <th>Doel</th>
          <th>Details</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($events ?? []) as $event): ?>
          <tr>
            <td class="muted"><?= htmlspecialchars(date('d-m-Y H:i', strtotime((string) $event['created_at']))) ?></td>
            <td><span class="badge blue"><?= htmlspecialchars((string) $event['action_label']) ?></span></td>
            <td><?= htmlspecialchars((string) $event['actor']) ?></td>
            <td class="muted"><?= htmlspecialchars((string) $event['target']) ?></td>
            <td class="muted">
              <?php
                $metadata = $event['metadata'] ?? [];
                $parts = [];
                foreach (['reason', 'target_role', 'school_id'] as $key) {
                    if (!empty($metadata[$key])) {
                        $parts[] = $key . ': ' . $metadata[$key];
                    }
                }
              ?>
              <?= htmlspecialchars($parts ? implode(' | ', $parts) : '-') ?>
            </td>
            <td class="muted"><?= htmlspecialchars((string) $event['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($events)): ?>
          <tr>
            <td colspan="6" class="muted empty-cell">Geen auditregels gevonden.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
