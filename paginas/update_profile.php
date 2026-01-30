<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include '../conexao/conexao.php';

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_info') {
        $telefone = trim($_POST['telefone'] ?? '');
        $morada = trim($_POST['morada'] ?? '');
        $contribuinte = trim($_POST['contribuinte'] ?? '');

        $upd = $pdo->prepare("UPDATE utilizadores SET telefone=?, morada=?, contribuinte=? WHERE id=?");
        $upd->execute([$telefone, $morada, $contribuinte, $user_id]);

        header("Location: perfil.php?success=" . urlencode("Dados atualizados com sucesso."));
        exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            throw new Exception("As passwords não coincidem.");
        }
        if (strlen($new) < 6) {
            throw new Exception("A nova password deve ter pelo menos 6 caracteres.");
        }

        $stmt = $pdo->prepare("SELECT palavra_passe_hash FROM utilizadores WHERE id = ?");
        $stmt->execute([$user_id]);
        $stored = $stmt->fetchColumn();

        if (!$stored || !password_verify($current, $stored)) {
            throw new Exception("Password atual incorreta.");
        }

        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE utilizadores SET palavra_passe_hash = ? WHERE id = ?");
        $upd->execute([$new_hash, $user_id]);

        header("Location: perfil.php?success=" . urlencode("Password alterada com sucesso."));
        exit;
    }

    header("Location: perfil.php?error=" . urlencode("Ação inválida."));
    exit;

} catch (Exception $e) {
    header("Location: perfil.php?error=" . urlencode($e->getMessage()));
    exit;
}
