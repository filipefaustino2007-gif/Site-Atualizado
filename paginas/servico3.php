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
    <title><?= t('servico3.meta_title') ?></title>
    <link rel="icon" type="image/png" href="../img/icon.png">
    <link rel="stylesheet" href="../css/cabecalho.css">
    <link rel="stylesheet" href="../css/servico3.css">
    <link rel="stylesheet" href="../rsp4k/servico34k.css">
    <link rel="stylesheet" href="../tablet/servico3-tab.css">
    <link rel="stylesheet" href="../mobile/servico3-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

    <!-- Topo -->
    <section class="servico-topo">
        <img src="../img/index3.png" alt="UAV/Drone Fotogrametria" class="servico-topo-img">
        <div class="servico-topo-texto">
            <strong><h1><?= t('servico3.title') ?></h1></strong>
        </div>
    </section>

    <!-- Secção Fotogrametria -->
    <section class="servico-fotogrametria">
        <div class="servico-fotogrametria-texto">
            <h2><?= t('servico3.fotogrametria.title') ?></h2>
            <p><?= t('servico3.fotogrametria.text') ?></p>
        </div>

        <div class="servico-fotogrametria-video">
            <video src="../img/fotogrametria.mp4" autoplay muted loop playsinline></video>
        </div>
    </section>

    <!-- Secção Principais Técnicas de Fotogrametria -->
    <section class="servico-tecnicas">
        <div class="servico-tecnicas-texto">
            <h2><?= t('servico3.tecnicas.title') ?></h2>
            <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.tecnicas.subtitle1') ?></strong>
            <?= t('servico3.tecnicas.text1') ?>
            </p>

            <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.tecnicas.subtitle2') ?></strong>
            <?= t('servico3.tecnicas.text2') ?>
            </p>

            <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.tecnicas.subtitle3') ?></strong>
            <?= t('servico3.tecnicas.text3') ?>
            </p>


        </div>

        <div class="servico-tecnicas-video">
            <video src="../img/fotogrametria1.mp4" autoplay muted loop playsinline></video>
        </div>
    </section>

        <!-- Secção Vantagens da Fotogrametria -->
    <section class="servico-vantagens">
        <div class="servico-vantagens-imagem">
            <img src="../img/index3.png" alt="Vantagens da Fotogrametria">
        </div>

        <div class="servico-vantagens-texto">
            <h2><?= t('servico3.vantagens.title') ?></h2>

            <p class="bullet-text">
                <span class="bullet">&#8226;</span> <strong><?= t('servico3.vantagens.subtitle1') ?></strong> 
                <?= t('servico3.vantagens.text1') ?>
            </p>

            <p class="bullet-text">
                <span class="bullet">&#8226;</span> <strong><?= t('servico3.vantagens.subtitle2') ?></strong> 
                <?= t('servico3.vantagens.text2') ?>
            </p>

            <p class="bullet-text">
                <span class="bullet">&#8226;</span> <strong><?= t('servico3.vantagens.subtitle3') ?></strong> 
                <?= t('servico3.vantagens.text3') ?>
            </p>

            <p class="bullet-text">
                <span class="bullet">&#8226;</span> <strong><?= t('servico3.vantagens.subtitle4') ?></strong> 
                <?= t('servico3.vantagens.text4') ?>
            </p>

            <p class="bullet-text">
                <span class="bullet">&#8226;</span> <strong><?= t('servico3.vantagens.subtitle5') ?></strong> 
                <?= t('servico3.vantagens.text5') ?>
            </p>
        </div>
    </section>

        <!-- Secção Modelo Digital de Terreno (MDT) -->
            <section class="servico-mdt">

            <div class="servico-mdt-topo">
                <h2><?= t('servico3.mdt.title') ?></h2>
            </div>

            <div class="servico-mdt-conteudo">

                <!-- COLUNA ESQUERDA (3 textos) -->
                <div class="mdt-coluna-texto">

                <div class="mdt-bloco">
                    <h3 class="titulo1"><?= t('servico3.mdt.subtitle1') ?></h3>
                    <p class="texto1"><?= t('servico3.mdt.text1') ?></p>
                </div>

                <div class="mdt-bloco">
                    <h3 class="titulo2"><?= t('servico3.mdt.subtitle2') ?></h3>
                    <p class="texto2"><?= t('servico3.mdt.text2') ?></p>
                </div>

                <div class="mdt-bloco">
                    <h3 class="titulo3"><?= t('servico3.mdt.subtitle3') ?></h3>
                    <p class="texto3"><?= t('servico3.mdt.text3') ?></p>
                </div>

                </div>

                <!-- COLUNA DIREITA (2 imagens) -->
                <div class="mdt-coluna-imagens">

                <div class="mdt-imagem1">
                    <img src="../img/terreno1.png" alt="Modelo Digital de Terreno">
                </div>

                <div class="mdt-imagem2">
                    <img src="../img/terreno2.png" alt="Funcionamento do MDT">
                </div>

                </div>

            </div>
            </section>
            

        <!-- Secção Usos e Aplicações do MDT -->
        <section class="servico-usos">
    <!-- Esquerda: grelha de imagens (isolada) -->
    <div class="usos-grelha-wrapper">
        <div class="usos-grelha">
        <img src="../img/index3.png"   alt="Grelha 1" class="gallery-item">
        <img src="../img/grelha2.png"  alt="Grelha 2" class="gallery-item">
        <img src="../img/grelha3.png"  alt="Grelha 3" class="gallery-item">
        <img src="../img/grelha4.png"  alt="Grelha 4" class="gallery-item">
        <img src="../img/grelha5.png"  alt="Grelha 5" class="gallery-item">
        <img src="../img/grelha6.png"  alt="Grelha 6" class="gallery-item">
        </div>
    </div>

    <!-- Direita: título + texto + bullets (isolado) -->
    <div class="usos-texto-wrapper">
        <div class="usos-texto">
        <h2><?= t('servico3.wrapper.title') ?></h2>
        <p class="usos-intro">
            <?= t('servico3.wrapper.intro') ?>
        </p>

        <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.wrapper.subtitle1') ?></strong>
            <?= t('servico3.wrapper.text1') ?> 
        </p>
        <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.wrapper.subtitle2') ?></strong>
            <?= t('servico3.wrapper.text2') ?> 
        </p>
        <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.wrapper.subtitle3') ?></strong>
            <?= t('servico3.wrapper.text3') ?> 
        </p>
        <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.wrapper.subtitle4') ?></strong>
            <?= t('servico3.wrapper.text4') ?> 
        </p>
        <p class="bullet-text">
            <span class="bullet">&#8226;</span> <strong><?= t('servico3.wrapper.subtitle5') ?></strong>
            <?= t('servico3.wrapper.text5') ?> 
        </p>
        </div>
    </div>
    </section>

    <!-- Secção Nuvens de Pontos -->
    <section class="servico-nuvens">
    <div class="nuvens-topo">
        <h2><?= t('servico3.nuvens.title') ?></h2>
    </div>

    <div class="nuvens-conteudo">
        <!-- Coluna esquerda: textos -->
        <div class="nuvens-textos">
        <div class="nuvens-bloco">
            <h3><?= t('servico3.nuvens.subtitle1') ?></h3>
            <p><?= t('servico3.nuvens.text1') ?>
            </p>
        </div>

        <div class="nuvens-bloco">
            <h3><?= t('servico3.nuvens.subtitle2') ?></h3>
            <p><?= t('servico3.nuvens.text2') ?>
            </p>
        </div>

        <div class="nuvens-bloco1">
            <h3 class="vantagem"><?= t('servico3.nuvens.subtitle3') ?></h3><p>
            <span class="bullet"></span> <strong><?= t('servico3.nuvens.topic1') ?></strong> 
            <?= t('servico3.nuvens.subtext1') ?><br>
            <span class="bullet"></span> <strong><?= t('servico3.nuvens.topic2') ?></strong> 
            <?= t('servico3.nuvens.subtext2') ?><br>
            <span class="bullet"></span> <strong><?= t('servico3.nuvens.topic3') ?></strong> 
            <?= t('servico3.nuvens.subtext3') ?><br>
            <span class="bullet"></span> <strong><?= t('servico3.nuvens.topic4') ?></strong> 
            <?= t('servico3.nuvens.subtext4') ?></p>
            <p class="nuvens-final"><?= t('servico3.nuvens.text3') ?></p>
        </div>
        </div>

        <!-- Coluna direita: imagens -->
        <div class="nuvens-imagens">
        <img src="../img/nuvem1.png" alt="Nuvem de pontos 1">
        <img src="../img/nuvem2.png" alt="Nuvem de pontos 2">
        </div>
    </div>
    </section>

    <!-- Secção Aplicações das Nuvens de Pontos -->
