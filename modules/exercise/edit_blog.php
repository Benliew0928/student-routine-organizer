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

$data = exerciseDefaultBlogData();
$errors = [];
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

    $data = [
        'title' => $blog['title'],
        'content' => $blog['content'],
        'blog_date' => $blog['blog_date'],
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = exerciseBlogDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, exerciseValidateBlogData($data));

        if (!$errors) {
            $stmt = $connection->prepare('UPDATE exercise_blogs SET title = ?, content = ?, blog_date = ? WHERE blog_id = ? AND user_id = ?');
            $stmt->bind_param('sssii', $data['title'], $data['content'], $data['blog_date'], $blogId, $userId);
            $stmt->execute();

            setFlash('success', 'Exercise blog updated successfully.');
            header('Location: ' . BASE_URL . '/modules/exercise/index.php?view=blogs');
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Exercise blog editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Exercise Blog';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel narrow">
    <h1>Edit Exercise Blog</h1>
    <p class="muted">Update your fitness post and date.</p>

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

    <form method="post" action="<?= BASE_URL; ?>/modules/exercise/edit_blog.php?id=<?= (int) $blogId; ?>">
        <?= csrfInput(); ?>

        <label for="title">Blog Title</label>
        <input id="title" name="title" maxlength="140" value="<?= escapeOutput($data['title']); ?>" required>

        <label for="blog_date">Blog Date</label>
        <input id="blog_date" name="blog_date" type="date" value="<?= escapeOutput($data['blog_date']); ?>" required>

        <label for="content">Content</label>
        <textarea id="content" name="content" maxlength="5000" rows="8" required><?= escapeOutput($data['content']); ?></textarea>

        <div class="button-row">
            <button class="button primary" type="submit">Save Changes</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/exercise/index.php?view=blogs">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
