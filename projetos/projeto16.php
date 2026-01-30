<?php
require_once __DIR__ . '/../paginas/cabecalho.php';

$titulo = "Estádio Municipal de Coimbra";
$capa   = "../img/img3.png";
$pasta  = "../ficheiros_pj/projeto16";

$galeria = [
  "img1.png","img2.png","img3.png","img4.png",
  "img5.png","img6.png","img7.png","img8.png"
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

    @media (max-width: 520px){
      .pp-galeria{ grid-template-columns: 1fr; }
      .pp-galeria > img{ height: 240px; }
    }
  </style>
</head>
<body>

<section class="pp-hero" style="background-image:url('<?= htmlspecialchars($capa) ?>')">
  <h1><?= htmlspecialchars($titulo) ?></h1>
</section>

<main class="pp-main">

  <section class="pp-section">
    <div class="pp-galeria">
      <?php foreach ($galeria as $img): ?>
        <img src="<?= htmlspecialchars($pasta) ?>/<?= htmlspecialchars($img) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </section>

  <a href="../paginas/portfolio.php" class="pp-back">
    <i class="bi bi-arrow-left"></i> Voltar ao Portfólio
  </a>
</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
