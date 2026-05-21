<?php
if (!function_exists('icon')) {
function icon(string $name): string
{
    $icons = [
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'platform' => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 8v8M8 10.5l4 2.2 4-2.2"/>',
        'roster' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h3M8 18h8"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
        'heart' => '<path d="M20 12c0 4-8 9-8 9s-8-5-8-9a8 8 0 1 1 16 0z"/>',
        'document' => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/>',
        'briefcase' => '<path d="M3 7h18M3 7l2-3h14l2 3M5 7v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7"/>',
        'school' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 9h.01M12 9h.01M15 9h.01"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'teacher' => '<path d="M4 19v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/><circle cx="10" cy="8" r="3.5"/>',
        'student' => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6M19 8v6"/>',
        'audit' => '<path d="M9 11h6M9 15h6M9 7h3"/><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    ];

    $path = $icons[$name] ?? $icons['calendar'];

    return '<svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . $path . '</svg>';
}
}
?>
<aside class="sidebar">
  <div class="brand">
    <a href="/" class="brand-lockup">
      <img src="/assets/images/Roostar_logo_sidebar.png" alt="Roostar" class="brand-logo" width="150" height="44">
    </a>
    <button class="collapse-btn" title="Inklappen" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
    </button>
  </div>

  <div class="search-box">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input placeholder="Zoeken..." aria-label="Zoeken">
  </div>

  <?php foreach (($navGroups ?? []) as $groupLabel => $items): ?>
    <?php if (!$items) continue; ?>
    <div class="nav-label"><?= htmlspecialchars((string) $groupLabel) ?></div>
    <?php foreach ($items as $item): ?>
      <a href="<?= htmlspecialchars($item->href) ?>" class="nav-item <?= $item->active ? 'active' : '' ?>">
        <?= icon($item->icon) ?>
        <?= htmlspecialchars($item->label) ?>
        <?php if ($item->badge): ?>
          <span class="badge <?= in_array($item->badge, ['AI', 'HQ', 'Setup'], true) ? 'green' : '' ?>"><?= htmlspecialchars($item->badge) ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="sidebar-foot">
    <div class="nav-label pt-0">Systeem</div>
    <a class="nav-item logout-link" href="/logout">
      <?= icon('logout') ?>
      Uitloggen
    </a>
  </div>
</aside>
