<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // PHPMailer

function enviarEmail($destino, $assunto, $mensagemHTML) {
    $config = include '../paginas/config_email.php';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($destino);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $mensagemHTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
