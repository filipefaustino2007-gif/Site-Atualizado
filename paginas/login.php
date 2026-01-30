<?php
session_start();

// Se o parâmetro 'brand' existir e for válido, salva na sessão
$brand = $_GET['brand'] ?? null;
$brandsPermitidas = ['supremexpansion', '3dscan2cad']; // Marcas válidas

if ($brand && in_array($brand, $brandsPermitidas)) {
    $_SESSION['brand'] = $brand;
} else {
    $_SESSION['brand'] = 'supremexpansion'; // Valor padrão
}

require_once __DIR__ . "/../conexao/conexao.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// === Carregar configurações do email ===
$config = require __DIR__ . '/../paginas/config_email.php';

$erro = "";

// === LOGIN AUTOMÁTICO POR COOKIE ===
if (!isset($_SESSION['autenticado']) && isset($_COOKIE['lembrar_user'])) {
    $token = $_COOKIE['lembrar_user'];

    $sql = "SELECT u.id, u.nome, u.email, u.acesso_id, a.nome_acesso
            FROM utilizadores u
            LEFT JOIN acesso a ON a.id = u.acesso_id
            WHERE u.token_login = ? AND u.token_expira > NOW()
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $u = $res->fetch_assoc();


        // ✅ Login automático
        $_SESSION['user_id']      = $u['id'];
        $_SESSION['email']        = $u['email'];
        $_SESSION['nome_user']    = $u['nome'];
        $_SESSION['nivel_acesso'] = $u['acesso_id'];
        $_SESSION['nome_acesso']  = $u['nome_acesso'];
        $_SESSION['autenticado']  = true;

        header("Location: index.php");
        exit;
    } else {
        setcookie('lembrar_user', '', time() - 3600, '/');
    }
}

// === LOGIN NORMAL (email + password) ===
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $sql = "SELECT u.id, u.nome, u.email, u.palavra_passe_hash, u.acesso_id, a.nome_acesso
            FROM utilizadores u
            LEFT JOIN acesso a ON a.id = u.acesso_id
            WHERE u.email = ? AND u.ativo = 1
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $row = $resultado->fetch_assoc();

        if (password_verify($password, $row['palavra_passe_hash'])) {



            // === Verificar cookie válido ===
            $check = $conn->prepare("SELECT token_login, token_expira FROM utilizadores WHERE id = ?");
            $check->bind_param("i", $row['id']);
            $check->execute();
            $ck = $check->get_result()->fetch_assoc();

            if (!empty($ck['token_login']) && strtotime($ck['token_expira']) > time()) {
                // Cookie ainda válido → login direto
                $_SESSION['user_id']      = $row['id'];
                $_SESSION['email']        = $row['email'];
                $_SESSION['nome_user']    = $row['nome'];
                $_SESSION['nivel_acesso'] = $row['acesso_id'];
                $_SESSION['nome_acesso']  = $row['nome_acesso'];
                $_SESSION['autenticado']  = true;

                header("Location: index.php");
                exit;
            }

            // === Criar código de verificação (2FA) ===
            $codigo = rand(0, 1);
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt2 = $conn->prepare("INSERT INTO verificacoes_login (user_id, codigo, expiracao) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $row['id'], $codigo, $expira);
            $stmt2->execute();

            // === Enviar email com PHPMailer ===
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $config['smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $config['smtp_user'];
                $mail->Password   = $config['smtp_pass'];
                $mail->SMTPSecure = $config['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $config['smtp_port'];

                $mail->CharSet = 'UTF-8';
                $mail->setFrom($config['from_email'], $config['from_name']);
                $mail->addAddress($row['email'], $row['nome']);
                $mail->isHTML(true);
                $mail->Subject = 'Código de Verificação - SupremeXpansion';
                $mail->Body    = "
                    <p>Olá <strong>{$row['nome']}</strong>,</p>
                    <p>O seu código de verificação é: 
                       <span style='font-size:22px; font-weight:bold; color:#a30101;'>{$codigo}</span></p>
                    <p>Este código é válido por 1 hora.</p>
                    <br>
                    <p style='font-size:12px;color:#555'>Se não fez login, ignore este email.</p>
                ";
                $mail->AltBody = "O seu código de verificação é {$codigo} (válido por 1 hora).";

                $mail->send();
            } catch (Exception $e) {
                error_log("Erro ao enviar email para {$row['email']}: " . $mail->ErrorInfo);
                $erro = "<i class='bi bi-x-circle-fill'></i> Erro ao enviar o email: {$mail->ErrorInfo}";
            }


            // Guardar dados temporários na sessão
            $_SESSION['verificar_user'] = $row['id'];
            $_SESSION['email_temp'] = $row['email'];

            header("Location: verificar_codigo.php");
            exit;
        } else {
            $erro = "Password incorreta!";
        }
    } else {
        $erro = "Utilizador não encontrado ou inativo!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>SupremeXpansion - Login</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="icon" type="image/png" href="../img/icon.png">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<?php include 'cabecalho.php'; ?>


<main class="login-container">
    <h2>Iniciar Sessão</h2>

    <form method="post" class="form-login">
        <input type="email" name="email" placeholder="Email de utilizador" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Entrar</button>
        
        <!-- 🔹 Link "Esqueceu-se da password?" -->
        <div class="forgot-link">
            <a href="forgot_password.php">Esqueceu-se da password?</a>
        </div>
        <div class="newsletter-invite">
            <h3>Ainda não tem conta?</h3>
            <p>
                Junte-se à nossa comunidade e receba as últimas novidades, projetos e inovações da SupremeXpansion diretamente no seu email.
                <br><br>
                <strong>Registe-se gratuitamente</strong> e fique a par das novas publicações, projetos e tecnologias 3D que estamos a desenvolver.
            </p>
            <a href="registo.php" class="btn-registar"><i class="bi bi-envelope-heart-fill"></i> Quero receber novidades</a>
        </div>

    </form>

     <?php if (!empty($erro)): ?>
        <p class="erro-login"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
        <p class="msg success" style="color:green; text-align:center;">
            Sessão terminada com sucesso.
        </p>
    <?php endif; ?>
</main>
</body>
</html>
