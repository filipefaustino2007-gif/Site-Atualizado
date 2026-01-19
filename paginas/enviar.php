<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';

$configPath = __DIR__ . '/config_email.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao carregar as configurações de email.',
    ]);
    exit;
}

$config = require $configPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.',
    ]);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$telemovel = trim($_POST['telemovel'] ?? '');
$motivo = trim($_POST['motivo'] ?? '');
$assunto = trim($_POST['assunto'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');

$erros = [];
if ($nome === '') $erros[] = 'O nome é obrigatório.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'Introduza um email válido.';
if ($motivo === '') $erros[] = 'Selecione o motivo do contacto.';
if ($motivo === 'outro' && $assunto === '') $erros[] = 'Indique o assunto do contacto.';
if ($mensagem === '') $erros[] = 'A mensagem não pode estar vazia.';

if ($erros) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $erros),
    ]);
    exit;
}

/* -----------------------------
   NORMALIZAÇÃO DOS MOTIVOS
-------------------------------- */

$motivosValidos = [
    'orcamento' => 'Pedido de orçamento',
    'parceria' => 'Proposta de parceria',
    'suporte'   => 'Suporte ao cliente',
    'outro'     => 'Outro motivo'
];

$motivoFinal = $motivosValidos[$motivo] ?? 'Outro motivo';

/* Assunto final:
   – Se for um dos primeiros três motivos → assunto = motivo completo
   – Se for "outro" → assunto = o que o utilizador escreveu
*/
if ($motivo !== 'outro') {
    $assuntoFinal = $motivoFinal;
} else {
    $assuntoFinal = $assunto !== '' ? $assunto : 'Outro motivo';
}

/* -----------------------------------------
   Escapar tudo para segurança
------------------------------------------ */
$nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
$emailSeguro = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$telemovelSeguro = htmlspecialchars($telemovel ?: 'Não fornecido', ENT_QUOTES, 'UTF-8');
$motivoSeguro = htmlspecialchars($motivoFinal, ENT_QUOTES, 'UTF-8');
$assuntoSeguro = htmlspecialchars($assuntoFinal, ENT_QUOTES, 'UTF-8');
$mensagemSeguro = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->SMTPSecure = $config['smtp_secure'] === 'tls'
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $config['smtp_port'];
    $mail->CharSet = 'UTF-8';
    $mail->SMTPDebug = 0;

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress('f_faustino@supremexpansion.com', 'SupremeXpansion');
    $mail->addReplyTo($email, $nome ?: $email);

    $mail->isHTML(true);
    $mail->Subject = 'Novo contacto via website SupremeXpansion';
    $mail->Body = "
    <div style='font-family:Poppins, sans-serif; background:#111; padding:20px; border-radius:10px; color:#fff;'>
        <h2 style='color:#c40000;'>Novo contacto recebido</h2>
        <p><strong>Nome:</strong> {$nomeSeguro}</p>
        <p><strong>Email:</strong> {$emailSeguro}</p>
        <p><strong>Telemóvel:</strong> {$telemovelSeguro}</p>
        <p><strong>Motivo:</strong> {$motivoSeguro}</p>
        <p><strong>Assunto:</strong> {$assuntoSeguro}</p>
        <p><strong>Mensagem:</strong><br>{$mensagemSeguro}</p>
    </div>";

    $mail->AltBody =
        "Novo contacto recebido.\n".
        "Nome: {$nome}\n".
        "Email: {$email}\n".
        "Telemóvel: {$telemovel}\n".
        "Motivo: {$motivoFinal}\n".
        "Assunto: {$assuntoFinal}\n".
        "Mensagem:\n{$mensagem}";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Agradecemos o contacto. A sua mensagem foi enviada com sucesso.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível enviar o email neste momento. Tente novamente mais tarde.',
        'error' => $mail->ErrorInfo,
    ]);
}
