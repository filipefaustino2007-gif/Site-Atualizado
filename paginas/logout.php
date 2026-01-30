<?php
session_start();
require_once __DIR__ . '/../conexao/conexao.php';

// ✅ 1. Se existir cookie "lembrar_user", eliminamos também na base de dados
if (isset($_COOKIE['lembrar_user'])) {
    $token = $_COOKIE['lembrar_user'];

    // apaga o token da BD
    $stmt = $conn->prepare("UPDATE utilizadores SET token_login = NULL, token_expira = NULL WHERE token_login = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // apaga o cookie no browser
    setcookie('lembrar_user', '', time() - 3600, '/', '', false, true);
}

// ✅ 2. Destroi completamente a sessão PHP
$_SESSION = [];
session_unset();
session_destroy();

// ✅ 3. Redireciona para o login
header("Location: login.php?logout=1");
exit;
