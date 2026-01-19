<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/language_utils.php';

$language = resolveLanguageCode($_POST['lang'] ?? null);
$_SESSION['lang'] = $language;

$redirect = buildRedirectTarget($_SERVER['HTTP_REFERER'] ?? null);

// Segurança extra: garante que começa com /
if ($redirect === '' || $redirect[0] !== '/') {
    $redirect = '/' . ltrim($redirect, '/');
}

header('Location: ' . $redirect);
exit;