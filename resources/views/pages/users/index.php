<?php
  $tabs = $tabs ?? ['gebruikers' => 'Gebruikers', 'leraren' => 'Leraren', 'leerlingen' => 'Leerlingen'];
  $activeTab = $activeTab ?? 'gebruikers';
  $tabDescriptions = [
    'gebruikers' => 'Beheer admins, afdelingsleiders en roostermedewerkers.',
    'leraren' => 'Beheer leraaraccounts vanuit gebruikersbeheer.',
    'leerlingen' => 'Beheer leerlingaccounts vanuit gebruikersbeheer.',
  ];
  $createDefaultRole = $activeTab === 'leraren' ? 'leraar' : ($activeTab === 'leerlingen' ? 'leerling' : '');
  $createButtonLabel = $activeTab === 'leraren' ? 'Nieuwe leraar' : ($activeTab === 'leerlingen' ? 'Nieuwe leerling' : 'Nieuwe gebruiker');
?>

<section class="card overview-card">
  <div class="overview-head">
    <div>
      <div class="eyebrow">Gebruikersbeheer</div>
      <div class="sync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 1-15 6.7L3 16M3 12a9 9 0 0 1 15-6.7L21 8M3 4v4h4M21 20v-4h-4"/></svg>
        Namen worden encrypted opgeslagen en alleen hier ontsleuteld voor weergave.
      </div>
    </div>
    <nav class="settings-tabs segmented tabs-inline" aria-label="Gebruiker tabs">
      <?php foreach ($tabs as $tabKey => $tabLabel): ?>
        <a class="<?= $activeTab === $tabKey ? 'active' : '' ?>" href="/gebruikers?tab=<?= htmlspecialchars((string) $tabKey) ?>">
          <?= htmlspecialchars((string) $tabLabel) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="view-actions">
      <button class="btn btn-dark" type="button" data-open-modal="user-create-modal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
        <?= htmlspecialchars($createButtonLabel) ?>
      </button>
    </div>
  </div>

  <div class="kpis">
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-navy"></div><div class="kpi-label">Totaal</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= count($users ?? []) ?></div></div>
      <div class="kpi-sub">gebruikers in scope</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-green"></div><div class="kpi-label">Admins</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= (int) (($roleCounts['school_admin'] ?? 0) + ($roleCounts['sg_admin'] ?? 0)) ?></div></div>
      <div class="kpi-sub">beheeraccounts</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-purple"></div><div class="kpi-label">Planners</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= (int) ($roleCounts['rooster_medewerker'] ?? 0) ?></div></div>
      <div class="kpi-sub">met roosterrol</div>
    </div>
    <div class="kpi">
      <div class="kpi-head"><div class="kpi-icon ic-coral"></div><div class="kpi-label">Scholen</div></div>
      <div class="kpi-value-row"><div class="kpi-num"><?= count($schools ?? []) ?></div></div>
      <div class="kpi-sub">zichtbaar voor gebruiker</div>
    </div>
  </div>
</section>

<?php
  $schools = $schools ?? [];
  $singleSchoolId = count($schools) === 1 ? (string) ($schools[0]['id'] ?? '') : '';
?>

