<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Palácio Tomar";
$capa   = "../img/img18_pj.png";
$pasta  = "../ficheiros_pj/projeto18";

/* 4 imagens (2 por fila -> 2 filas) */
$galeria = ["img1.png","img2.png","img3.png","img4.png"];

/* título PDFs */
$pdfsTitulo = "Projecto Palácio Tomar";

/* 5 PDFs */
$pdfs = [
  "Palacio-Alvim_2D_01.pdf",
  "Palacio-Alvim_2D_02.pdf",
  "Palacio-Alvim_2D_03.pdf",
  "Palacio-Alvim_2D_04.pdf",
  "Palacio-Alvim_2D_05.pdf"
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
    /* 2 imagens por fila */
    .pp-galeria{
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
    }
    .pp-galeria > img{
      width: 100%;
      height: 380px;
      object-fit: cover;
      display: block;
      border-radius: 0;
    }

    /* título antes dos PDFs */
    .pp-pdfs-title{
      margin-top: 26px;
      margin-bottom: 12px;
      font-size: 24px;
      font-weight: 900;
    }

    /* PDFs */
    .pp-pdfs{
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .pp-pdf-card iframe{
      width: 100%;
      height: 780px;
      display: block;
    }

    @media (max-width: 900px){
      .pp-galeria > img{ height: 260px; }
      .pp-pdf-card iframe{ height: 650px; }
    }
    @media (max-width: 520px){
      .pp-galeria{ grid-template-columns: 1fr; }
      .pp-galeria > img{ height: 240px; }
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

  <div class="pp-pdfs-title"><?= htmlspecialchars($pdfsTitulo) ?></div>

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
    <i class="bi bi-arrow-left"></i> Voltar ao Portfólio
  </a>

</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
