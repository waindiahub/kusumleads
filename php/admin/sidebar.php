<aside class="sidebar app-sidebar" role="navigation" aria-label="Primary">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <i class="fas fa-bolt"></i>
        </div>
        <div class="brand-copy">
            <strong>kusum leads</strong>
            <small>Admin Console</small>
        </div>
    </div>

    <div class="sidebar-inner">
        <div class="sidebar-section">
            <p class="sidebar-section-label">Overview</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-section-label">Leads & Contacts</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'leads.php' ? 'active' : '' ?>" href="leads.php">
                        <i class="fas fa-users"></i>
                        <span class="nav-label">Leads</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'active' : '' ?>" href="contacts.php">
                        <i class="fas fa-address-book"></i>
                        <span class="nav-label">Contacts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'lead_profile.php' ? 'active' : '' ?>" href="lead_profile.php">
                        <i class="fas fa-id-card-alt"></i>
                        <span class="nav-label">Lead Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'lead_scoring.php' ? 'active' : '' ?>" href="lead_scoring.php">
                        <i class="fas fa-star-half-alt"></i>
                        <span class="nav-label">Lead Scoring</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-section-label">Communication</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'whatsapp_conversations.php' ? 'active' : '' ?>" href="whatsapp_conversations.php">
                        <i class="fas fa-comments"></i>
                        <span class="nav-label">Live Chats</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['whatsapp_templates.php','whatsapp_template_builder.php']) ? 'active' : '' ?>" href="whatsapp_templates.php">
                        <i class="fas fa-file-alt"></i>
                        <span class="nav-label">Templates</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'campaigns.php' ? 'active' : '' ?>" href="campaigns.php">
                        <i class="fas fa-bullhorn"></i>
                        <span class="nav-label">Campaigns</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'campaign_recipients.php' ? 'active' : '' ?>" href="campaign_recipients.php">
                        <i class="fas fa-users-cog"></i>
                        <span class="nav-label">Campaign Recipients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                        <i class="fas fa-bell"></i>
                        <span class="nav-label">Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reminders.php' ? 'active' : '' ?>" href="reminders.php">
                        <i class="fas fa-clock"></i>
                        <span class="nav-label">Reminders</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-section-label">Automation</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'flow_builder.php' ? 'active' : '' ?>" href="flow_builder.php">
                        <i class="fas fa-project-diagram"></i>
                        <span class="nav-label">Flow Builder</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-label">Reports</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <p class="sidebar-section-label">Operations</p>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agents.php' ? 'active' : '' ?>" href="agents.php">
                        <i class="fas fa-user-tie"></i>
                        <span class="nav-label">Agents</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'expenses.php' ? 'active' : '' ?>" href="expenses.php">
                        <i class="fas fa-money-bill"></i>
                        <span class="nav-label">Expenses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'activity_logs.php' ? 'active' : '' ?>" href="activity_logs.php">
                        <i class="fas fa-history"></i>
                        <span class="nav-label">Activity Logs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'system_health.php' ? 'active' : '' ?>" href="system_health.php">
                        <i class="fas fa-heartbeat"></i>
                        <span class="nav-label">System Health</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-footer">
        <a class="nav-link" href="settings.php">
            <i class="fas fa-cog"></i>
            <span class="nav-label">Settings</span>
        </a>
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</aside>
