<?php
// projetoX.php (base) — SEM PDFs

require_once __DIR__ . '/../paginas/cabecalho.php'; // ajusta se o caminho for outro

// ✅ ALTERA ISTO EM CADA FICHEIRO
$titulo = "Taberna do Quinzena";
$capa   = "../img/img7.png";          // a capa (e também fundo do topo)
$pasta  = "../ficheiros_pj/projeto13";     // pasta local deste ficheiro com imagens da página

/* 5 imagens: 3 na primeira fila + 2 na segunda */
$galeria = ["img1.png","img2.png","img3.png","img4.png","img5.png"];

/* 2 PDFs */
$pdfs = [
  "Taberna-do-Quinzena-Santarem_Corte-A-Model-1.pdf",
  "Taberna-do-Quinzena-Santarem_Alcado-Model-1.pdf"
];
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
    /* 3 + 2 (preenche a 2ª fila) */
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
    /* últimas 2 imagens: metade/metade */
    .pp-galeria > img:nth-last-child(-n+2){
      grid-column: span 3;
      height: 420px;
    }

    /* PDFs */
    .pp-pdfs{
      margin-top: 18px;
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .pp-pdf-card iframe{
      width: 100%;
      height: 780px;
      display: block;
    }

    /* responsivo */
    @media (max-width: 900px){
      .pp-galeria{ grid-template-columns: repeat(2, 1fr); }
      .pp-galeria > img{ grid-column: span 1; height: 260px; }
      .pp-galeria > img:nth-last-child(-n+2){ grid-column: span 1; height: 320px; }
      .pp-pdf-card iframe{ height: 650px; }
    }

    @media (max-width: 520px){
      .pp-galeria{ grid-template-columns: 1fr; }
      .pp-galeria > img{ height: 240px; }
      .pp-galeria > img:nth-last-child(-n+2){ height: 280px; }
      .pp-pdf-card iframe{ height: 560px; }
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

  <?php if (!empty($pdfs)): ?>
    <section class="pp-section pp-pdfs">
      <?php foreach ($pdfs as $pdf): ?>
        <?php $pdfUrl = htmlspecialchars($pasta) . '/' . htmlspecialchars($pdf); ?>
        <div class="pp-pdf-card">
          <iframe src="<?= $pdfUrl ?>#view=FitH" frameborder="0"></iframe>
          <div class="pp-pdf-info">
            <p><?= htmlspecialchars($pdf) ?></p>
            <a href="<?= $pdfUrl ?>" download>
              <i class="bi bi-download"></i> Download
            </a>
          </div>
        </div>
      <?php endforeach; ?>
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