<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'protecao.php';
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

$user_id = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, nome, email, telefone, morada, contribuinte, palavra_passe_hash, acesso_id FROM utilizadores WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Utilizador não encontrado.");

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title>Perfil | SupremeXpansion</title>
<link rel="icon" type="image/png" href="../img/icon.png">
<link rel="stylesheet" href="../css/perfil.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <br>
<main class="container">
  <h1>Meu Perfil</h1>
  <div class="meta">Pode alterar os seus dados pessoais e a sua password.</div>

  <?php if ($success): ?><div class="msg success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="msg error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="grid">
    <form class="card" method="POST" action="update_profile.php">
      <input type="hidden" name="action" value="update_info">
      <div class="field"><label>Nome</label><input type="text" value="<?= htmlspecialchars($user['nome']) ?>" readonly></div>
      <div class="field"><label>Email</label><input type="text" value="<?= htmlspecialchars($user['email']) ?>" readonly></div>
      <div class="field"><label>Telefone</label><input type="text" name="telefone" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>"></div>
      <div class="field"><label>Morada</label><input type="text" name="morada" value="<?= htmlspecialchars($user['morada'] ?? '') ?>"></div>
      <div class="field"><label>Contribuinte (NIF)</label><input type="text" name="contribuinte" value="<?= htmlspecialchars($user['contribuinte'] ?? '') ?>"></div>
      <button class="btn" type="submit">Guardar alterações</button>
    </form>

    <div class="pw-card">
      <form method="POST" action="update_profile.php" onsubmit="return validatePassForm();">
        <input type="hidden" name="action" value="change_password">
        <h3>Alterar Password</h3>
        <div class="field"><label>Atual</label><input type="password" name="current_password" required></div>
        <div class="field"><label>Nova</label><input type="password" id="new_password" name="new_password" required></div>
        <div class="field"><label>Confirmar</label><input type="password" id="confirm_password" name="confirm_password" required></div>
        <button class="btn" type="submit">Alterar Password</button>
      </form>
    </div>
  </div>
</main>

<script>
function validatePassForm() {
  const a = document.getElementById('new_password').value;
  const b = document.getElementById('confirm_password').value;
  if (a !== b) { alert('As passwords não coincidem.'); return false; }
  if (a.length < 6) { alert('A password deve ter pelo menos 6 caracteres.'); return false; }
  return true;
}
</script>
</body>
</html>