<section class="servico-aplicacoes">
    
    <!-- Imagem à esquerda -->
    <div class="aplicacoes-imagem">
        <img src="../img/nuvem3.png" alt="Aplicações Nuvens de Pontos">
    </div>

    <!-- Texto à direita -->
    <div class="aplicacoes-texto">
        <h2><?= t('servico3.aplicacoes.title') ?></h2>
        <div class="textos">

        <p class="num-text">
            <span class="num">1.</span>
            <strong><?= t('servico3.aplicacoes.subtitle1') ?></strong>  
            <?= t('servico3.aplicacoes.text1') ?>
        </p>

        <p class="num-text">
            <span class="num">2.</span>
            <strong><?= t('servico3.aplicacoes.subtitle2') ?></strong>  
            <?= t('servico3.aplicacoes.text2') ?>
        </p>

        <p class="num-text">
            <span class="num">3.</span>
            <strong><?= t('servico3.aplicacoes.subtitle3') ?></strong>  
            <?= t('servico3.aplicacoes.text3') ?>
        </p>

        <p class="num-text">
            <span class="num">4.</span>
            <strong><?= t('servico3.aplicacoes.subtitle4') ?></strong>  
            <?= t('servico3.aplicacoes.text4') ?>
        </p>

        <p class="num-text">
            <span class="num">5.</span>
            <strong><?= t('servico3.aplicacoes.subtitle5') ?></strong>  
            <?= t('servico3.aplicacoes.text5') ?>
        </p>
        </div>
    </div>
    </section>

    <!-- Secção Galeria Cinzenta com 12 Imagens -->
    <section class="servico-grelha-cinzenta">
        <div class="grelha-cinzenta-grid">
            <img src="../img/grelha7.png"  alt="Grelha 7"  class="gallery-item">
            <img src="../img/grelha8.png"  alt="Grelha 8"  class="gallery-item">
            <img src="../img/grelha9.png"  alt="Grelha 9"  class="gallery-item">
            <img src="../img/grelha10.png" alt="Grelha 10" class="gallery-item">
            <img src="../img/grelha11.png" alt="Grelha 11" class="gallery-item">
            <img src="../img/grelha12.png" alt="Grelha 12" class="gallery-item">

            <img src="../img/grelha13.png" alt="Grelha 13" class="gallery-item">
            <img src="../img/grelha14.png" alt="Grelha 14" class="gallery-item">
            <img src="../img/grelha15.png" alt="Grelha 15" class="gallery-item">
            <img src="../img/grelha16.png" alt="Grelha 16" class="gallery-item">
            <img src="../img/grelha17.png" alt="Grelha 17" class="gallery-item">
            <img src="../img/grelha18.png" alt="Grelha 18" class="gallery-item">
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

    <!-- Popup de Imagem -->
    <div id="imagePopup" class="image-popup" aria-hidden="true">
        <div class="popup-content" role="dialog" aria-modal="true">
            <button class="close-btn" aria-label="Fechar">&times;</button>
            <button class="nav-btn prev" aria-label="Anterior">&#10094;</button>
            <img id="popupImage" src="" alt="Imagem Ampliada">
            <button class="nav-btn next" aria-label="Seguinte">&#10095;</button>
            <div class="popup-caption" id="popupCaption"></div>
        </div>
    </div>

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
    <script src="../js/servico3.js"></script>
</body>
</html>
