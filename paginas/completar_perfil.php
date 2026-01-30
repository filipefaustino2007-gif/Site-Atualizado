<?php
session_start();
include '../conexao/conexao.php';
include 'cabecalho.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $morada = trim($_POST['morada']);
    $contrib = trim($_POST['contribuinte']);

    $sql = $pdo->prepare("
        UPDATE utilizadores 
        SET nome=?, telefone=?, morada=?, contribuinte=? 
        WHERE id=?
    ");
    $sql->execute([$nome, $telefone, $morada, $contrib, $_SESSION['user_id']]);

    $msg = "Dados atualizados com sucesso!";

    header("Location: index.php");

}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Completar Perfil</title>
<link rel="stylesheet" href="../css/completar_perfil.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br><br>
<main>
    <h2>Bem-vindo! <i class="bi bi-stars"></i></h2>

    <p>Antes de continuar, por favor preencha os seus dados.</p>
    <br>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Nome completo</label>
        <input type="text" name="nome" required>

        <label>Telefone</label>
        <input type="text" name="telefone" required>

        <label>Morada</label>
        <input type="text" name="morada" required>

        <label>NIF / Contribuinte</label>
        <input type="text" name="contribuinte" required>

        <!-- TEXTO INFORMATIVO -->
        <p style="
            margin-top: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-style: italic;
            color: #666;
            text-align: center;
        ">
            Esta informação é obrigatória para efeitos contabilísticos e faturação.
        </p>

        <button type="submit">Guardar Dados</button>
    </form>

</main>

</body>
</html>
