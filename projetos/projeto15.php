<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Destilaria da Longra";
$capa   = "../img/img4.png";
$pasta  = "../ficheiros_pj/projeto15";

/* imagens:
   - 1ª fila: 4 imagens
   - 2ª fila: 2 imagens
   - 3ª fila: 2 imagens
*/
$imgs_4 = ["img1.png","img2.png","img3.png","img4.png"];
$imgs_2a = ["img5.png","img6.png"];
$imgs_2b = ["img7.png","img8.png"];

/* vídeo */
$video = "video.mp4";

/* PDFs */
$pdfs = [
  "Perfil_1-2.pdf",
  "Perfil_2-1.pdf",
  "Perfil_3-1.pdf",
  "Perfil_4-1.pdf",
  "planta-piso-0-e-piso-1-1.pdf",
  "planta-piso-2-e-3-1.pdf",
  "Planta-piso-4-e-cobertura-1.pdf"
];

$pdfs_titulo = "Projecto Destilaria da Longra";
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
    /* 4 imagens na 1ª fila */
    .pj15-grid4{
      display:grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
    }
    .pj15-grid4 img{
      width:100%;
      height: 300px;
      object-fit:cover;
      display:block;
      border-radius:0;
    }

    /* 2 imagens por fila (para 2ª e 3ª filas) */
    .pj15-grid2{
      display:grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
      margin-top: 8px;
    }
    .pj15-grid2 img{
      width:100%;
      height: 380px;
      object-fit:cover;
      display:block;
      border-radius:0;
    }

    /* vídeo */
    .pj15-video{
      margin-top: 18px;
    }
    .pj15-video video{
      width:100%;
      height:auto;
      display:block;
    }

    /* título antes dos PDFs + espaçinho */
    .pj15-pdfs-title{
      margin-top: 26px;
      margin-bottom: 12px;
      font-size: 24px;
      font-weight: 900;
    }

    /* PDFs */
    .pj15-pdfs{
      display:grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    .pj15-pdf-card iframe{
      width:100%;
      height: 780px;
      display:block;
    }

    /* responsivo */
    @media (max-width: 900px){
      .pj15-grid4{ grid-template-columns: repeat(2, 1fr); }
      .pj15-grid4 img{ height: 260px; }
      .pj15-grid2{ grid-template-columns: 1fr; }
      .pj15-grid2 img{ height: 320px; }
      .pj15-pdf-card iframe{ height: 650px; }
    }

    @media (max-width: 520px){
      .pj15-grid4{ grid-template-columns: 1fr; }
      .pj15-grid4 img{ height: 240px; }
      .pj15-grid2 img{ height: 280px; }
      .pj15-pdf-card iframe{ height: 560px; }
    }
  </style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <section class="pp-section">
    <div class="pj15-grid4">
      <?php foreach ($imgs_4 as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>

    <div class="pj15-grid2">
      <?php foreach ($imgs_2a as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>

    <div class="pj15-grid2">
      <?php foreach ($imgs_2b as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (!empty($video)): ?>
    <section class="pp-section pj15-video">
      <video controls autoplay muted loop playsinline>
        <source src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($video) ?>" type="video/mp4">
      </video>
    </section>
  <?php endif; ?>

  <!-- título antes dos PDFs -->
  <div class="pj15-pdfs-title"><?= htmlspecialchars($pdfs_titulo) ?></div>

  <?php if (!empty($pdfs)): ?>
    <section class="pp-section pj15-pdfs">
      <?php foreach ($pdfs as $pdf): ?>
        <?php $pdfUrl = htmlspecialchars($pasta) . '/' . htmlspecialchars($pdf); ?>
        <div class="pj15-pdf-card pp-pdf-card">
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
