<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/auth.php';

destroyCurrentSession();

header('Location: ' . BASE_URL . '/login.php');
exit;
