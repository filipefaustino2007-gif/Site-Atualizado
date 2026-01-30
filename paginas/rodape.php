<?php

include_once __DIR__ . '/i18n.php';
include_once __DIR__ . '/language_utils.php';

// Carregar línguas disponíveis e língua atual
$availableLanguages = getAvailableLanguages();          // vem do language_utils.php
$currentLang = resolveLanguageCode($_SESSION['lang'] ?? 'pt');

// Fallbacks de segurança caso algo venha marado
if (!is_array($availableLanguages) || empty($availableLanguages)) {
    $availableLanguages = ['pt' => 'Português'];
}
if (!isset($availableLanguages[$currentLang])) {
    $currentLang = array_key_first($availableLanguages);
}

// Opções para o dropdown (todas menos a atual)
$languageOptions = array_filter(
    $availableLanguages,
    fn($label, $code) => $code !== $currentLang,
    ARRAY_FILTER_USE_BOTH
);

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/rodape.css">
    <link rel="stylesheet" href="../rsp4k/rodape4k.css">
    <link rel="stylesheet" href="../tablet/rodape-tab.css">
    <link rel="stylesheet" href="../mobile/rodape-mob.css">
</head>
<body>

<footer class="main-footer">
    <div class="footer-content">

        <!-- Coluna Esquerda -->
        <div class="footer-left">
            <div class="footer-logo">
                <img src="../img/logo_branco.svg" alt="SupremeXpansion Logo">
            </div>
            <div class="footer-text">
                <p><?= t('rodape.title') ?></p>
                <p><?= t('rodape.text1') ?> | <a href="politica_privacidade.php" target="_blank" rel="noopener noreferrer"><?= t('rodape.text2') ?></a></p>
            </div>
            <div class="footer-socials">
                <a href="https://www.facebook.com/supremexpansion2023/" target="_blank" rel="noopener noreferrer">
                    <img src="../img/facebook.svg" alt="Facebook">
                </a>
                <a href="https://www.instagram.com/supremexpansion/" target="_blank" rel="noopener noreferrer">
                    <img src="../img/instagram.svg" alt="Instagram">
                </a>
                <a href="https://www.linkedin.com/company/supremexpansion" target="_blank" rel="noopener noreferrer">
                    <img src="../img/linkedin.svg" alt="LinkedIn">
                </a>
            </div>
        </div>

        <!-- Coluna Central -->
        <div class="footer-middle">
            <p class="servicos"><?= t('rodape.servicos') ?></p>
            <br>
            <a href="servico1.php"><?= t('rodape.servico1') ?></a>
            <br>
            <a href="servico2.php"><?= t('rodape.servico2') ?></a>
            <br>
            <a href="servico3.php"><?= t('rodape.servico3') ?></a>
            <div class="footer-logo3d">
                <a href="http://3dscan2cad.com/" target="_blank" rel="noopener noreferrer">
                <img src="../img/logo3d.png" alt="3DScan2CAD">
                </a>
            </div>
        </div>

        <!-- Coluna Direita -->
        <div class="footer-right">
            <p class="contactos"><?= t('rodape.contactos') ?></p>
            <br>
            <p><?= t('rodape.telefone') ?></p>
            <p><?= t('rodape.chamada') ?></p>
            <p><?= t('rodape.numPT') ?></p>
            <p><?= t('rodape.numING') ?></p>
            <br>
            <p><?= t('rodape.email') ?></p>
            <p><?= t('rodape.email_text') ?></p>
        </div>

    </div>
</footer>

</body>
</html>
