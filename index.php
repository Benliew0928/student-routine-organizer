<?php
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <p class="eyebrow">UCCD3243 Assignment</p>
        <h1>Student Routine Organizer</h1>
        <p class="hero-copy">A calm dashboard for students to track exercise, journal reflections, money records, and habit progress in one place.</p>
        <div class="button-row">
            <a class="button primary" href="<?= BASE_URL; ?>/login.php">Login</a>
            <a class="button" href="<?= BASE_URL; ?>/register.php">Register</a>
        </div>
    </div>
</section>

<section class="module-grid dashboard-section" aria-label="Routine modules">
    <article class="module-card">
        <h2>Exercise Tracker</h2>
        <p>Record workouts, duration, calories, and exercise dates.</p>
    </article>
    <article class="module-card">
        <h2>Diary Journal</h2>
        <p>Capture daily thoughts and mood patterns for reflection.</p>
    </article>
    <article class="module-card">
        <h2>Money Tracker</h2>
        <p>Monitor income, expenses, categories, and balances.</p>
    </article>
    <article class="module-card">
        <h2>Habit Tracker</h2>
        <p>Follow habit targets, completion status, and progress.</p>
    </article>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
