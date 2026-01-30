<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Fábrica Alimentar";
$capa   = "../img/img12.png";
$pasta  = "../ficheiros_pj/projeto2";

$imagens = ["img1.png","img2.png","img3.png","img4.png"];
$video   = "pj2.mp4";
$pdfs    = ["Projecto-Vivid-Foods-Atalaia_Geral-Model-1.pdf","Projecto-Vivid-Foods-Atalaia_Geral-ModeL-2.pdf"];
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
    /* menos colado às laterais (mais pequeno) */
    .pp-main{
      max-width: 1300px;
      padding: 18px 22px 60px;  /* ↑ mais padding lateral */
    }

    /* VIDEO mais pequeno */
    .pp-video video{
      width: 100%;
      max-width: 1200px;        /* ↓ */
      height: auto;
      display: block;
      margin: 0 auto;
    }

    /* PDFs mais pequenos */
    .pp-pdfs{
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-top: 20px;
    }

    .pp-pdf-card iframe{
      width: 100%;
      max-width: 1200px;        
      height: 750px;
      display: block;
      margin: 0 auto;        
    }

    .pp-pdf-info{
      max-width: 1200px;
      margin: 0 auto;
    }

    .pp-pdf-info a{
      font-weight: 900;
      text-decoration: none;
    }

    .pp-title{
    margin: 0 0 14px 0;
    font-size: 30px;
    font-weight: 900;
    }
  </style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <?php if (!empty($imagens)): ?>
    <section class="pp-section">
      <div class="pp-galeria">
        <?php foreach ($imagens as $img): ?>
          <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($video)): ?>
    <section class="pp-section">
      <div class="pp-video">
        <!-- autoplay: em browsers só toca automático se estiver muted -->
        <video controls autoplay muted loop playsinline>
          <source src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($video) ?>" type="video/mp4">
        </video>
      </div>
    </section>
  <?php endif; ?>
  <br><br><br><br><br><br>
  <br><br>
  <h2 class="pp-title">Projecto Vivid Foods Atalaia</h2>

  <?php if (!empty($pdfs)): ?>
    <section class="pp-section">
      <div class="pp-pdfs">
        <?php foreach ($pdfs as $pdf): ?>
          <?php $pdfUrl = htmlspecialchars($pasta) . '/' . htmlspecialchars($pdf); ?>
          <div class="pp-pdf-card">
            <iframe src="<?= $pdfUrl ?>#view=FitH" frameborder="0"></iframe>
            <div class="pp-pdf-info">
              <p><?= htmlspecialchars($pdf) ?></p>
              <a href="<?= $pdfUrl ?>" download><i class="bi bi-download"></i> Download</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
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
