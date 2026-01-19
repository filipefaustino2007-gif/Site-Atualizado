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
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('politicas.meta_title') ?></title>
    <link rel="icon" type="image/png" href="/img/icon.png">
    <link rel="stylesheet" href="/css/cabecalho.css">
    <link rel="stylesheet" href="/css/politica_privacidade.css">
    <link rel="stylesheet" href="/rsp4k/politica_privacidade4k.css">
    <link rel="stylesheet" href="/tablet/politica_privacidade-tab.css">
    <link rel="stylesheet" href="/mobile/politica_privacidade-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

        <!-- Topo -->
        <section class="sobre-topo">
            <img src="../img/index2.png" alt="Sobre SupremeXpansion" class="sobre-topo-img">
            <div class="sobre-topo-texto">
                <h1><?= t('politicas.title') ?></h1>
            </div>
        </section>

        <section class="privacy-content">
            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts1.title') ?></h2>
                <p><?= t('politicas.texts.texts1.about') ?><a href="https://supremexpansion.com" target="_blank" rel="noopener noreferrer">https://supremexpansion.com</a></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts2.title') ?></h2>
                <p><?= t('politicas.texts.texts2.about1') ?></p>
                <p><?= t('politicas.texts.texts2.about2') ?><a href="https://automattic.com/privacy/" target="_blank" rel="noopener noreferrer">https://automattic.com/privacy/</a>. <?= t('politicas.texts.texts2.about3') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts3.title') ?></h2>
                <p><?= t('politicas.texts.texts3.about') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts4.title') ?></h2>
                <p><?= t('politicas.texts.texts4.about1') ?></p>
                <p><?= t('politicas.texts.texts4.about2') ?></p>
                <p><?= t('politicas.texts.texts4.about3') ?></p>
                <p><?= t('politicas.texts.texts4.about4') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts5.title') ?></h2>
                <p><?= t('politicas.texts.texts5.about1') ?></p>
                <p><?= t('politicas.texts.texts5.about2') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts6.title') ?></h2>
                <p><?= t('politicas.texts.texts6.about') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts7.title') ?></h2>
                <p><?= t('politicas.texts.texts7.about1') ?></p>
                <p><?= t('politicas.texts.texts7.about2') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts8.title') ?></h2>
                <p><?= t('politicas.texts.texts8.about') ?></p>
            </article>

            <article class="privacy-section">
                <h2><?= t('politicas.texts.texts9.title') ?></h2>
                <p><?= t('politicas.texts.texts9.about') ?></p>
            </article>
        </section>
    </main>

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