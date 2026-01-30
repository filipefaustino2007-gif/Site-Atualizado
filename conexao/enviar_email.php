<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // PHPMailer


function enviarEmailProposta($email, $nome, $ficheiro_pdf, $codigo_proposta) {

    // Carregar configuração
    $config = require __DIR__ . '/../paginas/config_email.php';


    $mail = new PHPMailer(true);

    try {
        // CONFIGURAÇÃO SMTP
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure']; 
        $mail->Port       = $config['smtp_port'];

        // REMETENTE
        $mail->setFrom($config['from_email'], $config['from_name']);

        // DESTINATÁRIO
        $mail->addAddress($email, $nome);

        // ANEXO
        if (file_exists($ficheiro_pdf)) {
            $mail->addAttachment($ficheiro_pdf);
        }

        // CONTEÚDO
        $mail->isHTML(true);
        $mail->Subject = "Nova Proposta – $codigo_proposta";

        $mail->Body = "
            <div style='font-family:Poppins, sans-serif;'>
                <h2 style='color:#a30101;'>SupremeXpansion</h2>

                <p>Olá <b>$nome</b>,</p>

                <p>Segue em anexo a sua proposta referente ao projeto solicitado.</p>

                <p>Caso pretenda avançar ou tenha qualquer dúvida, estamos sempre disponíveis.</p>

                <br><br>
                <p>Com os melhores cumprimentos,<br>
                <b>SupremeXpansion</b></p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/email_error.log', "Erro email: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        return false;
    }
}
