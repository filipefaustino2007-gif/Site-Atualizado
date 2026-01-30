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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= t('servicos.meta_title') ?></title>
    <link rel="icon" type="image/png" href="../img/icon.png" />
    <link rel="stylesheet" href="../css/cabecalho.css" />
    <link rel="stylesheet" href="../css/servico.css" />
    <link rel="stylesheet" href="../rsp4k/servico4k.css">
    <link rel="stylesheet" href="../tablet/servico-tab.css">
    <link rel="stylesheet" href="../mobile/servico-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

    <!-- Topo Serviços -->
    <section class="servicos-topo">
        <img src="../img/servicos.png" alt="Serviços SupremeXpansion" class="servicos-topo-img" />
        <div class="servicos-topo-texto">
            <h1><?= t('servicos.text') ?></h1>
        </div>
    </section>

    <!-- Secção dos Serviços -->
    <section class="services-gallery">

        <a class="service-card" href="servico1.php">
            <img src="../img/laserscan.png" alt="<?= t('home.services.laser.title') ?>">
            <div class="service-overlay">
                <h2><?= t('home.services.laser.title') ?></h2>
                <p><?= t('home.services.laser.text') ?></p>
            </div>
        </a>

        <a class="service-card" href="servico2.php">
            <img src="../img/index2.png" alt="<?= t('home.services.design.title') ?>">
            <div class="service-overlay">
                <h2><?= t('home.services.design.title') ?></h2>
                <p><?= t('home.services.design.text') ?></p>
            </div>
        </a>

        <a class="service-card" href="servico3.php">
            <img src="../img/index3.png" alt="<?= t('home.services.drone.title') ?>">
            <div class="service-overlay">
                <h2><?= t('home.services.drone.title') ?></h2>
                <p><?= t('home.services.drone.text') ?><br><?= t('home.services.drone.rtext') ?></p>
            </div>
        </a>
    </section>

    <!-- Secção de Contacto -->
    <section class="contact-footer">
        <div class="contact-footer-content">
            <h2 class="contact-footer-title"><?= t('home.contact_section.title') ?></h2>
            <p class="contact-footer-subtitle">
                <?= t('home.contact_section.subtitle') ?>
            </p>
            <a href="contactos.php" class="btn-contactar-outline">
                <?= t('home.contact_section.button') ?>
            </a>
        </div>
    </section>

    <!-- Botão de scroll para cima -->
<button id="btnTopoHeader" class="btn-topo-header" type="button" aria-label="Voltar ao topo" style="position: fixed; right: 18px; bottom: 18px; width: 52px; height: 52px; border: none; border-radius: 14px; cursor: pointer; background: #a30101; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 22px rgba(0,0,0,.18); z-index: 9999; opacity: 0; transform: translateY(10px); pointer-events: none; transition: .25s ease;">
  <i class="bi bi-arrow-up" style="font-size: 20px; line-height: 1;"></i>
</button>

<script>
(function(){
  const btn = document.getElementById("btnTopoHeader");
  if (!btn) return;

  // Tenta detetar o header. Se não existir, usa o topo.
  const header = document.querySelector("header") || document.querySelector(".cabecalho") || document.querySelector("#cabecalho");
  const getHeaderBottom = () => {
    if (!header) return 120; // fallback
    const rect = header.getBoundingClientRect();
    // bottom relativo ao documento (scrollY + bottom do rect)
    return window.scrollY + rect.bottom;
  };

  let headerBottomPx = getHeaderBottom();

  // recalcular em resize (porque o header pode mudar altura)
  window.addEventListener("resize", () => {
    headerBottomPx = getHeaderBottom();
  });

  function onScroll(){
    // mostra só quando já passaste o header (com folga)
    const passou = window.scrollY > (headerBottomPx - 30);
    btn.classList.toggle("show", passou);

    // Estilos no botão diretamente (depois de passar o cabeçalho)
    if (passou) {
      btn.style.opacity = '1';
      btn.style.transform = 'translateY(0)';
      btn.style.pointerEvents = 'auto';
    } else {
      btn.style.opacity = '0';
      btn.style.transform = 'translateY(10px)';
      btn.style.pointerEvents = 'none';
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
})();
</script>

    <?php include 'rodape.php'; ?>
</body>
</html>
