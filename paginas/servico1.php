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
    <title><?= t('servico1.meta_title') ?></title>
    <link rel="icon" type="image/png" href="../img/icon.png">
    <link rel="stylesheet" href="../css/cabecalho.css">
    <link rel="stylesheet" href="../css/servico1.css">
    <link rel="stylesheet" href="../rsp4k/servico14k.css">
    <link rel="stylesheet" href="../tablet/servico1-tab.css">
    <link rel="stylesheet" href="../mobile/servico1-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

    <!-- Topo -->
    <section class="servico-topo">
        <img src="../img/index1.png" alt="Laser Scan e Levantamentos 3D" class="servico-topo-img">
        <div class="servico-topo-texto">
            <strong><h1><?= t('servico1.title') ?></h1></strong>
        </div>
    </section>

    <!-- Secção Laser Scanning -->
    <section class="servico-conteudo">
        <div class="servico-media">
            <video src="../img/3d_laser_scanning.mp4" autoplay muted loop playsinline></video>
        </div>

        <div class="servico-texto">
            <h2><?= t('servico1.serv1.title') ?></h2>
            <p><?= t('servico1.serv1.text') ?></p>
        </div>
    </section>

    <!-- Secção Levantamentos Arquitetónicos -->
    <section class="servico-arquitetonicos">
        <div class="servico-arquitetonicos-texto">
            <h2><?= t('servico1.arquitetonicos.title') ?></h2>
            <p><?= t('servico1.arquitetonicos.text') ?></p>
        </div>

        <div class="servico-arquitetonicos-img">
            <img src="../img/arquitetonicos.png" alt="Levantamentos Arquitetónicos">
        </div>
    </section>

    <!-- Secção Levantamentos de Interiores, Exteriores e Arruamentos -->
    <section class="servico-levantamentos">
        <div class="servico-levantamentos-esquerda">
            <img src="../img/lev_ext.png" alt="Levantamentos de Interiores e Exteriores">
        </div>

        <div class="servico-levantamentos-direita">
            <h2><?= t('servico1.levantamentos.title') ?></h2>
            <p><?= t('servico1.levantamentos.text1') ?><br><?= t('servico1.levantamentos.text2') ?></p>
        </div>
    </section>

    <!-- Secção Criação de As-Builts -->
    <section class="servico-asbuilts">
        <div class="servico-asbuilts-texto">
            <h2><?= t('servico1.asbuilts.title') ?></h2>
            <p><?= t('servico1.asbuilts.text') ?></p>
        </div>

        <div class="servico-asbuilts-imagem">
            <img src="../img/point_cloud.png" alt="Criação de As-Builts">
        </div>
    </section>

    <!-- Secção Monitorização de Edifícios Históricos -->
    <section class="servico-monitorizacao">
        <div class="monitorizacao-imagens">
            <div class="img-grid">
                <!-- Estas 4 entram no mesmo popup e antes das restantes -->
                <img class="gallery-item" src="../img/index1.png"       alt="Monitorização 1">
                <img class="gallery-item" src="../img/laserscan.png"    alt="Monitorização 2">
                <img class="gallery-item" src="../img/Point_Clouds3.png" alt="Monitorização 3">
                <img class="gallery-item" src="../img/Point_Cloud4.png"  alt="Monitorização 4">
            </div>
        </div>

        <div class="monitorizacao-texto">
            <h2><?= t('servico1.asbuilts.title') ?></h2>
            <p><?= t('servico1.asbuilts.text') ?></p>
        </div>
    </section>

    <!-- Popup de Imagem (comum a toda a página) -->
    <div id="imagePopup" class="image-popup" aria-hidden="true">
        <div class="popup-content" role="dialog" aria-modal="true">
            <button class="close-btn" aria-label="Fechar">&times;</button>
            <button class="nav-btn prev" aria-label="Anterior">&#10094;</button>
            <img id="popupImage" src="" alt="Imagem Ampliada">
            <button class="nav-btn next" aria-label="Seguinte">&#10095;</button>
            <div class="popup-caption" id="popupCaption"></div>
        </div>
    </div>

    <!-- Galeria em Grelha (continuação da mesma galeria; mantém a ordem) -->
    <section class="galeria-catalogo" data-gallery="servico1">
        <div class="galeria-grid">
            <!-- Linha 1 -->
            <img class="grid-img gallery-item" src="../img/lev5.png"            alt="Item 5">
            <img class="grid-img gallery-item" src="../img/arquitetonicos.png"  alt="Item 6">
            <img class="grid-img gallery-item" src="../img/lev7.png"            alt="Item 7">
            <img class="grid-img gallery-item" src="../img/index1.png"          alt="Item 8">
            <img class="grid-img gallery-item" src="../img/lev9.png"            alt="Item 9">
            <img class="grid-img gallery-item" src="../img/laserscan.png"       alt="Item 10">

            <!-- Linha 2 -->
            <img class="grid-img gallery-item" src="../img/lev11.png"           alt="Item 11">
            <img class="grid-img gallery-item" src="../img/Point_Clouds3.png"   alt="Item 12">
            <img class="grid-img gallery-item" src="../img/point_cloud.png"     alt="Item 13">
            <img class="grid-img gallery-item" src="../img/Point_Cloud4.png"    alt="Item 14">
            <img class="grid-img gallery-item" src="../img/lev15.png"           alt="Item 15">
            <img class="grid-img gallery-item" src="../img/lev16.png"           alt="Item 16">

            <!-- Linha 3 -->
            <img class="grid-img gallery-item" src="../img/lev17.png"           alt="Item 17">
            <img class="grid-img gallery-item" src="../img/lev18.png"           alt="Item 18">
            <img class="grid-img gallery-item" src="../img/lev19.png"           alt="Item 19">
            <img class="grid-img gallery-item" src="../img/lev20.png"           alt="Item 20">
            <img class="grid-img gallery-item" src="../img/lev21.png"           alt="Item 21">
            <img class="grid-img gallery-item" src="../img/lev22.png"           alt="Item 22">

            <!-- Linha 4 (largas) -->
            <img class="grid-img wide-2 gallery-item" src="../img/lev23.png"    alt="Item 23">
            <img class="grid-img wide-2 gallery-item" src="../img/lev24.png"    alt="Item 24">
            <img class="grid-img wide-2 gallery-item" src="../img/lev25.png"    alt="Item 25">
        </div>
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
    <script src="../js/servico1.js"></script>
</body>
</html>
