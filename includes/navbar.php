<?php
$navItems = [
    'Dashboard' => BASE_URL . '/dashboard.php',
    'Exercise' => BASE_URL . '/modules/exercise/index.php',
    'Journal' => BASE_URL . '/modules/journal/index.php',
    'Money' => BASE_URL . '/modules/money/index.php',
    'Habits' => BASE_URL . '/modules/habits/index.php',
];
$isAuthenticated = isLoggedIn();
$brandHref = $isAuthenticated ? BASE_URL . '/dashboard.php' : BASE_URL . '/index.php';
?>

<?php if ($isAuthenticated): ?>
    <?php
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    $activeItem = 'dashboard';
    if (strpos($currentScript, 'modules/exercise') !== false) {
        $activeItem = 'exercise';
    } elseif (strpos($currentScript, 'modules/journal') !== false) {
        $activeItem = 'journal';
    } elseif (strpos($currentScript, 'modules/money') !== false) {
        $activeItem = 'money';
    } elseif (strpos($currentScript, 'modules/habits') !== false) {
        $activeItem = 'habits';
    }
    ?>
    <aside class="dashboard-sidebar">
        <a href="<?= BASE_URL; ?>/dashboard.php" class="sidebar-logo">
            <div class="logo-spike"></div>
            <span class="sidebar-logo-text"><?= APP_NAME; ?></span>
        </a>
        
        <nav class="sidebar-nav">
            <a href="<?= BASE_URL; ?>/dashboard.php" class="sidebar-nav-item <?= $activeItem === 'dashboard' ? 'is-active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL; ?>/modules/exercise/index.php" class="sidebar-nav-item <?= $activeItem === 'exercise' ? 'is-active' : ''; ?>">
                <i class="bi bi-activity"></i> Exercise
            </a>
            <a href="<?= BASE_URL; ?>/modules/journal/index.php" class="sidebar-nav-item <?= $activeItem === 'journal' ? 'is-active' : ''; ?>">
                <i class="bi bi-journal-text"></i> Journal
            </a>
            <a href="<?= BASE_URL; ?>/modules/money/index.php" class="sidebar-nav-item <?= $activeItem === 'money' ? 'is-active' : ''; ?>">
                <i class="bi bi-piggy-bank"></i> Money
            </a>
            <a href="<?= BASE_URL; ?>/modules/habits/index.php" class="sidebar-nav-item <?= $activeItem === 'habits' ? 'is-active' : ''; ?>">
                <i class="bi bi-check2-circle"></i> Habits
            </a>
            
            <a href="<?= BASE_URL; ?>/logout.php" class="sidebar-nav-item" style="margin-top: auto; color: var(--color-journal, #b42318);">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </nav>
        
        <div class="sidebar-footer" style="font-size: 11px; color: var(--color-muted, #647064); padding: 0 14px; margin-top: 12px; font-family: var(--font-family-2, sans-serif);">
            &copy; <?= date('Y'); ?> <?= APP_NAME; ?>
        </div>
    </aside>

    <div class="dashboard-mobile-header">
        <div class="sidebar-logo">
            <div class="logo-spike"></div>
            <span class="sidebar-logo-text"><?= APP_NAME; ?></span>
        </div>
        <a href="<?= BASE_URL; ?>/logout.php" class="dashboard-logout-btn">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
<?php else: ?>
    <header class="topbar">
        <a class="brand" href="<?= $brandHref; ?>"><?= APP_NAME; ?></a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="<?= BASE_URL; ?>/login.php">Login</a>
            <a href="<?= BASE_URL; ?>/register.php">Register</a>
        </nav>
    </header>
<?php endif; ?>
