<?php
$pageSlug = basename($_SERVER['PHP_SELF'], '.php');
$pageTitle = ucwords(str_replace('_', ' ', $pageSlug));
$userName = $_SESSION['user']['name'] ?? 'Admin';
$userRole = ucfirst($_SESSION['user']['role'] ?? 'Admin');
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $userName), 0, 2) ?: 'AD');
?>
<header class="app-navbar">
    <div class="app-navbar__left">
        <button class="icon-button d-inline-flex d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a class="app-brand" href="index.php">
            <div class="app-brand__mark">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <p class="app-brand__title">Kusum Leads</p>
                <small>Admin Control</small>
            </div>
        </a>
        <div class="app-navbar__divider d-none d-sm-flex"></div>
        <div class="app-navbar__context d-none d-sm-flex">
            <span class="context-label">Current Page</span>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
    </div>
    <div class="app-navbar__right">
        <div class="dropdown d-none d-md-block">
            <button class="ghost-button" id="quickNavDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-compass me-1"></i> Quick Navigate
                <i class="fas fa-chevron-down ms-2"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end app-navbar-dropdown" aria-labelledby="quickNavDropdown">
                <li><span class="dropdown-label">Core</span></li>
                <li><a class="dropdown-item" href="index.php">Dashboard</a></li>
                <li><a class="dropdown-item" href="leads.php">Leads</a></li>
                <li><a class="dropdown-item" href="agents.php">Agents</a></li>
                <li><a class="dropdown-item" href="campaigns.php">Campaigns</a></li>
                <li><a class="dropdown-item" href="reports.php">Reports</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><span class="dropdown-label">Messaging</span></li>
                <li><a class="dropdown-item" href="whatsapp_conversations.php">WhatsApp Chats</a></li>
                <li><a class="dropdown-item" href="whatsapp_templates.php">WhatsApp Templates</a></li>
                <li><a class="dropdown-item" href='whatsapp_template_builder.php'>Template Builder</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><span class="dropdown-label">Ops</span></li>
                <li><a class="dropdown-item" href="contacts.php">Contacts</a></li>
                <li><a class="dropdown-item" href="expenses.php">Expenses</a></li>
                <li><a class="dropdown-item" href="reminders.php">Reminders</a></li>
                <li><a class="dropdown-item" href="notifications.php">Notifications</a></li>
                <li><a class="dropdown-item" href="system_health.php">System Health</a></li>
                <li><a class="dropdown-item" href="settings.php">Settings</a></li>
            </ul>
        </div>
        <div class="app-navbar__status-pill d-none d-sm-flex">
            <span class="dot"></span> Live
        </div>
        <button class="icon-button" id="themeToggle" type="button" title="Toggle light/dark theme">
            <i class="fas fa-adjust"></i>
        </button>
        <div class="app-user-chip">
            <span class="avatar"><?= htmlspecialchars($initials) ?></span>
            <div>
                <p><?= htmlspecialchars($userName) ?></p>
                <small><?= htmlspecialchars($userRole) ?></small>
            </div>
        </div>
        <a class="btn btn-outline-light logout-btn" href="logout.php">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</header>
<script>
  (function(){
    const bodyEl = document.body;
    const key = 'admin_theme';
    const apply = t => bodyEl.classList.toggle('dark', t === 'dark');
    const saved = localStorage.getItem(key) || 'light';
    apply(saved);
    const btn = document.getElementById('themeToggle');
    if (btn) {
      btn.onclick = function () {
        const cur = bodyEl.classList.contains('dark') ? 'dark' : 'light';
        const next = cur === 'dark' ? 'light' : 'dark';
        localStorage.setItem(key, next);
        apply(next);
      };
    }

    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mq = window.matchMedia('(max-width: 992px)');

    const syncOverlayState = () => {
      const shouldOverlay = mq.matches && bodyEl.classList.contains('sidebar-open');
      bodyEl.classList.toggle('sidebar-overlay', shouldOverlay);
      bodyEl.classList.toggle('sidebar-locked', !mq.matches);
    };

    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function (event) {
        event.stopPropagation();
        bodyEl.classList.toggle('sidebar-open');
        syncOverlayState();
      });
    }

    document.addEventListener('click', function (event) {
      if (!mq.matches) return;
      if (!bodyEl.classList.contains('sidebar-open')) return;
      if (event.target.closest('.sidebar') || event.target.closest('#sidebarToggle')) return;
      bodyEl.classList.remove('sidebar-open');
      syncOverlayState();
    });

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncOverlayState);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncOverlayState);
    }
    syncOverlayState();

    const unwrapLegacyRow = (shell) => {
      const legacyRow = shell.querySelector(':scope > .row');
      if (!legacyRow) return;
      while (legacyRow.firstChild) {
        shell.insertBefore(legacyRow.firstChild, legacyRow);
      }
      legacyRow.remove();
    };

    const ensurePageContentWrapper = (mainEl) => {
      if (!mainEl) return;
      if (!mainEl.querySelector(':scope > .page-content')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'page-content';
        while (mainEl.firstChild) {
          wrapper.appendChild(mainEl.firstChild);
        }
        mainEl.appendChild(wrapper);
      }
    };

    const normalizeMain = (mainEl) => {
      if (!mainEl) return;
      mainEl.classList.add('page-wrapper');
      ['col-md-10', 'col-lg-9', 'col-xl-10', 'ms-sm-auto', 'px-md-4'].forEach(cls => mainEl.classList.remove(cls));
      ensurePageContentWrapper(mainEl);
    };

    const hydrateLegacyShell = () => {
      if (document.getElementById('appShell')) {
        normalizeMain(document.querySelector('#appShell main'));
        return;
      }

      const legacyContainer = Array.from(bodyEl.children).find(node => node.classList && node.classList.contains('container-fluid'));
      if (!legacyContainer) return;

      const shell = document.createElement('div');
      shell.className = 'app-shell app-shell--legacy';
      shell.id = 'appShell';
      shell.dataset.legacy = 'true';

      while (legacyContainer.firstChild) {
        shell.appendChild(legacyContainer.firstChild);
      }
      bodyEl.replaceChild(shell, legacyContainer);
      unwrapLegacyRow(shell);

      const sidebar = shell.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('app-sidebar');
      }

      normalizeMain(shell.querySelector('main'));
    };

    const initLayout = () => {
      const sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('app-sidebar');
      }
      hydrateLegacyShell();
      bodyEl.classList.add('admin-shell');
      bodyEl.dataset.page = <?= json_encode($pageSlug) ?>;
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initLayout, { once: true });
    } else {
      initLayout();
    }
  })();
</script>
