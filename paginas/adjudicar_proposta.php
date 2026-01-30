<?php
include '../conexao/conexao.php';
require '../vendor/autoload.php'; // PHPMailer
$config = require '../paginas/config_email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['id'])) {
    die("Proposta inválida.");
}

$id_proposta = (int)$_GET['id'];

// === 1️⃣ Buscar dados da proposta ===
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
$stmt->execute([$id_proposta]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposta) die("Proposta não encontrada.");

$email_cliente = trim(strtolower($proposta['email_cliente']));
$nome_cliente  = $proposta['nome_cliente'];
$telefone      = $proposta['telefone_cliente'] ?? null;

// === 2️⃣ Atualizar estado da proposta ===
// === 2️⃣ (NOVO) Verificar estado anterior para não reenviar ===
$estadoAnterior = strtolower(trim($proposta['estado'] ?? ''));
$jaAdjudicada = ($estadoAnterior === 'adjudicada');

// === 3️⃣ Atualizar estado da proposta ===
$update = $pdo->prepare("UPDATE propostas SET estado = 'adjudicada' WHERE id = ?");
$update->execute([$id_proposta]);

// (NOVO) só envia email se acabou de mudar para adjudicada
// (NOVO) só envia email se acabou de mudar para adjudicada
if (!$jaAdjudicada) {

    // Código da proposta (em vez do id)
    $codigoProposta = trim((string)($proposta['codigo'] ?? ''));
    if ($codigoProposta === '') {
        // fallback se por algum motivo não existir
        $codigoProposta = (string)$id_proposta;
    }

    // === (NOVO) Buscar contabilistas (robusto: acesso_id OU nivel_acesso) ===
    $stmtCont = $pdo->prepare("
        SELECT nome, email
        FROM utilizadores
        WHERE (acesso_id = 3)
          AND email IS NOT NULL AND email <> ''
    ");
    $stmtCont->execute();
    $contabilistas = $stmtCont->fetchAll(PDO::FETCH_ASSOC);

    // LOG: quantos contabilistas encontrou
    error_log("Adjudicar proposta {$codigoProposta}: contabilistas encontrados = " . count($contabilistas));

    if (!empty($contabilistas)) {

        $linkProposta = "http://localhost:3000/Supremexpansion/paginas/ver_proposta.php?id=" . (int)$id_proposta;

        $nomeCliente = $proposta['nome_cliente'] ?? '';
        $emailCliente = $proposta['email_cliente'] ?? '';
        $telefoneCliente = $proposta['telefone_cliente'] ?? ($proposta['telefone'] ?? '');

        $nomeProjeto = $proposta['nome_projeto'] ?? '';
        $nomeObra    = $proposta['nome_obra'] ?? '';
        $totalFinal  = $proposta['total_final'] ?? ($proposta['valor_total'] ?? '');

        try {
            $mailC = new PHPMailer(true);

            // DEBUG (ativa só para testar; depois mete 0)
            // $mailC->SMTPDebug = 2;
            // $mailC->Debugoutput = 'error_log';

            $mailC->isSMTP();
            $mailC->Host       = $config['smtp_host'];
            $mailC->SMTPAuth   = true;
            $mailC->Username   = $config['smtp_user'];
            $mailC->Password   = $config['smtp_pass'];
            $mailC->SMTPSecure = $config['smtp_secure'] === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
            $mailC->Port       = $config['smtp_port'];
            $mailC->CharSet    = 'UTF-8';

            $mailC->setFrom($config['from_email'], $config['from_name']);

            foreach ($contabilistas as $c) {
                $em = trim((string)($c['email'] ?? ''));
                if ($em !== '') {
                    $mailC->addAddress($em, $c['nome'] ?? $em);
                }
            }

            $mailC->Subject = "Proposta adjudicada ({$codigoProposta}) | SupremeXpansion";
            $mailC->isHTML(true);

            $mailC->AltBody = "Foi adjudicada a proposta {$codigoProposta}. Ver: {$linkProposta}";

            $mailC->Body = "
              <div style='font-family:Poppins,Arial,sans-serif;background:#fafafa;padding:20px;border-radius:12px;'>
                <h2 style='color:#a30101;margin:0 0 10px;'>Proposta adjudicada</h2>

                <p style='margin:0 0 12px;'>
                  Foi adjudicada a proposta <b>{$codigoProposta}</b>.
                </p>

                <div style='background:#fff;border:1px solid #eee;border-radius:10px;padding:12px;'>
                  <p style='margin:0 0 6px;'><b>Cliente:</b> {$nomeCliente}</p>
                  <p style='margin:0 0 6px;'><b>Email:</b> {$emailCliente}</p>
                  " . (!empty($telefoneCliente) ? "<p style='margin:0 0 6px;'><b>Telefone:</b> {$telefoneCliente}</p>" : "") . "
                  " . (!empty($nomeProjeto) ? "<p style='margin:0 0 6px;'><b>Projeto:</b> {$nomeProjeto}</p>" : "") . "
                  " . (!empty($nomeObra) ? "<p style='margin:0 0 6px;'><b>Obra:</b> {$nomeObra}</p>" : "") . "
                  " . (!empty($totalFinal) ? "<p style='margin:0;'><b>Total:</b> {$totalFinal}</p>" : "") . "
                </div>

                <p style='margin:14px 0 10px;'>Abrir proposta no portal:</p>

                <a href='{$linkProposta}'
                  style='display:inline-block;background:#a30101;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700;'>
                  Ver Proposta Adjudicada
                </a>

                <p style='margin-top:16px;font-size:12px;color:#666;'>
                  (Mensagem automática para contabilista)
                </p>
              </div>
            ";

            $mailC->send();
            error_log("Email contabilista enviado com sucesso para proposta {$codigoProposta}.");

        } catch (Exception $e) {
            error_log("Erro ao enviar email ao contabilista (proposta {$codigoProposta}): " . $mailC->ErrorInfo);
        }
    } else {
        error_log("Sem contabilistas para notificar (proposta {$codigoProposta}). Verifica acesso_id/nivel_acesso e emails.");
    }
}


// === 3️⃣ Verificar se já existe conta de utilizador ===
$stmtUser = $pdo->prepare("
    SELECT id 
    FROM utilizadores 
    WHERE LOWER(email) = LOWER(TRIM(?))
");
$stmtUser->execute([$email_cliente]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    // Criar password aleatória
    $senha = substr(bin2hex(random_bytes(4)), 0, 8);
    $hash = password_hash($senha, PASSWORD_DEFAULT);

    // Criar novo utilizador
    $ins = $pdo->prepare("
        INSERT INTO utilizadores 
            (nome, email, telefone, palavra_passe_hash, acesso_id, ativo, data_registo)
        VALUES (?, ?, ?, ?, 6, 1, NOW())
    ");
    $ins->execute([$nome_cliente, $email_cliente, $telefone, $hash]);

    $id_utilizador = $pdo->lastInsertId();

    // Atualizar tabela CLIENTES
    $up = $pdo->prepare("
        UPDATE clientes 
        SET registado = 1, id_utilizador = ?
        WHERE LOWER(email) = LOWER(TRIM(?))
    ");
    $up->execute([$id_utilizador, $email_cliente]);

    // === 4️⃣ Enviar email com credenciais ===
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'] === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($email_cliente, $nome_cliente);
        $mail->Subject = "Acesso à sua conta SupremeXpansion";
        $mail->isHTML(true);

        $mail->Body = "
        <div style='font-family: Poppins, sans-serif; background:#fafafa; padding:20px; border-radius:10px;'>
            <h2 style='color:#a30101;'>Olá $nome_cliente,</h2>
            <p>A sua proposta foi <b>adjudicada com sucesso!</b></p>
            <p>Foi criada automaticamente uma conta de utilizador no portal SupremeXpansion.</p>
            <p><b>Email:</b> $email_cliente<br>
            <b>Password:</b> $senha</p>
            <a href='http://localhost:3000/Supremexpansion/paginas/login.php'
               style='display:inline-block; background:#a30101; color:white; padding:10px 15px; 
                      border-radius:8px; text-decoration:none; font-weight:bold;'>
                Entrar na conta
            </a>
            <p style='font-size:13px; color:#555;'>Recomendamos que altere a sua password após o primeiro login.</p>
        </div>";
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $mail->ErrorInfo);
    }
}
// === 5️⃣ Concluir
header("Location: propostas.php");
exit;
?>