<div id="user-create-modal" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="user-create-title" hidden>
  <div class="modal modal-lg app-modal">
    <div class="modal-head">
      <div>
        <div id="user-create-title" class="modal-title">Nieuwe gebruiker</div>
        <div class="muted text-sm">Maak een gebruiker aan met expliciete school-scope en rolrechten.</div>
      </div>
      <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="post" action="/gebruikers">
      <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
      <input type="hidden" name="tab" value="<?= htmlspecialchars((string) $activeTab) ?>">
      <div class="modal-body">
        <div class="app-modal-grid">
          <div class="form-group">
            <label class="form-label">Naam</label>
            <input class="form-input" type="text" name="name" required>
          </div>
          <div class="form-group">
            <label class="form-label">E-mailadres</label>
            <input class="form-input" type="email" name="email" required>
          </div>
          <div class="form-group">
            <label class="form-label">Wachtwoord</label>
            <input class="form-input" type="password" name="password" minlength="8" required>
          </div>
          <div class="form-group">
            <label class="form-label">Rol</label>
            <select class="form-select" name="role" required>
              <option value="">Kies een rol</option>
              <?php foreach (($roleOptions ?? []) as $roleValue => $roleLabel): ?>
                <option value="<?= htmlspecialchars((string) $roleValue) ?>" <?= $createDefaultRole === (string) $roleValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $roleLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">School</label>
            <select class="form-select" name="school_id" required>
              <?php if ($singleSchoolId === ''): ?>
                <option value="">Kies een school</option>
              <?php endif; ?>
              <?php foreach ($schools as $school): ?>
                <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= $singleSchoolId === (string) $school['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $school['naam']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
        <button class="btn btn-dark" type="submit">Gebruiker aanmaken</button>
      </div>
    </form>
  </div>
</div>

<section class="card tasks-card">
  <div class="tasks-head">
    <div>
      <div class="eyebrow"><?= htmlspecialchars((string) ($tabs[$activeTab] ?? 'Gebruikers')) ?></div>
      <div class="muted text-sm"><?= htmlspecialchars((string) ($tabDescriptions[$activeTab] ?? 'Alleen gebruikers binnen jouw school- of scholengroep-scope.')) ?></div>
    </div>

    <form class="user-filter-form" method="get" action="/gebruikers">
      <input type="hidden" name="tab" value="<?= htmlspecialchars((string) $activeTab) ?>">
      <?php if (!empty($schools)): ?>
        <select class="form-select w-filter" name="school_id" onchange="this.form.submit()">
          <option value="">Alle scholen</option>
          <?php foreach ($schools as $school): ?>
            <option value="<?= htmlspecialchars((string) $school['id']) ?>" <?= ($schoolFilter ?? '') === $school['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string) $school['naam']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if ($activeTab === 'gebruikers'): ?>
          <select class="form-select w-filter" name="role" onchange="this.form.submit()">
            <option value="">Alle rollen</option>
            <?php foreach (($filterRoleOptions ?? []) as $roleValue => $roleLabel): ?>
              <option value="<?= htmlspecialchars((string) $roleValue) ?>" <?= ($roleFilter ?? '') === $roleValue ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $roleLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <select class="form-select w-filter" name="status" onchange="this.form.submit()">
          <option value="">Alle statussen</option>
          <option value="active" <?= ($statusFilter ?? '') === 'active' ? 'selected' : '' ?>>Actief</option>
          <option value="inactive" <?= ($statusFilter ?? '') === 'inactive' ? 'selected' : '' ?>>Inactief</option>
        </select>

        <?php if (!empty($schoolFilter) || !empty($roleFilter) || !empty($statusFilter)): ?>
          <a class="btn btn-outline btn-sm" href="/gebruikers?tab=<?= htmlspecialchars((string) $activeTab) ?>">Wis filters</a>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Naam</th>
          <th>E-mail</th>
          <th>Rol</th>
          <th>School</th>
          <th>Rooster genereren</th>
          <th>Status</th>
          <th>Laatste login</th>
          <th>Acties</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($users ?? []) as $index => $userRow): ?>
          <?php $isCurrentUser = (string) ($currentUserId ?? '') === (string) $userRow['id']; ?>
          <tr>
            <td>
              <span class="assignee">
                <span class="avatar-sm av-<?= ($index % 5) + 1 ?>"><?= htmlspecialchars(mb_substr((string) $userRow['naam'], 0, 1)) ?></span>
                <strong><?= htmlspecialchars((string) $userRow['naam']) ?></strong>
              </span>
            </td>
            <td class="muted"><?= htmlspecialchars((string) $userRow['email']) ?></td>
            <td><span class="badge <?= $userRow['role'] === 'rooster_medewerker' ? 'blue' : 'green' ?>"><?= htmlspecialchars((string) $userRow['role_label']) ?></span></td>
            <td class="muted"><?= htmlspecialchars((string) ($userRow['school_naam'] ?: '-')) ?></td>
            <td>
              <span class="badge <?= $userRow['can_generate_roster'] ? 'green' : '' ?>">
                <?= $userRow['can_generate_roster'] ? 'Toegang' : 'Geen' ?>
              </span>
            </td>
            <td>
              <span class="status <?= $userRow['active'] ? 'st-done' : 'st-block' ?>">
                <?= $userRow['active'] ? 'Actief' : 'Inactief' ?>
              </span>
            </td>
            <td class="muted">
              <?= $userRow['last_login_at'] ? htmlspecialchars(date('d-m-Y H:i', strtotime((string) $userRow['last_login_at']))) : 'Nog niet' ?>
            </td>
            <td>
              <div class="user-row-actions">
                <button class="btn btn-outline btn-sm" type="button" data-open-modal="password-reset-<?= htmlspecialchars((string) $userRow['id']) ?>" <?= $userRow['active'] ? '' : 'disabled' ?>>Reset wachtwoord</button>

                <?php if ($userRow['active']): ?>
                  <form method="post" action="/gebruikers/deactiveer" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt deactiveren?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars((string) $activeTab) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $userRow['id']) ?>">
                    <button class="btn btn-danger btn-sm" type="submit" <?= !$isCurrentUser ? '' : 'disabled' ?>>Weghalen</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="/gebruikers/heractiveer">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars((string) $activeTab) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $userRow['id']) ?>">
                    <button class="btn btn-outline btn-sm" type="submit">Heractiveren</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($users)): ?>
          <tr>
            <td colspan="8" class="muted empty-cell">Geen gebruikers gevonden.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach (($users ?? []) as $userRow): ?>
  <div id="password-reset-<?= htmlspecialchars((string) $userRow['id']) ?>" class="modal-backdrop glass-backdrop" role="dialog" aria-modal="true" aria-labelledby="password-reset-title-<?= htmlspecialchars((string) $userRow['id']) ?>" hidden>
    <div class="modal modal-md app-modal">
      <div class="modal-head">
        <div>
          <div id="password-reset-title-<?= htmlspecialchars((string) $userRow['id']) ?>" class="modal-title">Wachtwoord resetten</div>
          <div class="muted text-sm">Maak een tijdelijk wachtwoord voor deze gebruiker.</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-close-modal>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="/gebruikers/reset-wachtwoord">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrfToken) ?>">
        <input type="hidden" name="tab" value="<?= htmlspecialchars((string) $activeTab) ?>">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string) $userRow['id']) ?>">
        <div class="modal-body">
          <p class="muted password-modal-copy">
            Je maakt een tijdelijk wachtwoord voor <strong><?= htmlspecialchars((string) $userRow['naam']) ?></strong>. Bij de eerstvolgende login moet deze gebruiker direct een nieuw wachtwoord kiezen.
          </p>
          <div class="temporary-password-box">
            <span>Gebruiker</span>
            <strong><?= htmlspecialchars((string) $userRow['email']) ?></strong>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-outline" type="button" data-close-modal>Annuleren</button>
          <button class="btn btn-dark" type="submit" <?= $userRow['active'] ? '' : 'disabled' ?>>Tijdelijk wachtwoord maken</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!empty($temporaryPassword)): ?>
  <?php
    $tempUser = $temporaryPasswordUser ?? [];
    $tempName = (string) ($tempUser['name'] ?? 'de gebruiker');
    $tempEmail = (string) ($tempUser['email'] ?? '');
    $mailSubject = 'Tijdelijk wachtwoord Roostar';
    $mailBody = "Hallo {$tempName},\n\nEr is een tijdelijk wachtwoord voor je Roostar-account aangemaakt:\n\n{$temporaryPassword}\n\nLog hiermee in en kies direct een nieuw wachtwoord.\n\nGroet";
    $mailto = $tempEmail !== ''
      ? 'mailto:' . $tempEmail . '?subject=' . rawurlencode($mailSubject) . '&body=' . rawurlencode($mailBody)
      : '';
  ?>
  <div class="modal-backdrop glass-backdrop password-overlay" role="dialog" aria-modal="true" aria-labelledby="temporary-password-title">
    <div class="modal modal-md app-modal password-modal">
      <div class="modal-head">
        <div>
          <div class="eyebrow">Wachtwoord reset</div>
          <div id="temporary-password-title" class="modal-title">Tijdelijk wachtwoord aangemaakt</div>
        </div>
        <button class="modal-close" type="button" aria-label="Sluiten" data-dismiss-overlay>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="modal-body">
        <p class="muted password-modal-copy">
          Deel dit wachtwoord eenmalig met <?= htmlspecialchars($tempName) ?>. Bij de eerstvolgende login moet de gebruiker direct een nieuw wachtwoord kiezen.
        </p>

        <div class="temporary-password-box">
          <span>Tijdelijk wachtwoord</span>
          <strong id="temporary-password-value"><?= htmlspecialchars((string) $temporaryPassword) ?></strong>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-copy-value="<?= htmlspecialchars((string) $temporaryPassword) ?>">Kopieer</button>
        <?php if ($mailto !== ''): ?>
          <a class="btn btn-dark" href="<?= htmlspecialchars($mailto) ?>">E-mail gebruiker</a>
        <?php endif; ?>
        <button class="btn btn-ghost" type="button" data-dismiss-overlay>Sluiten</button>
      </div>
    </div>
  </div>
<?php endif; ?>
