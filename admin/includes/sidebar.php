<nav class="admin-sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-clock"></i> Houra Admin</h3>
    </div>
    
    <div class="sidebar-menu">
        <ul>
            <li>
                <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span class="menu-text">Manage Users</span>
                </a>
            </li>
            <li>
                <a href="services.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                    <i class="fas fa-hands-helping"></i>
                    <span class="menu-text">Services</span>
                </a>
            </li>
            <li>
                <a href="requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span class="menu-text">Service Requests</span>
                </a>
            </li>
            <li>
                <a href="proofs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'proofs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i>
                    <span class="menu-text">Task Proofs</span>
                </a>
            </li>
            <li>
                <a href="transactions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <span class="menu-text">Transactions</span>
                </a>
            </li>
            <li>
                <a href="disputes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'disputes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="menu-text">Disputes</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reports</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs"></i>
                    <span class="menu-text">System Settings</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn btn-danger btn-sm m-3">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>