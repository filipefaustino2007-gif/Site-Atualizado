<?php
session_start();
include 'protecao.php';
include '../conexao/conexao.php';
include '../conexao/envia_email.php'; // usa PHPMailer e config_email.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $cargo_id = (int)$_POST['cargo'];
    $telefone = trim($_POST['telefone']);
    $morada = trim($_POST['morada']);
    $nif = trim($_POST['contribuinte']);

    // validação básica
    if (empty($nome) || empty($email) || empty($cargo_id)) {
        $erro = "Preencha os campos obrigatórios.";
    } else {
        // gerar password
        $senha_plain = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 10);
        $senha_hash = password_hash($senha_plain, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO utilizadores (nome, email, telefone, morada, contribuinte, palavra_passe_hash, acesso_id, ativo, data_registo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nome, $email, $telefone, $morada, $nif, $senha_hash, $cargo_id]);

            // enviar email
            $mensagem = "
            <h2>Bem-vindo à SupremeXpansion, $nome!</h2>
            <p>Estamos felizes por tê-lo na nossa equipa.</p>
            <p>Os seus dados de acesso são:</p>
            <ul>
                <li><b>Email:</b> $email</li>
                <li><b>Palavra-passe:</b> $senha_plain</li>
            </ul>
            <p>Recomendamos que altere a palavra-passe após o primeiro login por motivos de segurança.</p>
            <p><a href='http://localhost:3000/paginas/login.php' style='background:#a30101;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;'>Aceder à plataforma</a></p>
            <p style='margin-top:20px;color:#555;'>Cumprimentos,<br><b>Equipa SupremeXpansion</b></p>
            ";

            enviarEmail($email, "Bem-vindo à SupremeXpansion", $mensagem);

            header("Location: funcionarios.php?sucesso=1");
            exit;

        } catch (Throwable $e) {
            $erro = "Erro ao criar funcionário: " . $e->getMessage();
        }
    }
}

// buscar lista de cargos (acessos)
$cargos = $pdo->query("SELECT id, nome_acesso FROM acesso WHERE id < 6 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Criar Funcionário | SupremeXpansion</title>
<link rel="icon" type="image/png" href="../img/icon.png">
<link rel="stylesheet" href="../css/criar_funcionario.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br>
<main>
  <h1><i class="bi bi-plus-circle-fill"></i> Criar Novo Funcionário</h1>

  <?php if (!empty($sucesso)): ?>
    <div class="msg sucesso"><?= htmlspecialchars($sucesso) ?></div>
  <?php elseif (!empty($erro)): ?>
    <div class="msg erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label><b>Nome*</b></label>
    <input type="text" name="nome" required>

    <label><b>Email*</b></label>
    <input type="email" name="email" required>

    <label><b>Cargo*</b></label>
    <select name="cargo" required>
      <option value="">Selecionar...</option>
      <?php foreach ($cargos as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome_acesso']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Telefone</label>
    <input type="text" name="telefone">

    <label>Morada</label>
    <input type="text" name="morada">

    <label>Contribuinte</label>
    <input type="text" name="contribuinte">

    <button type="submit">Criar Funcionário</button>
  </form>
</main>
</body>
</html>
