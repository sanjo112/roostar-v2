<?php
$notificationCenter = $notificationCenter ?? ($notifications ?? []);
$notificationUnreadCount = 0;

foreach ($notificationCenter as $notification) {
    if (empty($notification['is_read']) && empty($notification['read_at'])) {
        $notificationUnreadCount++;
    }
}

$formatNotificationTime = static function (array $notification): string {
    $createdAt = (string) ($notification['created_at'] ?? '');

    if ($createdAt === '') {
        return 'net';
    }

    try {
        $created = new DateTimeImmutable($createdAt);
        $now = new DateTimeImmutable();
        $seconds = max(0, $now->getTimestamp() - $created->getTimestamp());

        if ($seconds < 60) {
            return 'net';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60) . ' min geleden';
        }

        if ($created->format('Y-m-d') === $now->format('Y-m-d')) {
            return $created->format('H:i');
        }

        return $created->format('d-m H:i');
    } catch (Throwable) {
        return 'net';
    }
};
?>

<div class="topbar">
  <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
  <div class="topbar-right">
    <button class="icon-btn" title="Help" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4M12 17h.01"/></svg>
    </button>
    <div class="notification-menu">
      <button class="icon-btn notification-bell" title="Meldingen" type="button" data-notification-toggle aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 21a2 2 0 0 0 4 0"/></svg>
        <?php if ($notificationUnreadCount > 0): ?>
          <span class="notification-badge"><?= $notificationUnreadCount ?></span>
        <?php endif; ?>
      </button>

      <div class="notification-panel" data-notification-panel>
        <div class="notification-panel-head">
          <strong>Meldingen</strong>
          <span data-notification-count><?= $notificationUnreadCount ?> nieuw</span>
        </div>
        <div class="notification-panel-list">
          <?php foreach ($notificationCenter as $notification): ?>
            <?php
              $isRead = !empty($notification['is_read']) || !empty($notification['read_at']);
              $notificationId = (string) ($notification['id'] ?? '');
            ?>
            <div class="notification-panel-item notification-<?= htmlspecialchars((string) ($notification['type'] ?? 'info')) ?><?= $isRead ? ' is-read' : '' ?>" data-notification-id="<?= htmlspecialchars($notificationId) ?>">
              <div class="notification-panel-title-row">
                <strong><?= htmlspecialchars((string) ($notification['title'] ?? 'Roostar')) ?></strong>
                <?php if (!$isRead): ?>
                  <span class="notification-unread-dot" aria-label="Ongelezen"></span>
                <?php endif; ?>
              </div>
              <span><?= htmlspecialchars((string) ($notification['message'] ?? '')) ?></span>
              <small><?= htmlspecialchars($formatNotificationTime($notification)) ?><?= $isRead ? ' · gelezen' : '' ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (empty($notificationCenter)): ?>
            <div class="notification-empty">Nog geen meldingen.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <a href="/profiel" class="avatar" title="Mijn profiel">
      <?= htmlspecialchars((string) ($user['initials'] ?? 'V2')) ?>
    </a>
  </div>
</div>
