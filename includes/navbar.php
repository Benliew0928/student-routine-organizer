<?php
$navItems = [
    'Dashboard' => BASE_URL . '/dashboard.php',
    'Exercise' => BASE_URL . '/modules/exercise/index.php',
    'Journal' => BASE_URL . '/modules/journal/index.php',
    'Money' => BASE_URL . '/modules/money/index.php',
    'Habits' => BASE_URL . '/modules/habits/index.php',
];
?>
<header class="topbar">
    <a class="brand" href="<?= BASE_URL; ?>/index.php"><?= APP_NAME; ?></a>
    <nav class="nav-links" aria-label="Main navigation">
        <?php if (isLoggedIn()): ?>
            <?php foreach ($navItems as $label => $href): ?>
                <a href="<?= $href; ?>"><?= escapeOutput($label); ?></a>
            <?php endforeach; ?>
            <?php if (currentUserRole() === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/admin/dashboard.php">Admin</a>
            <?php endif; ?>
            <a href="<?= BASE_URL; ?>/logout.php">Logout</a>
        <?php else: ?>
            <a href="<?= BASE_URL; ?>/login.php">Login</a>
            <a href="<?= BASE_URL; ?>/register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

