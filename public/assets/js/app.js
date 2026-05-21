window.roostar = {
  toast(message, type = 'info', options = {}) {
    const stack = getToastStack();
    const toast = document.createElement('div');
    const variant = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    const title = options.title || notificationTitle(variant);
    const actionLabel = options.actionLabel || '';
    const actionHref = options.actionHref || '';

    toast.className = `notification-card notification-${variant}`;
    if (options.id) {
      toast.dataset.notificationId = options.id;
    }
    toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
    toast.innerHTML = `
      <div class="notification-icon" aria-hidden="true">${notificationIcon(variant)}</div>
      <div class="notification-content">
        <div class="notification-head">
          <strong>${escapeHtml(title)}</strong>
          <span>nu</span>
        </div>
        <div class="notification-message">${escapeHtml(message)}</div>
        ${actionLabel && actionHref ? `<a class="btn btn-dark btn-sm notification-action" href="${escapeAttribute(actionHref)}">${escapeHtml(actionLabel)}</a>` : ''}
      </div>
      <button class="notification-close" type="button" aria-label="Melding sluiten">×</button>
    `;

    stack.prepend(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    const close = () => {
      toast.classList.remove('is-visible');
      toast.classList.add('is-leaving');
      setTimeout(() => toast.remove(), 220);
    };

    setTimeout(close, options.duration || 4200);
  },
};

window.toast = window.roostar.toast;

document.addEventListener('DOMContentLoaded', () => {
  const source = document.getElementById('roostar-notifications');

  if (!source?.textContent) {
    return;
  }

  try {
    const notifications = JSON.parse(source.textContent);

    if (Array.isArray(notifications)) {
      notifications.forEach((notification, index) => {
        setTimeout(() => {
          window.roostar.toast(
            notification.message || '',
            notification.type || 'info',
            { id: notification.id || '', title: notification.title || 'Roostar' },
          );
        }, index * 120);
      });
    }
  } catch (error) {
    window.roostar.toast('Meldingen konden niet worden geladen', 'error');
  }
});

function getToastStack() {
  let stack = document.querySelector('.notification-stack');

  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'notification-stack';
    document.body.appendChild(stack);
  }

  return stack;
}

function notificationTitle(type) {
  return {
    success: 'Roostar',
    error: 'Roostar',
    warning: 'Roostar',
    info: 'Roostar',
  }[type];
}

function notificationIcon(type) {
  return {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>',
    error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v5M12 17h.01"/><path d="M10.3 4.3 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M12 3 2 20h20L12 3Z"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  }[type];
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll('`', '&#096;');
}

document.addEventListener('click', async (event) => {
  const notificationToggle = event.target.closest('[data-notification-toggle]');
  const notificationPanel = document.querySelector('[data-notification-panel]');

  if (notificationToggle && notificationPanel) {
    const isOpen = notificationPanel.classList.toggle('is-open');
    notificationToggle.setAttribute('aria-expanded', String(isOpen));

    if (isOpen) {
      markNotificationsRead(notificationToggle, notificationPanel);
    }

    return;
  }

  if (!event.target.closest('.notification-menu')) {
    document.querySelector('[data-notification-panel]')?.classList.remove('is-open');
    document.querySelector('[data-notification-toggle]')?.setAttribute('aria-expanded', 'false');
  }

  const notificationClose = event.target.closest('.notification-close');

  if (notificationClose) {
    const card = notificationClose.closest('.notification-card');
    markOneNotificationRead(card?.dataset.notificationId || '');
    card?.classList.remove('is-visible');
    card?.classList.add('is-leaving');
    setTimeout(() => card?.remove(), 220);
    return;
  }

  const copyButton = event.target.closest('[data-copy-value]');

  if (copyButton) {
    const value = copyButton.getAttribute('data-copy-value') || '';

    try {
      await navigator.clipboard.writeText(value);
      window.toast('Gekopieerd naar klembord', 'success');
    } catch (error) {
      window.toast('Kopieren lukt niet in deze browser', 'error');
    }
  }

  const modalOpen = event.target.closest('[data-open-modal]');

  if (modalOpen) {
    const modalId = modalOpen.getAttribute('data-open-modal') || '';
    const modal = modalId ? document.getElementById(modalId) : null;

    if (modal) {
      modal.hidden = false;
      document.body.classList.add('has-modal-open');
      modal.querySelector('input, select, textarea, button')?.focus();
    }

    return;
  }

  if (event.target.matches('.modal-backdrop')) {
    closeModal(event.target);
    return;
  }

  if (event.target.closest('[data-close-modal]')) {
    const modal = event.target.closest('.modal-backdrop');
    closeModal(modal);
    return;
  }

  if (event.target.closest('[data-dismiss-overlay]')) {
    const overlay = event.target.closest('.modal-backdrop') || document.querySelector('.password-overlay');
    overlay?.remove();
  }
});

function markNotificationsRead(notificationToggle, notificationPanel) {
  const unreadItems = Array.from(notificationPanel.querySelectorAll('.notification-panel-item:not(.is-read)'));
  const ids = unreadItems
    .map((item) => item.getAttribute('data-notification-id') || '')
    .filter(Boolean);

  notificationToggle.querySelector('.notification-badge')?.remove();

  const count = notificationPanel.querySelector('[data-notification-count]');
  if (count) {
    count.textContent = '0 nieuw';
  }

  unreadItems.forEach((item) => {
    item.classList.add('is-read');
    item.querySelector('.notification-unread-dot')?.remove();

    const meta = item.querySelector('small');
    if (meta && !meta.textContent.includes('gelezen')) {
      meta.textContent = `${meta.textContent} · gelezen`;
    }
  });

  if (ids.length > 0) {
    sendNotificationsRead(ids);
  }
}

function markOneNotificationRead(id = '') {
  const badge = document.querySelector('.notification-badge');
  const count = document.querySelector('[data-notification-count]');
  const current = Number.parseInt(badge?.textContent || '0', 10);
  const next = Math.max(0, current - 1);

  if (badge) {
    if (next === 0) {
      badge.remove();
    } else {
      badge.textContent = String(next);
    }
  }

  if (count) {
    count.textContent = `${next} nieuw`;
  }

  if (id) {
    sendNotificationsRead([id]);
  }
}

function sendNotificationsRead(ids) {
  const token = readCsrfToken();
  if (!token) {
    return;
  }

  const body = new URLSearchParams();
  body.set('_token', token);
  ids.forEach((id) => body.append('ids[]', id));

  fetch('/notifications/read', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
    body,
  }).catch(() => {});
}

function readCsrfToken() {
  const source = document.getElementById('roostar-csrf');
  if (!source?.textContent) {
    return '';
  }

  try {
    return JSON.parse(source.textContent).token || '';
  } catch (error) {
    return '';
  }
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    document.querySelector('.password-overlay')?.remove();
    document.querySelectorAll('.modal-backdrop:not([hidden])').forEach((modal) => closeModal(modal));
    document.querySelector('[data-notification-panel]')?.classList.remove('is-open');
    document.querySelector('[data-notification-toggle]')?.setAttribute('aria-expanded', 'false');
  }
});

function closeModal(modal) {
  if (!modal) {
    return;
  }

  modal.hidden = true;
  document.body.classList.toggle('has-modal-open', document.querySelector('.modal-backdrop:not([hidden])') !== null);
}
