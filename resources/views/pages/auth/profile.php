<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Account</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
        Persoonlijke gegevens en beveiliging.
      </div>
    </div>
    <div class="view-actions">
      <a class="btn btn-dark" href="/wachtwoord-wijzigen">Wachtwoord wijzigen</a>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-navy"></div><div class="kpi-label">Rol</div></div>
      <div class="kpi-value-row"><div class="kpi-num profile-kpi-text"><?= htmlspecialchars((string) $profile['role_label']) ?></div></div>
      <div class="kpi-sub">rechten via school-scope</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-green"></div><div class="kpi-label">Laatste login</div></div>
      <div class="kpi-value-row"><div class="kpi-num profile-kpi-text"><?= $profile['last_login_at'] ? htmlspecialchars(date('d-m-Y H:i', strtotime((string) $profile['last_login_at']))) : 'Nog niet' ?></div></div>
      <div class="kpi-sub">accountactiviteit</div>
    </div>
  </div>
</section>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow">Mijn gegevens</div>
      <div class="muted text-sm">Namen worden encrypted opgeslagen en alleen bij weergave ontsleuteld.</div>
    </div>
  </div>

  <div class="profile-grid">
    <div class="profile-field">
      <span>Naam</span>
      <strong><?= htmlspecialchars((string) $profile['naam']) ?></strong>
    </div>
    <div class="profile-field">
      <span>E-mailadres</span>
      <strong><?= htmlspecialchars((string) $profile['email']) ?></strong>
    </div>
    <div class="profile-field">
      <span>School</span>
      <strong><?= htmlspecialchars((string) $profile['school_naam']) ?></strong>
    </div>
    <div class="profile-field">
      <span>Scholengroep</span>
      <strong><?= htmlspecialchars((string) $profile['scholengroep_naam']) ?></strong>
    </div>
    <div class="profile-field">
      <span>Wachtwoord gewijzigd</span>
      <strong><?= $profile['password_changed_at'] ? htmlspecialchars(date('d-m-Y H:i', strtotime((string) $profile['password_changed_at']))) : 'Nog niet' ?></strong>
    </div>
  </div>
</section>
