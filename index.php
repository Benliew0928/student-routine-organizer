<?php
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <p class="eyebrow">UCCD3243 Assignment</p>
        <h1>Student Routine Organizer</h1>
        <p class="hero-copy">A PHP and MySQL web application for exercise, journal, money, and habit tracking.</p>
        <div class="button-row">
            <a class="button primary" href="<?= BASE_URL; ?>/login.php">Login</a>
            <a class="button" href="<?= BASE_URL; ?>/register.php">Register</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

