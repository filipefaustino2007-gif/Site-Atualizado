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
    <title><?= t('sobre.meta_title') ?></title>
    <link rel="icon" type="image/png" href="/img/icon.png">
    <link rel="stylesheet" href="/css/cabecalho.css">
    <link rel="stylesheet" href="/css/sobre.css">
    <link rel="stylesheet" href="/rsp4k/sobre4k.css">
    <link rel="stylesheet" href="/tablet/sobre-tab.css">
    <link rel="stylesheet" href="/mobile/sobre-mob.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'cabecalho.php'; ?>

    <!-- Topo -->
    <section class="sobre-topo">
        <img src="../img/index2.png" alt="Sobre SupremeXpansion" class="sobre-topo-img">
        <div class="sobre-topo-texto">
            <h1><?= t('sobre.title') ?></h1>
        </div>
    </section>

    <!-- Secção Sobre a SupremeXpansion -->
    <section class="sobre-intro">
        <div class="sobre-intro-esquerda">
            <img src="../img/icon.png" alt="SupremeXpansion Ícone">
        </div>
        <div class="sobre-intro-direita">
            <h2><?= t('sobre.sobre') ?><strong><?= t('sobre.supreme') ?></strong></h2>
        </div>
    </section>

    <!-- Secção Experiência e Qualidade -->
    <section id="experiencia" class="sobre-experiencia">
        <div class="sobre-experiencia-esquerda">
            <h2><?= t('sobre.experiencia.title') ?></h2>
            <p><?= t('sobre.experiencia.text') ?></p>
        </div>
        <div class="sobre-experiencia-direita">
            <img src="../img/img16.png" alt="Experiência e Qualidade">
        </div>
    </section>

    <!-- Secção Tecnologia e Ferramentas -->
    <section id="tecnologia" class="sobre-tecnologia">
        <div class="sobre-tecnologia-esquerda">
            <img src="../img/drone.png" alt="Tecnologia e Ferramentas">
        </div>
        <div class="sobre-tecnologia-direita">
            <h2><?= t('sobre.tecnologia.title') ?></h2>
            <p><?= t('sobre.tecnologia.text') ?></p>
        </div>
    </section>

    <!-- Secção Preço Acessível -->
    <section id="preco" class="sobre-preco">
        <div class="sobre-preco-esquerda">
            <h2><?= t('sobre.preco.title') ?></h2>
            <p><?= t('sobre.preco.text') ?></p>
        </div>
        <div class="sobre-preco-direita">
            <img src="../img/virtualreality.png" alt="Preço Acessível">
        </div>
    </section>

    <!-- Secção Excelente Suporte ao Cliente -->
    <section id="suporte" class="sobre-suporte">
        <div class="sobre-suporte-esquerda">
            <img src="../img/suportecliente.png" alt="Excelente Suporte ao Cliente">
        </div>
        <div class="sobre-suporte-direita">
            <h2><?= t('sobre.suporte.title') ?></h2>
            <p><?= t('sobre.suporte.text') ?></p>
        </div>
    </section>

    <!-- NOVA SECÇÃO RESUMO FINAL -->
    <section class="sobre-resumo">
        <p><?= t('sobre.resumo.text') ?></p>
    </section>

        <!-- Secção Equipa -->
    <section class="sobre-equipa">
        <h2><?= t('sobre.equipa.title') ?></h2>

        <div class="equipa-container">
            <!-- Primeira fila -->
            <div class="equipa-fila">
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro1') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo1') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro2') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo2') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro3') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo3') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro4') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo4') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro5') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo5') ?></p>
                </div>
            </div>

            <!-- Segunda fila -->
            <div class="equipa-fila">
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro6') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo6') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro7') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo7') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro8') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo8') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro9') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo9') ?></p>
                </div>
                <div>
                    <p class="membro"><?= t('sobre.equipa.membro10') ?></p>
                    <p class="cargo"><?= t('sobre.equipa.cargo10') ?></p>
                </div>
            </div>
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
</body>
</html>
