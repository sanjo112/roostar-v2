<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Module</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 12a9 9 0 0 1 15-6.7L21 8M3 4v4h4M21 20v-4h-4"/></svg>
        <?= htmlspecialchars($moduleDescription ?? 'Deze module wordt stap voor stap uit V1 overgezet.') ?>
      </div>
    </div>
  </div>

  <div class="empty">
    <div class="title"><?= htmlspecialchars($moduleTitle ?? 'Module') ?></div>
    <p class="muted">De V2-shell, navigatie en permissies zijn klaar. De functionaliteit komt in deze modulemap te staan.</p>
  </div>
</section>

