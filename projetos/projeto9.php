<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Levantamento Moradia";
$capa   = "../img/img10.png";
$pasta  = "../ficheiros_pj/projeto9";

$galeria = ["img1.png","img2.png","img3.png","img4.png","img5.png"];

// mete aqui o nome real do vídeo dentro da pasta projeto9
$video = "video.mp4";
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title><?= htmlspecialchars($titulo) ?> | Portfólio SupremeXpansion</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/projeto_portfolio_geral.css">

  <style>
    /* 3 na primeira fila + 2 na segunda a preencher */
    .pp-galeria{
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 8px;
    }

    .pp-galeria > img{
      grid-column: span 2;     /* 3 por fila */
      width: 100%;
      height: 340px;
      object-fit: cover;
      display: block;
      border-radius: 0;
    }

    /* últimas 2: metade/metade (preenche a fila toda) */
    .pp-galeria > img:nth-last-child(-n+2){
      grid-column: span 3;     /* 2 por fila */
      height: 420px;
    }

    /* vídeo a preencher */
    .pp-video{
      margin-top: 18px;
    }
    .pp-video video{
      width: 100%;
      height: auto;
      display: block;
    }

    /* responsivo */
    @media (max-width: 900px){
      .pp-galeria{ grid-template-columns: repeat(2, 1fr); }
      .pp-galeria > img{ grid-column: span 1; height: 260px; }
      .pp-galeria > img:nth-last-child(-n+2){ grid-column: span 1; height: 320px; }
    }

    @media (max-width: 520px){
      .pp-galeria{ grid-template-columns: 1fr; }
      .pp-galeria > img{ height: 240px; }
      .pp-galeria > img:nth-last-child(-n+2){ height: 280px; }
    }
  </style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <?php if (!empty($galeria)): ?>
    <section class="pp-section">
      <div class="pp-galeria">
        <?php foreach ($galeria as $img): ?>
          <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($video)): ?>
    <section class="pp-section pp-video">
      <video controls autoplay muted loop playsinline>
        <source src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($video) ?>" type="video/mp4">
      </video>
    </section>
  <?php endif; ?>

  <a href="../paginas/portfolio.php" class="pp-back">
    <i class="bi bi-arrow-left"></i>
    Voltar ao Portfólio
  </a>

</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
