<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SupremeXpansion - Capturamos a Realidade</title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/png" href="../img/icon.png">
    <link rel="stylesheet" href="../css/cabecalho.css">
    <link rel="stylesheet" href="../css/index.css">
    <script src="../js/index.js" defer></script>
</head>
<body>
    <?php include 'cabecalho.php'; ?>


    <!-- Slideshow -->
    <section class="slideshow-container">

        <!-- Slide 1 -->
        <div class="slide fade active">
            <img src="../img/index1.png" alt="<?= t('home.slides.slide1.title') ?>">
            <div class="text-box">
                <h1><?= t('home.slides.slide1.title') ?></h1>
                <br>
                <p><?= t('home.slides.slide1.text') ?></p>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="slide fade">
            <img src="../img/index2.png" alt="<?= t('home.slides.slide2.title') ?>">
            <div class="text-box">
                <h1><?= t('home.slides.slide2.title') ?></h1>
                <br>
                <p><?= t('home.slides.slide2.text') ?></p>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="slide fade">
            <img src="../img/index3.png" alt="<?= t('home.slides.slide3.title') ?>">
            <div class="text-box">
                <h1><?= t('home.slides.slide3.title') ?></h1>
                <br>
                <p><?= t('home.slides.slide3.text') ?></p>
            </div>
        </div>

        <!-- Botões de navegação -->
        <div class="slide-controls">
            <button class="prev">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="next">
                <i class="bi bi-chevron-right"></i>
            </button>
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

    <!-- Por que escolher trabalhar com a SupremeXpansion -->
    <section class="why-supreme">
        <div class="video-side">
            <video src="../img/scan_to_3d_model.mp4" autoplay muted loop playsinline></video>
        </div>

        <div class="text-side">
            <a class="h1_video">
                <strong><?= t('home.why.title_strong') ?></strong>
                <?= t('home.why.title_rest') ?>
            </a>

            <div class="topics">
                <p>
                    <a href="sobre.php#experiencia" class="subtitulo">
                        <?= t('home.why.experience.title') ?>
                    </a>
                    - <?= t('home.why.experience.text') ?>
                </p>
                <br>
                <p>
                    <a href="sobre.php#tecnologia" class="subtitulo">
                        <?= t('home.why.tech.title') ?>
                    </a>
                    - <?= t('home.why.tech.text') ?>
                </p>
                <br>
                <p>
                    <a href="sobre.php#preco" class="subtitulo">
                        <?= t('home.why.price.title') ?>
                    </a>
                    - <?= t('home.why.price.text') ?>
                </p>
                <br>
                <p>
                    <a href="sobre.php#suporte" class="subtitulo">
                        <?= t('home.why.support.title') ?>
                    </a>
                    - <?= t('home.why.support.text') ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Secção Portfolio -->
    <section class="portfolio-showcase">
        <div class="portfolio-showcase-header">
            <strong><h2><?= t('home.portfolio.title') ?></h2></strong>

            <a href="portfolio.php" class="btn-portfolio-outline">
                <?= t('home.portfolio.view_all') ?>
            </a>
        </div>
        <div class="portfolio-strip-carousel">
            <div class="portfolio-strip-track">

                <?php
                // Buscar os 12 projetos mais recentes concluídos com capa e visibilidade para este site
                $sql = "
                SELECT p.id, p.nome_projeto, i.ficheiro AS capa
                FROM projetos p
                LEFT JOIN projeto_imagens i ON i.projeto_id = p.id AND i.tipo = 'capa'
                WHERE p.estado = 'Concluído'
                    AND i.ficheiro IS NOT NULL
                    AND (p.visibilidade_site IN ('ambos', 'portugal'))  -- mostra apenas os disponíveis para este site
                ORDER BY p.data_termino DESC
                LIMIT 12
                ";
                $stmt = $pdo->query($sql);
                $projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($projetos)):
                ?>
                <p style="color:#fff; text-align:center; width:100%;">Ainda não existem projetos concluídos para mostrar.</p>
                <?php
                else:
                foreach ($projetos as $p):
                ?>
                <div class="portfolio-item" onclick="window.location.href='ver_projeto_portfolio.php?id=<?= $p['id'] ?>'">
                    <img src="../uploads/projetos/<?= $p['id'] ?>/capa/<?= htmlspecialchars($p['capa']) ?>" alt="<?= htmlspecialchars($p['nome_projeto']) ?>">
                    <strong><div class="portfolio-caption"><?= htmlspecialchars($p['nome_projeto']) ?></div></strong>
                </div>
                <?php
                endforeach;
                endif;
                ?>

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