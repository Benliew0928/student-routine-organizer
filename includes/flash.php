<?php
declare(strict_types=1);

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function displayFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $type = htmlspecialchars($_SESSION['flash']['type'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_SESSION['flash']['message'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['flash']);

    echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
}

