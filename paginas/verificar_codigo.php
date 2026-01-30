<?php
session_start();
require_once __DIR__ . "/../conexao/conexao.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $codigo = trim($_POST['codigo'] ?? '');
    $user_id = $_SESSION['verificar_user'] ?? null;

    if (!$user_id) {
        die("Sessão expirada. Por favor, volte a iniciar sessão.");
    }

    // Buscar código válido
    $sql = "SELECT * FROM verificacoes_login 
            WHERE user_id = ? AND codigo = ? AND confirmado = 0 AND expiracao > NOW()
            ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $codigo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
    // Código válido — marcar como confirmado
    $upd = $conn->prepare("UPDATE verificacoes_login SET confirmado = 1 WHERE user_id = ? AND codigo = ?");
    $upd->bind_param("is", $user_id, $codigo);
    $upd->execute();

    // === Buscar novamente os dados do utilizador ===
    $sqlUser = "SELECT u.id, u.nome, u.email, u.acesso_id, a.nome_acesso
                FROM utilizadores u
                LEFT JOIN acesso a ON a.id = u.acesso_id
                WHERE u.id = ? LIMIT 1";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $userRes = $stmtUser->get_result();

    if ($res->num_rows === 1) {
        // ✅ Código válido — marcar como confirmado
        $upd = $conn->prepare("UPDATE verificacoes_login SET confirmado = 1 WHERE user_id = ? AND codigo = ?");
        $upd->bind_param("is", $user_id, $codigo);
        $upd->execute();

        // ✅ Buscar novamente todos os dados do utilizador
        $sqlUser = "SELECT 
                        u.id AS user_id,
                        u.nome,
                        u.nome,
                        u.email,
                        u.acesso_id,
                        a.nome_acesso
                    FROM utilizadores u
                    LEFT JOIN acesso a ON a.id = u.acesso_id
                    WHERE u.id = ? LIMIT 1";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->bind_param("i", $user_id);
        $stmtUser->execute();
        $userRes = $stmtUser->get_result();

        if ($userRes && $userRes->num_rows === 1) {
            $user = $userRes->fetch_assoc();

            // ✅ Reconstruir completamente a sessão
            $_SESSION['user_id']      = $user['user_id'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['nome_user']    = !empty($user['nome']) ? $user['nome'] : $user['nome'];
            $_SESSION['nivel_acesso'] = $user['acesso_id'];
            $_SESSION['nome_acesso']  = $user['nome_acesso'];
            $_SESSION['autenticado']  = true;
        } else {
            die("Erro ao reconstruir sessão: utilizador não encontrado.");
        }

        // ✅ Lembrar utilizador (se selecionado)
        if (isset($_POST['lembrar']) && $_POST['lembrar'] === '1') {
            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+15 days'));

            $stmtToken = $conn->prepare("UPDATE utilizadores SET token_login = ?, token_expira = ? WHERE id = ?");
            $stmtToken->bind_param("ssi", $token, $expira, $user_id);
            $stmtToken->execute();

            // Cookie válido por 15 dias
            setcookie('lembrar_user', $token, time() + (15 * 24 * 60 * 60), "/", "", false, true);
        }

        // ✅ Limpar flag temporária e redirecionar
        unset($_SESSION['verificar_user']);
        header("Location: index.php");
        exit;
    }



    } else {
        $erro = "Código inválido ou expirado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../img/icon.png">
    <title>Verificação de Código | SupremeXpansion</title>
    <link rel="stylesheet" href="../css/verificar_codigo.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<div class="verify-container">
    <div class="verify-box">
        <img src="../img/logo_branco.svg" class="verify-logo" alt="SupremeXpansion">
        <h2>Verificação de Código</h2>
        <p>Introduza o código de 6 dígitos que foi enviado para o seu email.</p>
        <form method="POST">
            <input type="text" name="codigo" maxlength="6" required placeholder="######" class="input-code">
            <div class="lembrar">
                <input type="checkbox" name="lembrar" value="1" id="lembrar">
                <label for="lembrar">Lembrar-me por 15 dias</label>
            </div>
            <button type="submit" class="btn-confirm">Confirmar</button>
        </form>
        <?php if (!empty($erro)): ?>
            <p class="erro"><?= htmlspecialchars($erro) ?></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

</html>
