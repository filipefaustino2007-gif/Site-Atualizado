<?php
include '../conexao/conexao.php';
include 'cabecalho.php';

$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = "Por favor, preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } else {
        try {
            // 1) Verifica se já existe utilizador com este email
            $check = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn()) {
                $erro = "Este email já está registado.";
            } else {
                // 2) Hash da palavra-passe
                $hash = password_hash($senha, PASSWORD_DEFAULT);

                // 3) Inserção respeitando as colunas da tua tabela
                $stmt = $pdo->prepare("
                    INSERT INTO utilizadores
                        (nome, email, palavra_passe_hash, acesso_id, ativo, data_registo, criado_em)
                    VALUES
                        (?, ?, ?, 7, 1, NOW(), NOW())
                ");
                $stmt->execute([$nome, $email, $hash]);

                $msg = "✅ Registo concluído! Vai passar a receber as nossas publicações por email.";
            }
        } catch (Throwable $e) {
            $erro = "Erro ao registar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title>Registo | SupremeXpansion</title>
  <link rel="stylesheet" href="../css/registo.css">
</head>
<body>
  <div class="registo">
    <h2>Registar Newsletter</h2>
    <p style="color:#555; font-size:14px; margin-bottom:14px;">
      Cria a tua conta de newsletter para receber novidades. Não dá acesso à gestão.
    </p>

    <form method="POST" novalidate>
      <input type="text" name="nome" placeholder="O seu nome" required>
      <input type="email" name="email" placeholder="O seu email" required>
      <input type="password" name="senha" placeholder="Crie uma palavra-passe" required>
      <button type="submit">Registar</button>
    </form>

    <?php if ($msg):  ?><div class="msg sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="msg erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  </div>
</body>
</html>
