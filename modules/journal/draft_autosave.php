<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

function journalDraftJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    journalDraftJson(405, ['success' => false, 'message' => 'Method not allowed.']);
}

 $accessState = sessionAccessState();
if (!isLoggedIn()) {
    if ($accessState === 'expired') {
        expireAuthenticatedSession();
        journalDraftJson(401, ['success' => false, 'message' => 'Your session expired after 30 minutes of inactivity. Please log in again.']);
    }

    journalDraftJson(401, ['success' => false, 'message' => 'Please log in again.']);
}

refreshAuthenticatedSession();

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    journalDraftJson(403, [
        'success' => false,
        'message' => 'Your session expired. Refresh and try again.',
    ]);
}

$draftId = null;
$rawDraftIdValue = $_POST['draft_id'] ?? '';
if (is_array($rawDraftIdValue)) {
    journalDraftJson(422, [
        'success' => false,
        'message' => 'The draft reference is invalid.',
    ]);
}

$rawDraftId = trim((string) $rawDraftIdValue);
if ($rawDraftId !== '') {
    $validatedId = filter_var(
        $rawDraftId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedId === false) {
        journalDraftJson(422, [
            'success' => false,
            'message' => 'The draft reference is invalid.',
        ]);
    }

    $draftId = (int) $validatedId;
}

$data = journalDataFromRequest($_POST);
$errors = journalValidateDraftData($data);

if ($errors) {
    journalDraftJson(422, ['success' => false, 'message' => $errors[0]]);
}

if ($draftId === null && !journalDraftHasMeaningfulContent($data)) {
    journalDraftJson(422, [
        'success' => false,
        'message' => 'Add something before saving a draft.',
    ]);
}

try {
    $connection = getDatabaseConnection();
    $userId = (int) $_SESSION['user_id'];
    $savedId = journalSaveDraft($connection, $userId, $draftId, $data);

    if ($savedId === null) {
        journalDraftJson(404, [
            'success' => false,
            'message' => 'Journal draft was not found.',
        ]);
    }

    $saved = journalLoadDraftForUser($connection, $savedId, $userId);
    if ($saved === null) {
        journalDraftJson(404, [
            'success' => false,
            'message' => 'Journal draft was not found.',
        ]);
    }

    $savedTimestamp = strtotime((string) $saved['updated_at']);

    journalDraftJson(200, [
        'success' => true,
        'draft_id' => $savedId,
        'saved_at' => date(DATE_ATOM, $savedTimestamp),
        'saved_label' => 'Draft saved at ' . date('g:i A', $savedTimestamp),
    ]);
} catch (Throwable $exception) {
    logApplicationException($exception, 'journal draft autosave');
    journalDraftJson(500, [
        'success' => false,
        'message' => 'Could not save the draft. Please retry.',
    ]);
}
