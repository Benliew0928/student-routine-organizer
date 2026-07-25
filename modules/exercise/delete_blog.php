<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$blogId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$blogId) {
    setFlash('error', 'Invalid exercise blog.');
    header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=blogs');
    exit;
}

$blog = null;
$pageError = null;

try {
    $connection = getDatabaseConnection();
    exerciseEnsureBlogTable($connection);
    $blog = exerciseBlogLoadForUser($connection, (int) $blogId, $userId);

    if (!$blog) {
        setFlash('error', 'Exercise blog was not found.');
        header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=blogs');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
            header('Location: ' . BASE_URL . '/modules/exercise/delete_blog.php?id=' . (int) $blogId);
            exit;
        }

        $stmt = $connection->prepare('DELETE FROM exercise_blogs WHERE blog_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $blogId, $userId);
        $stmt->execute();

        setFlash('success', 'Exercise blog deleted successfully.');
        header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=blogs');
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise blog deletion is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Delete Exercise Blog';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Delete Exercise Blog</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php elseif ($blog): ?>
        <p class="muted">Confirm that you want to remove this blog post. This action cannot be undone.</p>

        <dl class="detail-list">
            <div>
                <dt>Title</dt>
                <dd><?= escapeOutput($blog['title']); ?></dd>
            </div>
            <div>
                <dt>Date</dt>
                <dd><?= escapeOutput($blog['blog_date']); ?></dd>
            </div>
        </dl>

        <form method="post" action="<?= BASE_URL; ?>/modules/exercise/delete_blog.php?id=<?= (int) $blogId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button primary danger-primary" type="submit">Delete Blog</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=blogs">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
