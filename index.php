<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectAfterLogin(currentUserRole() ?? 'student');
} else {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
