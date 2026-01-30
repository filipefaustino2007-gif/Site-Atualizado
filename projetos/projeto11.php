<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Remodelação de Prédio";
$capa   = "../img/img6.png";
$pasta  = "../ficheiros_pj/projeto11";

/* 1) 3 imagens topo */
$top3 = ["img1.png","img2.png","img3.png"];

/* 2) Noturnas (1 grande) */
$noturna_big = "img4.png";

/* 3) Apartamento - T1 (2 imgs) */
$t1 = ["img5.png","img6.png"];

/* 4) Apartamento - T3 (12 imgs) */
$t3_a = [];
for ($i=7; $i<=18; $i++) $t3_a[] = "img{$i}.png";

/* 5) Apartamento - T3 (12 imgs) segunda série */
$t3_b = [];
for ($i=19; $i<=30; $i++) $t3_b[] = "img{$i}.png";

/* 6) Última grande */
$ultima_big = "img31.png";
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
    /* grids */
    .p11-grid-3{
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
    }
    .p11-grid-3 img{
      width:100%;
      height: 320px;
      object-fit: cover;
      display:block;
      border-radius:0;
    }

    .p11-big{
      margin-top: 8px;
    }
    .p11-big img{
      width:100%;
      height: 520px;
      object-fit: cover;
      display:block;
      border-radius:0;
    }

    .p11-grid-2{
      display:grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
    }
    .p11-grid-2 img{
      width:100%;
      height: 360px;
      object-fit: cover;
      display:block;
      border-radius:0;
    }

    .p11-title{
      margin: 18px 0 10px;
      font-size: 18px;
      font-weight: 900;
    }

    /* última grande colada à secção anterior */
    .p11-last{
      margin-top: 8px;
    }

    /* responsivo */
    @media (max-width: 900px){
      .p11-grid-3{ grid-template-columns: repeat(2, 1fr); }
      .p11-grid-3 img{ height: 260px; }
      .p11-big img{ height: 380px; }
      .p11-grid-2{ grid-template-columns: 1fr; }
      .p11-grid-2 img{ height: 300px; }
    }
    @media (max-width: 520px){
      .p11-grid-3{ grid-template-columns: 1fr; }
      .p11-grid-3 img{ height: 240px; }
      .p11-big img{ height: 300px; }
      .p11-grid-2 img{ height: 260px; }
    }
  </style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <!-- 1) 3 imagens topo -->
  <section class="pp-section">
    <div class="p11-grid-3">
      <?php foreach ($top3 as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 2) Noturnas -->
  <section class="pp-section">
    <div class="p11-title">Noturnas</div>
    <div class="p11-big">
      <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($noturna_big) ?>" alt="">
    </div>
  </section>

  <!-- 3) Apartamento - T1 -->
  <section class="pp-section">
    <div class="p11-title">Apartamento - T1</div>
    <div class="p11-grid-2">
      <?php foreach ($t1 as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 4) Apartamento - T3 -->
  <section class="pp-section">
    <div class="p11-title">Apartamento - T3</div>
    <div class="p11-grid-3">
      <?php foreach ($t3_a as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>
  <br>
  <!-- 5) Outra vez 12 imagens (mantive legenda para separar) -->
  <section class="pp-section">
    <div class="p11-grid-3">
      <?php foreach ($t3_b as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>

    <!-- 6) última grande logo em baixo -->
    <div class="p11-big p11-last">
      <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($ultima_big) ?>" alt="">
    </div>
  </section>

  <a href="../paginas/portfolio.php" class="pp-back">
    <i class="bi bi-arrow-left"></i>
    Voltar ao Portfólio
  </a>
</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
