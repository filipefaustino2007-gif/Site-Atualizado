<?php
// projetoX.php (base) — SEM PDFs

require_once __DIR__ . '/../paginas/cabecalho.php';

// ✅ ALTERA ISTO EM CADA FICHEIRO
$titulo = "Moradia Santarém";
$capa   = "../img/img15.png";               // capa (e fundo topo)
$pasta  = "../ficheiros_pj/projeto1";       // pasta das imagens deste projeto

// ✅ só nomes (sem caminho)
$galeria = [
  "img1.png",
  "img2.png",
  "img3.png",
  "img4.png",
  "img5.png",
  "img6.png",
  "img7.png",
  "img8.png",
  "img9.png",
  "img10.png",
  "img11.png",
  "img12.png",
  "img13.png",
  "img14.png",
  "img15.png",
  "img16.png",
  "img17.png",
  "img18.png",
  "img19.png",
  "img20.png",
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <title><?= htmlspecialchars($titulo) ?> | Portfólio SupremeXpansion</title>
  <link rel="stylesheet" href="../css/projeto_portfolio_geral.css">

  <style>
  /* PROJETO 1 — galeria maior, gap 8px, mais colada às laterais */
  .pp-main{
    max-width: 1400px;     /* deixa mais largo */
    padding: 18px 8px 50px;/* menos margem lateral */
  }

  .pp-galeria{
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;              /* gap pedido */
  }

  .pp-galeria > img{
    grid-column: span 2;   /* 3 por fila */
    width: 100%;
    height: 380px;         /* ↑ maior */
    object-fit: cover;
    border-radius: 0;
    display: block;
  }

  /* últimas 2 maiores */
  .pp-galeria > img:nth-last-child(-n+2){
    grid-column: span 3;   /* 2 na última fila */
    height: 520px;         /* ↑ maior */
  }

  /* responsivo */
  @media (max-width: 900px){
    .pp-main{ padding: 16px 10px 44px; }
    .pp-galeria{ grid-template-columns: repeat(2, 1fr); }
    .pp-galeria > img{ grid-column: span 1; height: 300px; }
    .pp-galeria > img:nth-last-child(-n+2){ grid-column: span 1; height: 360px; }
  }

  @media (max-width: 520px){
    .pp-main{ padding: 14px 10px 40px; }
    .pp-galeria{ grid-template-columns: 1fr; }
    .pp-galeria > img{ height: 260px; }
    .pp-galeria > img:nth-last-child(-n+2){ height: 320px; }
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

  <a href="../paginas/portfolio.php" class="pp-back">
    <i class="bi bi-arrow-left"></i>
    Voltar ao Portfólio
  </a>
</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
