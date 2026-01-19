<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/i18n.php';
include_once __DIR__ . '/language_utils.php';

// Carregar línguas disponíveis e língua atual
$flags = getLanguageFlags();
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
<html lang="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/cabecalho.css">
  <link rel="stylesheet" href="/rsp4k/cabecalho4k.css">
  <link rel="stylesheet" href="/tablet/cabecalho-tab.css">
  <link rel="stylesheet" href="/mobile/cabecalho-mob.css">
  <script src="/js/cabecalho.js"></script> 
</head>

<body>
  <header class="main-header">
    <div class="logo">
      <a href="index.php">
        <img src="../img/logo_branco.svg" alt="SupremeXpansion">
      </a>
    </div>

    <nav class="navbar">

      <div class="menu-logo">
    <a href="index.php">
      <img src="../img/logo_branco.svg" alt="SupremeXpansion">
    </a>
  </div>
   
      <ul>

        <li>
          <a href="index.php">
            <?= t('nav.home') ?>
          </a>
        </li>

        <li>
          <a href="sobre.php">
            <?= t('nav.about') ?>
          </a>
        </li>

        <!-- SERVIÇOS -->
         <li class="servicos-dropdown">
          <div class="dropdown-head">
            <a href="servico.php">
              <?= t('nav.services') ?>
            </a>

            <button type="button" class="dropdown-toggle" aria-label="Abrir serviços">
              <i class="bi bi-caret-right-fill"></i>
            </button>
          </div>

          <div class="servicos-menu">
            <b>
              <a href="servico1.php"><?= t('nav.laser_scan') ?></a>
              <a href="servico2.php"><?= t('nav.design_3d') ?></a>
              <a href="servico3.php"><?= t('nav.uav_drone') ?></a>
            </b>
          </div>
        </li>

        <!-- PORTFOLIO -->
         <li class="portfolio-dropdown">
          <div class="dropdown-head">
            <a href="portfolio.php">
              <?= t('nav.portfolio') ?>
            </a>

            <button type="button" class="dropdown-toggle" aria-label="Abrir portfolio">
              <i class="bi bi-caret-right-fill"></i>
            </button>
          </div>

          <div class="portfolio-menu">
            <b>
              <strong>
                <a href="portfolio_res.php">
                  <?= t('nav.residential') ?>
                </a>
              </strong>
              <strong>
                <a href="portfolio_urb.php">
                  <?= t('nav.urban') ?>
                </a>
              </strong>
              <strong>
                <a href="portfolio_com.php">
                  <?= t('nav.commercial') ?>
                </a>
              </strong>
              <strong>
                <a href="portfolio_ind.php">
                  <?= t('nav.industrial') ?>
                </a>
              </strong>
            </b>
          </div>
        </li>

        
        <li>
          <a href="contactos.php">
            <?= t('nav.contacts') ?>
          </a>
        </li>

        <li class="login-btn">
          <a href="login.php">
            <?= t('nav.login') ?>
          </a>
        </li>

        <!-- SELETOR DE IDIOMA -->
        <li class="language-selector">
          <form action="set_language.php" method="post" class="language-form">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($currentLang, ENT_QUOTES, 'UTF-8') ?>">

            <button
              type="button"
              class="language-toggle"
              aria-haspopup="listbox"
              aria-expanded="false"
              aria-controls="language-menu"
            >
              <img
                src="<?= htmlspecialchars($flags[$currentLang] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($availableLanguages[$currentLang], ENT_QUOTES, 'UTF-8') ?>"
                class="lang-flag"
              >

              <span class="language-label">
                <?= htmlspecialchars($availableLanguages[$currentLang], ENT_QUOTES, 'UTF-8') ?>
              </span>

              <i class="bi bi-caret-right-fill"></i>
            </button>

            <ul
              class="language-menu"
              id="language-menu"
              role="listbox"
              aria-label="Selecionar idioma"
            >
              <?php foreach ($languageOptions as $code => $label): ?>
                <li>
                  <button
                    type="button"
                    class="language-option"
                    data-lang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                    role="option"
                  >
                    <img
                      src="<?= htmlspecialchars($flags[$code] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                      class="lang-flag"
                    >
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                  </button>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        </li>


      </ul>
    </nav>
    <button class="menu-toggle" aria-label="Abrir menu">
    <span></span>
    <span></span>
    <span></span>
    </button>
  </header>
</body>
</html>
