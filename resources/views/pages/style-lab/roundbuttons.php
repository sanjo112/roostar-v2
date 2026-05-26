<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Style lab</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg>
        Ronde actieknoppen gebaseerd op de roundbuttons referentie.
      </div>
    </div>
  </div>
</section>

<section class="card round-button-demo-card">
  <div class="round-button-demo-head">
    <div>
      <div class="eyebrow">Navigatie variant</div>
      <h1 class="page-title">Round button stijl</h1>
      <p class="muted">Een proef voor grote, ronde actieknoppen met zachte diepte en verbindingslijn.</p>
    </div>
  </div>

  <nav class="round-action-nav" aria-label="Round button demo">
    <a class="round-action-button" href="/rooster" aria-label="Rooster">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h3M8 18h8"/></svg>
      <span>Rooster</span>
    </a>
    <a class="round-action-button" href="/roosters/genereren" aria-label="Genereren">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/></svg>
      <span>Genereren</span>
    </a>
    <a class="round-action-button" href="/stamdata" aria-label="Stamdata">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>
      <span>Stamdata</span>
    </a>
    <a class="round-action-button" href="/gebruikers" aria-label="Gebruikers">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11h-6M19 8v6"/></svg>
      <span>Gebruikers</span>
    </a>
  </nav>

  <div class="round-neo-demo">
    <button class="round-neo-button" type="button" aria-label="Vorige">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
    </button>
    <button class="round-neo-button is-inset" type="button" aria-label="Volgende">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </button>
  </div>

  <div class="neo-toolbar-demo">
    <span class="neo-control-cluster">
      <button class="neo-icon-control" type="button" aria-label="Vorige week">&lt;</button>
      <select class="neo-select-control" aria-label="Week">
        <option>Week 21</option>
        <option>Week 22</option>
      </select>
      <button class="neo-icon-control" type="button" aria-label="Volgende week">&gt;</button>
    </span>
    <select class="neo-select-control neo-year-control" aria-label="Jaar">
      <option>2026</option>
      <option>2027</option>
    </select>
  </div>

  <div class="soft-segment-demo">
    <div class="soft-segment-group" data-soft-segment-group>
      <button class="soft-segment-item" type="button">Cut</button>
      <button class="soft-segment-item" type="button">Copy</button>
      <button class="soft-segment-item" type="button">Paste</button>
    </div>
  </div>

  <div class="soft-week-demo">
    <div class="soft-week-group" data-soft-segment-group>
      <button class="soft-week-item soft-week-arrow" type="button" aria-label="Vorige week">&lt;</button>
      <select class="soft-week-item soft-week-select" aria-label="Week">
        <option>Week 21</option>
        <option>Week 22</option>
        <option>Week 23</option>
      </select>
      <button class="soft-week-item soft-week-arrow" type="button" aria-label="Volgende week">&gt;</button>
    </div>
    <select class="soft-week-item soft-week-year" aria-label="Jaar">
      <option>2026</option>
      <option>2027</option>
    </select>
  </div>
</section>

<script>
  document.querySelectorAll('[data-soft-segment-group] .soft-segment-item').forEach((button) => {
    button.addEventListener('click', () => {
      button.classList.add('soft-segment-item-active');
      setTimeout(() => button.classList.remove('soft-segment-item-active'), 600);
    });
  });
</script>
