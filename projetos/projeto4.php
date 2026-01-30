<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Projeto Moradia";
$capa   = "../img/img9.png";
$pasta  = "../ficheiros_pj/projeto4";

/* topo */
$imagens_topo = ["img1.png","img2.png","img3.png"];
$imagem_grande = "img4.png";

/* pdfs */
$pdf1 = "Drawing2-Model-1.pdf";
$pdf2 = "Drawing9-Model-1.pdf";

/* imagens depois do 1º pdf: img5.png -> img18.png (14 imagens)
   - 4 filas com 3 (12 imagens)
   - 5ª fila com 2 imagens (as últimas 2) */
$imagens_depois = [];
for ($i=5; $i<=18; $i++) $imagens_depois[] = "img{$i}.png";
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
  /* ===== TOPO: 3 imagens ===== */
  .pj4-grid{
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }
  .pj4-grid img{
    width: 100%;
    height: 320px;
    object-fit: cover;
    display: block;
    border-radius: 0;
  }

  /* ===== TOPO: 1 imagem grande ===== */
  .pj4-big{ margin-top: 8px; }
  .pj4-big img{
    width: 100%;
    height: 520px;
    object-fit: cover;
    display: block;
    border-radius: 0;
  }

  /* ===== PDFs ===== */
  .pj4-pdf{ margin-top: 18px; }
  .pj4-pdf iframe{
    width: 100%;
    height: 780px;
    display: block;
  }

  .pj4-after-pdf-space{ height: 36px; }

  /* ===== GRID DEPOIS DO 1º PDF =====
     6 colunas:
     - normais: span 2 => 3 por fila
     - últimas 2: span 3 => 2 por fila (metade/metade) */
  .pj4-grid2{
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
  }

  .pj4-grid2 img{
    grid-column: span 2;     /* 3 por fila */
    width: 100%;
    height: 320px;
    object-fit: cover;
    display: block;
    border-radius: 0;
  }

  /* ✅ últimas 2 (img17 e img18): metade/metade */
  .pj4-grid2 img:nth-last-child(-n+2){
    grid-column: span 3;     /* 2 por fila, sem buraco */
    height: 420px;           /* um bocado maiores */
  }

  /* ===== RESPONSIVO ===== */
  @media (max-width: 900px){
    .pj4-grid{ grid-template-columns: repeat(2, 1fr); }
    .pj4-grid img{ height: 260px; }
    .pj4-big img{ height: 380px; }
    .pj4-pdf iframe{ height: 650px; }

    .pj4-grid2{ grid-template-columns: repeat(2, 1fr); }
    .pj4-grid2 img{
      grid-column: span 1;
      height: 260px;
    }
    .pj4-grid2 img:nth-last-child(-n+2){
      grid-column: span 1;
      height: 320px;
    }
  }

  @media (max-width: 520px){
    .pj4-grid, .pj4-grid2{ grid-template-columns: 1fr; }
    .pj4-grid img, .pj4-grid2 img{
      grid-column: span 1;
      height: 240px;
    }
    .pj4-big img{ height: 300px; }
    .pj4-pdf iframe{ height: 560px; }

    .pj4-grid2 img:nth-last-child(-n+2){
      height: 280px;
    }
  }
</style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <!-- topo: 3 + 1 grande -->
  <section class="pp-section">
    <div class="pj4-grid">
      <?php foreach ($imagens_topo as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>

    <div class="pj4-big">
      <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($imagem_grande) ?>" alt="">
    </div>
  </section>

  <!-- pdf 1 -->
  <section class="pp-section pj4-pdf">
    <div class="pp-pdf-card">
      <iframe src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($pdf1) ?>#view=FitH" frameborder="0"></iframe>
      <div class="pp-pdf-info">
        <p>Drawing2-Model-1</p>
        <a href="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($pdf1) ?>" download>
          <i class="bi bi-download"></i> Download
        </a>
      </div>
    </div>
  </section>

  <div class="pj4-after-pdf-space"></div>

  <!-- imagens depois: img5 -> img18 -->
  <section class="pp-section">
    <div class="pj4-grid2">
      <?php foreach ($imagens_depois as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>

  <!-- pdf 2 -->
  <section class="pp-section pj4-pdf">
    <div class="pp-pdf-card">
      <iframe src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($pdf2) ?>#view=FitH" frameborder="0"></iframe>
      <div class="pp-pdf-info">
        <p>Drawing9-Model-1</p>
        <a href="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($pdf2) ?>" download>
          <i class="bi bi-download"></i> Download
        </a>
      </div>
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
