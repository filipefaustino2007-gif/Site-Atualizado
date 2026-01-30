<?php
declare(strict_types=1);

try {
    include '../conexao/conexao.php';

    if (empty($_POST['id']) || empty($_POST['estado'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'parâmetros inválidos']);
        exit;
    }

    $id     = (int) $_POST['id'];
    $estado = trim((string) $_POST['estado']);

    $permitidos = ['pendente','aceite','recusada','adjudicada','cancelada','arquivada'];

    if (!in_array($estado, $permitidos, true)) {
        echo json_encode(['ok' => false, 'error' => 'estado inválido']);
        exit;
    }

    // Confirmar se a proposta existe
    $chk = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
    $chk->execute([$id]);
    $proposta = $chk->fetch(PDO::FETCH_ASSOC);

    // Preferências de email (vindas do preview)
    // Se as colunas não existirem por algum motivo, fallback = 1 (envia)
    $email_send_all         = (int)($proposta['email_send_all'] ?? 1);
    $email_send_credenciais = (int)($proposta['email_send_credenciais'] ?? 1);

    $podeEnviarCredenciais = ($email_send_all === 1 && $email_send_credenciais === 1);

    if (!$proposta) {
        echo json_encode(['ok' => false, 'error' => 'proposta não encontrada']);
        exit;
    }

    // Atualizar estado
    $stmt = $pdo->prepare("UPDATE propostas SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);

    // SE FOR ADJUDICADA → CRIAR UTILIZADOR
    if ($estado === 'adjudicada') {

        $email  = trim(strtolower($proposta['email_cliente']));
        $nome   = $proposta['nome_cliente'];
        $tel    = $proposta['telefone_cliente'] ?? null;

        // Verificar se já existe utilizador
        $chkUser = $pdo->prepare("SELECT id FROM utilizadores WHERE LOWER(email)=?");
        $chkUser->execute([$email]);
        $user = $chkUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {

            // Criar password
            $senha = substr(bin2hex(random_bytes(4)), 0, 8);
            $hash  = password_hash($senha, PASSWORD_DEFAULT);

            // Criar utilizador
            $ins = $pdo->prepare("
                INSERT INTO utilizadores
                    (nome, email, telefone, palavra_passe_hash, acesso_id, ativo, data_registo)
                VALUES (?, ?, ?, ?, 6, 1, NOW())
            ");
            $ins->execute([$nome, $email, $tel, $hash]);

            $id_user = $pdo->lastInsertId();

            // Atualizar tabela clientes
            $up = $pdo->prepare("
                UPDATE clientes SET registado = 1, id_utilizador = ?
                WHERE LOWER(email) = ?
            ");
            $up->execute([$id_user, $email]);

                        // Enviar email (só se permitido no preview)
            if ($podeEnviarCredenciais) {

                require '../vendor/autoload.php';
                $config = require '../paginas/config_email.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = $config['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $config['smtp_user'];
                    $mail->Password = $config['smtp_pass'];
                    $mail->SMTPSecure = $config['smtp_secure'] === 'tls'
                        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = $config['smtp_port'];
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom($config['from_email'], $config['from_name']);
                    $mail->addAddress($email, $nome);
                    $mail->Subject = "Acesso à sua conta SupremeXpansion";
                    $mail->isHTML(true);

                    $mail->Body = "
                        <div style='font-family:Poppins,sans-serif; background:#fafafa; padding:20px;'>
                            <h2 style='color:#a30101;'>Olá $nome,</h2>
                            <p>A sua proposta foi adjudicada com sucesso!</p>
                            <p>Foi criada uma conta:</p>
                            <b>Email:</b> $email<br>
                            <b>Password:</b> $senha<br><br>
                            <a href='http://localhost:3000/paginas/login.php'
                               style='background:#a30101;color:white;padding:10px;border-radius:8px;text-decoration:none;'>
                               Entrar no Portal
                            </a>
                        </div>
                    ";

                    $mail->send();

                } catch (Exception $e) {
                    error_log("Falha no email adjudicação: " . $mail->ErrorInfo);
                }

            } else {
                error_log("Email de credenciais bloqueado (preferências). Proposta ID: " . $id);
            }

        }
    }

    echo json_encode(['ok' => true, 'estado' => $estado]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
