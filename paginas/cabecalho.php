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

$brand = $_SESSION['brand'] ?? 'supremexpansion';  // Pega a marca da sessão, se não existir, define como 'supremexpansion'

$logo = '../img/logo_branco.svg';  // Logo padrão da Supremexpansion
$linkSite = 'https://supremexpansion.com';  // Link padrão

// Verifica se a marca é '3dscan2cad' e altera o logo e link
if ($brand === '3dscan2cad') {
    $logo = '../img/logo_ing.png';  // Altere o caminho para o logo 3DScan
    $linkSite = 'https://3dscan2cad.com';  // Link para o 3DScan2Cad
}

require_once __DIR__ . '/../conexao/conexao.php'; // PRECISA disto aqui para usar $conn

// ===============================
//  LOGIN AUTOMÁTICO POR COOKIE
// ===============================
if (!isset($_SESSION['autenticado']) && isset($_COOKIE['remember_login'])) {
    $token = $_COOKIE['remember_login'];
    $stmt = $conn->prepare("SELECT * FROM utilizadores WHERE token_login = ? AND token_expira > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $_SESSION['autenticado']  = true;
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['nome_user']    = $user['nome'];
        $_SESSION['email']        = $user['email'];
        $_SESSION['nivel_acesso'] = $user['acesso_id'];
        $_SESSION['nome_acesso']  = $user['nome_acesso'] ?? '';
    }
}

$isCliente = (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] == 6);
$current_page = basename($_SERVER['PHP_SELF']);


// =========================================
// 🔥  BLOCO DO PRIMEIRO LOGIN (FUNCIONAL)
// =========================================

if (!empty($_SESSION['autenticado'])) {

    $userId = intval($_SESSION['user_id'] ?? 0);
    $paginaAtual = basename($_SERVER['PHP_SELF']);

    // Páginas que NÃO podem ativar o redirecionamento (evita loop)
    $paginasIgnorar = [
        'completar_perfil.php',
        'guardar_perfil.php',
        'logout.php',
        'verificar_codigo.php',
        'login.php'
    ];

    if ($userId > 0 && !in_array($paginaAtual, $paginasIgnorar)) {

        $sqlPL = "SELECT primeiro_login_feito FROM utilizadores WHERE id = ?";
        $stmtPL = $conn->prepare($sqlPL);
        $stmtPL->bind_param("i", $userId);
        $stmtPL->execute();
        $pl = $stmtPL->get_result()->fetch_assoc();

        if ($pl && intval($pl['primeiro_login_feito']) === 0) {

            // Atualizar para 1
            $updPL = $conn->prepare("UPDATE utilizadores SET primeiro_login_feito = 1 WHERE id = ?");
            $updPL->bind_param("i", $userId);
            $updPL->execute();

            // Redirecionar
            header("Location: completar_perfil.php?primeira_vez=1");
            exit;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/icon.png">
    <link rel="stylesheet" href="../css/cabecalho.css">
    <link rel="stylesheet" href="../rsp4k/cabecalho4k.css">
    <link rel="stylesheet" href="../tablet/cabecalho-tab.css">
    <link rel="stylesheet" href="../mobile/cabecalho-mob.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="../js/cabecalho.js" defer></script>
</head>
<body>
  <header class="main-header">
    <div class="logo">
      <a href="index.php">
        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo">
    </a>
    </div>

    <nav class="navbar">
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

        <?php if (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] < 7): ?>
            <li class="gestao-btn">
                <a href="#" id="abrirGestao">GESTÃO</a>
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['nome_user'])): ?>
            <!-- Mostra o nome e botão de logout -->
            <li class="user-info">
                <span class="user-nome"><?php echo htmlspecialchars($_SESSION['nome_user']); ?></span>
            </li>
            <li class="logout-btn">
                <a href="logout.php">LOGOUT</a>
            </li>
        <?php else: ?>
            <!-- Se não estiver logado -->
            <li class="login-btn">
                <a href="login.php">
                    <?= t('nav.login') ?>
                </a>
            </li>
        <?php endif; ?>
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

<!-- SIDEBAR (menu lateral à esquerda) -->
<div id="overlayGestao" class="overlay"></div>

<div id="sidebarGestao" class="sidebar">
    <div class="sidebar-header">
        <div class="user-box">
        <!-- icon -->
            <i class="fa-solid fa-user-circle user-icon"></i>
            
            <div class="user-info">
                <span class="user-nome"><?php echo htmlspecialchars($_SESSION['nome_user'] ?? 'Utilizador'); ?></span>
                <small class="user-acesso">
                    <li class="user-info">
                        <?php echo htmlspecialchars($_SESSION['nome_acesso']); ?>
                    </li>
                </small>
            </div>
            
        </div>
        <button id="fecharSidebar" title="Fechar"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <ul class="sidebar-menu">

    <ul class="sidebar-menu">

    <?php if ($_SESSION['nivel_acesso'] == 1 || $_SESSION['nivel_acesso'] == 2 || $_SESSION['nivel_acesso'] == 3): ?>

        <li><a href="./propostas.php">Propostas</a></li>
        <li><a href="./clientes.php">Clientes</a></li>
        <li><a href="./funcionarios.php">Funcionários</a></li>
        <li><a href="./projetos.php">Projetos</a></li>
        <li><a href="./dashboard.php">Dashboard</a></li>
        <li><a href="./produtividade.php">Produtividade</a></li>

    <?php elseif ($_SESSION['nivel_acesso'] == 5 ): ?>

        <!-- Funcionário só vê projetos -->
        <li><a href="./funcionarios.php">Funcionários</a></li>
        <li><a href="./projetos.php">Projetos</a></li>

    <?php elseif ($isCliente): ?>
        <li><a href="./propostas.php">Propostas</a></li>
        <li><a href="./projetos.php">Projetos</a></li>
        
    <?php elseif ($_SESSION['nivel_acesso'] == 4 ): ?>

        <!-- Comercial só vê projetos -->
        <li><a href="./clientes.php">Clientes</a></li>
        <li><a href="./funcionarios.php">Funcionários</a></li>
        <li><a href="./projetos.php">Projetos</a></li>

    <?php endif; ?>
    
</ul>


</ul>

</div>


</header>

</body>
</html>