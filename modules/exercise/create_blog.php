<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$data = exerciseDefaultBlogData();
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    exerciseEnsureBlogTable($connection);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = exerciseBlogDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, exerciseValidateBlogData($data));

        if (!$errors) {
            $stmt = $connection->prepare('INSERT INTO exercise_blogs (user_id, title, content, blog_date) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $userId, $data['title'], $data['content'], $data['blog_date']);
            $stmt->execute();

            setFlash('success', 'Exercise blog added successfully.');
            header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=blogs');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise blog creation is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Add Exercise Blog';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Add Exercise Blog</h1>
    <p class="muted">Write a fitness reflection, workout tip, or progress update.</p>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL; ?>/modules/exercise/create_blog.php">
        <?= csrfInput(); ?>

        <label for="title">Blog Title</label>
        <input id="title" name="title" maxlength="140" value="<?= escapeOutput($data['title']); ?>" required>

        <label for="blog_date">Blog Date</label>
        <input id="blog_date" name="blog_date" type="date" value="<?= escapeOutput($data['blog_date']); ?>" required>

        <label for="content">Content</label>
        <textarea id="content" name="content" maxlength="5000" rows="8" required><?= escapeOutput($data['content']); ?></textarea>

        <div class="button-row">
            <button class="button primary" type="submit">Save Blog</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=blogs">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
