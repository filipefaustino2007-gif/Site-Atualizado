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
    <title><?= t('servico2.meta_title') ?></title>
    <link rel="icon" type="image/png" href="../img/icon.png">

    <!-- CSS -->
    <link rel="stylesheet" href="../css/cabecalho.css">
    <link rel="stylesheet" href="../css/servico2.css">
    <link rel="stylesheet" href="../rsp4k/servico24k.css">
    <link rel="stylesheet" href="../tablet/servico2-tab.css">
    <link rel="stylesheet" href="../mobile/servico2-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

    <!-- TOPO -->
    <section class="servico-topo">
        <img src="../img/index2.png" alt="Vista aérea com modelação 3D" class="servico-topo-img">
        <div class="servico-topo-texto">
            <strong><h1><?= t('servico2.title') ?></h1></strong>
        </div>
    </section>

    <!-- INTRODUÇÃO -->
    <section class="servico-introducao">
        <div class="servico-introducao-texto">
            <h2><?= t('servico2.introducao.title') ?></h2>
            <p><?= t('servico2.introducao.text') ?></p>
        </div>

        <div class="servico-introducao-imagem">
            <img src="../img/modelacao3d.png" alt="Desenho técnico de planta arquitetónica">
        </div>
    </section>

    <!-- PLANTAS, CORTES E ALÇADOS -->
    <section class="servico-plantas">
        <div class="servico-plantas-imagem">
            <img src="../img/desenho_planta.png" alt="Detalhe de fachada arquitetónica">
        </div>

        <div class="servico-plantas-texto">
            <h2><?= t('servico2.plantas.title') ?></h2>
            <p><?= t('servico2.plantas.text') ?></p>
        </div>
    </section>

    <!-- MODELACAO 3D -->
    <section class="servico-modelacao">
        <div class="servico-modelacao-texto">
            <h2><?= t('servico2.modelacao.title') ?></h2>
            <p><?= t('servico2.modelacao.text') ?></p>
        </div>

        <div class="servico-modelacao-grelha">
            <div class="modelacao-grid">
                <img class="gallery-item" src="../img/servico3_1.png" alt="Modelo 3D de complexo urbano">
                <img class="gallery-item" src="../img/servico3_2.png" alt="Modelo 3D de infraestrutura industrial">
                <img class="gallery-item" src="../img/servico3_3.png" alt="Modelo 3D de interior arquitetónico">
                <img class="gallery-item" src="../img/servico3_4.png" alt="Modelo 3D de equipamento técnico">
            </div>
        </div>
    </section>

    <!-- POPUP IMAGENS (COMUM À PÁGINA) -->
    <div id="imagePopup" class="image-popup" aria-hidden="true">
        <div class="popup-content" role="dialog" aria-modal="true">
            <button class="close-btn" aria-label="Fechar">&times;</button>
            <button class="nav-btn prev" aria-label="Anterior">&#10094;</button>
            <img id="popupImage" src="" alt="Imagem Ampliada">
            <button class="nav-btn next" aria-label="Seguinte">&#10095;</button>
            <div class="popup-caption" id="popupCaption"></div>
        </div>
    </div>

        <!-- GALERIA EM GRELHA -->
        <section class="galeria-catalogo" data-gallery="servico3">
        <!-- Topo: 1ª e 2ª linha (6 + 6) -->
        <div class="galeria-grid galeria-grid-top">
            <!-- Linha 1 -->
            <img class="grid-img gallery-item" src="../img/servico3_5.png"  alt="Galeria 1">
            <img class="grid-img gallery-item" src="../img/servico3_6.png"  alt="Galeria 2">
            <img class="grid-img gallery-item" src="../img/servico3_7.png"  alt="Galeria 3">
            <img class="grid-img gallery-item" src="../img/servico3_8.png"  alt="Galeria 4">
            <img class="grid-img gallery-item" src="../img/servico3_9.png"  alt="Galeria 5">
            <img class="grid-img gallery-item" src="../img/servico3_10.png" alt="Galeria 6">

            <!-- Linha 2 -->
            <img class="grid-img gallery-item" src="../img/servico3_11.png" alt="Galeria 7">
            <img class="grid-img gallery-item" src="../img/servico3_12.png" alt="Galeria 8">
            <img class="grid-img gallery-item" src="../img/servico3_13.png" alt="Galeria 9">
            <img class="grid-img gallery-item" src="../img/servico3_14.png" alt="Galeria 10">
            <img class="grid-img gallery-item" src="../img/servico3_15.png" alt="Galeria 11">
            <img class="grid-img gallery-item" src="../img/servico3_16.png" alt="Galeria 12">
        </div>

        <!-- Fundo: 3ª linha (5 imagens maiores) -->
        <div class="galeria-grid galeria-grid-bottom">
            <img class="grid-img gallery-item" src="../img/servico3_17.png" alt="Galeria 13">
            <img class="grid-img gallery-item" src="../img/servico3_18.png" alt="Galeria 14">
            <img class="grid-img gallery-item" src="../img/servico3_19.png" alt="Galeria 15">
            <img class="grid-img gallery-item" src="../img/servico3_20.png" alt="Galeria 16">
            <img class="grid-img gallery-item" src="../img/servico3_21.png" alt="Galeria 17">
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

    <!-- JS -->
    <script src="../js/servico2.js"></script>
</body>
</html>
