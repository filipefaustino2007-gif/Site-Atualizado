<?php
// paginas/reset_password.php
session_start();
include '../conexao/conexao.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$erro = '';
$success = '';

if (empty($token)) {
    die('Token inválido.');
}

// Validar token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    die('Token inválido ou inexistente.');
}
if ($reset['used']) {
    die('Este link já foi utilizado.');
}
$now = new DateTime();
$expires = new DateTime($reset['expires_at']);
if ($expires < $now) {
    die('Este link expirou. Solicite novamente a recuperação da password.');
}

// Se POST -> gravar nova password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if (strlen($pass1) < 1) {
        $erro = "A password deve ter pelo menos 1 caracteres.";
    } elseif ($pass1 !== $pass2) {
        $erro = "As passwords não coincidem.";
    } else {
        // Atualizar password do user (hash)
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        // Se token tiver user_id nulo (raro), então não atualizamos
        if (!empty($reset['user_id'])) {
            $upd = $pdo->prepare("UPDATE utilizadores SET palavra_passe_hash = ? WHERE id = ?");
            $upd->execute([$hash, $reset['user_id']]);
        }
        // marcar token como usado
        $m = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $m->execute([$reset['id']]);

        $success = "Password atualizada com sucesso. Já pode iniciar sessão.";
    }
}

// Busca nome do utilizador para mostrar (opcional)
$userName = '';
if (!empty($reset['user_id'])) {
    $u = $pdo->prepare("SELECT nome, email FROM utilizadores WHERE id = ?");
    $u->execute([$reset['user_id']]);
    $usr = $u->fetch(PDO::FETCH_ASSOC);
    if ($usr) $userName = $usr['nome'] . ' (' . $usr['email'] . ')';
}
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title>Repor Password — SupremExpansion</title>
  <link rel="stylesheet" href="../css/forgot_password.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
  <div class="password-box">
    <h1>Repor Password</h1>

    <?php if ($success): ?>
      <div class="message success"><?=htmlspecialchars($success)?></div>
      <a href="login.php" class="back-link">Ir para o login <i class="bi bi-arrow-right"></i></a>
    <?php else: ?>
      <?php if ($erro): ?><div class="message error"><?=htmlspecialchars($erro)?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
        <input type="password" name="password" placeholder="Nova password" required>
        <input type="password" name="password_confirm" placeholder="Confirmar password" required>
        <button type="submit">Atualizar password</button>
      </form>

      <a href="login.php" class="back-link"><i class="bi bi-arrow-left"></i> Cancelar</a>
    <?php endif; ?>
  </div>
</body>

</html>
