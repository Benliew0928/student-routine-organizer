<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/habit_helpers.php';

requireLogin();
$userId = (int) $_SESSION['user_id'];
$logId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$logId) { setFlash('error', 'Invalid trail entry.'); header('Location: ' . BASE_URL . '/modules/habits/journey.php'); exit; }
$log = null; $errors = []; $pageError = null;
try {
    $connection = getDatabaseConnection();
    $log = habitLoadLogForUser($connection, (int) $logId, $userId);
    if (!$log) { setFlash('error', 'Trail entry not found.'); header('Location: ' . BASE_URL . '/modules/habits/journey.php'); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = cleanInput((string) ($_POST['action'] ?? 'update'));
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { $errors[] = 'Your session token expired. Please try again.'; }
        elseif ($action === 'delete') { $stmt = $connection->prepare('UPDATE habit_logs SET deleted_at = NOW() WHERE log_id = ? AND user_id = ? AND deleted_at IS NULL'); $stmt->bind_param('ii', $logId, $userId); $stmt->execute(); setFlash('success', 'Trail entry removed.'); header('Location: ' . BASE_URL . '/modules/habits/journey.php'); exit; }
        else {
            $status = cleanInput((string) ($_POST['completion_status'] ?? ''));
            $reflection = cleanInput((string) ($_POST['reflection_note'] ?? ''));
            if (!array_key_exists($status, habitLogStatusOptions())) { $errors[] = 'Choose a valid quest status.'; }
            if (mb_strlen($reflection) > 255) { $errors[] = 'Reflections must be 255 characters or fewer.'; }
            if (!$errors) { $completedAt = $status === 'completed' ? ($log['completed_at'] ?? date('Y-m-d H:i:s')) : null; $stmt = $connection->prepare('UPDATE habit_logs SET completion_status = ?, completed_at = ?, reflection_note = ? WHERE log_id = ? AND user_id = ? AND deleted_at IS NULL'); $stmt->bind_param('sssii', $status, $completedAt, $reflection, $logId, $userId); $stmt->execute(); setFlash('success', 'Trail entry updated.'); header('Location: ' . BASE_URL . '/modules/habits/journey.php?week=' . rawurlencode((new DateTimeImmutable($log['scheduled_date']))->modify('monday this week')->format('Y-m-d'))); exit; }
            $log['completion_status'] = $status; $log['reflection_note'] = $reflection;
        }
    }
} catch (Throwable $exception) { $pageError = 'This trail entry is unavailable right now. Please check the database setup.'; }
$pageTitle = 'Trail Entry'; require __DIR__ . '/../../includes/header.php';
?>
<section class="log-page"><a class="sanctuary-back-link" href="<?= BASE_URL; ?>/modules/habits/journey.php?week=<?= $log ? escapeOutput((new DateTimeImmutable($log['scheduled_date']))->modify('monday this week')->format('Y-m-d')) : ''; ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Momentum Trail</a><?php if ($pageError): ?><div class="alert alert-error"><?= escapeOutput($pageError); ?></div><?php elseif ($log): $meta = habitRealmOptions()[$log['realm']]; ?><header class="log-header"><span class="journey-realm realm-<?= escapeOutput($log['realm']); ?>"><i class="bi <?= escapeOutput($meta['icon']); ?>" aria-hidden="true"></i></span><div><p class="sanctuary-kicker"><?= escapeOutput($meta['label']); ?> · <?= escapeOutput((new DateTimeImmutable($log['scheduled_date']))->format('l, j F')); ?></p><h1><?= escapeOutput($log['habit_name']); ?></h1><p>Refine this dated moment without changing the quest blueprint.</p></div></header><?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= escapeOutput($error); ?></p><?php endforeach; ?></div><?php endif; ?><form method="post" action="<?= BASE_URL; ?>/modules/habits/log.php?id=<?= (int) $logId; ?>" class="log-form"><?= csrfInput(); ?><input type="hidden" name="action" value="update"><label for="completion_status">How did this quest land?</label><select id="completion_status" name="completion_status"><?php foreach (habitLogStatusOptions() as $value => $label): ?><option value="<?= escapeOutput($value); ?>" <?= $log['completion_status'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option><?php endforeach; ?></select><label for="reflection_note">Reflection <span>optional</span></label><textarea id="reflection_note" name="reflection_note" maxlength="255" placeholder="A small note for your future self."><?= escapeOutput($log['reflection_note'] ?? ''); ?></textarea><div class="blueprint-actions"><button class="sanctuary-button sanctuary-button-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i>Save trail entry</button><a class="sanctuary-button sanctuary-button-quiet" href="<?= BASE_URL; ?>/modules/habits/edit.php?id=<?= (int) $log['habit_id']; ?>"><i class="bi bi-pencil" aria-hidden="true"></i>Edit blueprint</a></div></form><form method="post" action="<?= BASE_URL; ?>/modules/habits/log.php?id=<?= (int) $logId; ?>" class="log-delete-form"><?= csrfInput(); ?><input type="hidden" name="action" value="delete"><button class="sanctuary-link-danger" type="submit" data-confirm="Remove this dated trail entry?"><i class="bi bi-trash3" aria-hidden="true"></i>Remove trail entry</button></form><?php endif; ?></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
