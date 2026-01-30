<?php
// paginas/forgot_password.php
session_start();
include '../conexao/conexao.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor insira um email válido.";
    } else {
        // Para não vazar se o email existe, mostramos sempre a mesma mensagem.
        // Mas internamente, se existir, vamos gerar um token e enviar o email.
        $stmt = $pdo->prepare("SELECT id, nome, email FROM utilizadores WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Gere token e grave
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            $expires_at = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$user['id'], $token, $expires_at]);

            // Monta link de reset
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $resetLink = $base . '/Supremexpansion/paginas/reset_password.php?token=' . urlencode($token);

            // Enviar email (reutiliza lógica de envio)
            require __DIR__ . '/../vendor/autoload.php';
            $sent = false;

            // tenta ler config_email se existir
            $config = file_exists(__DIR__ . '/../paginas/config_email.php') ? include __DIR__ . '/../paginas/config_email.php' : null;

            // função de envio simples
            function send_mail_fallback($to, $subject, $body, $from = null) {
                $headers = "From: " . ($from ?? 'no-reply@localhost') . "\r\n";
                $headers .= "Reply-To: " . ($from ?? 'no-reply@localhost') . "\r\n";
                return mail($to, $subject, $body, $headers);
            }

            // tenta PHPMailer se disponível
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                  $mail->CharSet = 'UTF-8';
                  $mail->Encoding = 'base64';
                  $mail->setLanguage('pt');
                    // se config existe, usa; senão tenta servidor local
                    if ($config) {
                        $mail->isSMTP();
                        $mail->Host = $config['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $config['smtp_user'];
                        $mail->Password = $config['smtp_pass'];
                        $mail->SMTPSecure = $config['smtp_secure'] ?? 'tls';
                        $mail->Port = $config['smtp_port'] ?? 587;
                        $fromEmail = $config['from_email'] ?? $config['smtp_user'];
                        $fromName = $config['from_name'] ?? 'SupremExpansion';
                        $mail->setFrom($fromEmail, $fromName);
                    } else {
                        // tenta envio local (pode falhar se servidor bloquear)
                        $mail->setFrom('no-reply@' . $_SERVER['HTTP_HOST'], 'SupremExpansion');
                    }

                    $mail->addAddress($user['email'], $user['nome']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Recuperação de password — SupremExpansion';
                    $mail->Body = "<p>Olá " . htmlspecialchars($user['nome']) . ",</p>
                        <p>Recebemos um pedido para repor a password da sua conta. Clique no link abaixo para escolher uma nova password (válido 1 hora):</p>
                        <p><a href=\"" . htmlspecialchars($resetLink) . "\">Repor password</a></p>
                        <p>Se não requisitou esta alteração, por favor, ignore este email.</p>
                        <p>Cumprimentos,<br>SupremExpansion</p>";
                    $mail->AltBody = "Olá {$user['nome']},\n\nPara repor a password acede a: {$resetLink}\n\nSe não pediu, ignore.";

                    $mail->send();
                    $sent = true;
                } catch (Exception $e) {
                    error_log("PHPMailer error (forgot): " . $mail->ErrorInfo);
                    $sent = false;
                }
            } else {
                // fallback mail()
                $subj = "Recuperação de password — SupremExpansion";
                $body = "Olá {$user['nome']},\n\nPara repor a password acede a: {$resetLink}\n\nSe não pediu, ignore.";
                $sent = send_mail_fallback($user['email'], $subj, $body);
            }
            // gravar log opcional (silencioso)
            $mensagem = "Se existe uma conta com esse email, recebeu um link para repor a password.";
        } else {
            // mesmo comportamento para evitar enumerar emails
            $mensagem = "Se existe uma conta com esse email, recebeu um link para repor a password.";
        }
    }
}
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Recuperar Password — SupremExpansion</title>
  <link rel="icon" type="image/png" href="../img/icon.png">
  <link rel="stylesheet" href="../css/forgot_password.css">
</head>
<body>
  <div class="password-box">
    <h1>Esqueceu-se da Password?</h1>
    <p>Insira o seu email e enviaremos um link para repor a sua password.</p>

    <?php if ($mensagem): ?>
      <div class="message success"><?=htmlspecialchars($mensagem)?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="message error"><?=htmlspecialchars($erro)?></div>
    <?php endif; ?>

    <form method="post">
      <input type="email" name="email" placeholder="email@exemplo.com" required>
      <button type="submit">Enviar link</button>
    </form>

    <a href="login.php" class="back-link">← Voltar ao login</a>
  </div>
</body>

</html>
