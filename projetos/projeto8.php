<?php
// projetoX.php (base) — SEM PDFs

require_once __DIR__ . '/../paginas/cabecalho.php'; // ajusta se o caminho for outro

// ✅ ALTERA ISTO EM CADA FICHEIRO
$titulo = "Mapeamento Drone Londres UK";
$capa   = "../img/img17.png";          // a capa (e também fundo do topo)
$pasta  = "../ficheiros_pj/projeto8";     // pasta local deste ficheiro com imagens da página

// ✅ IMAGENS DENTRO DA PÁGINA (depois tu preenches)
$galeria = [
  "img1.png",
  "img2.png",
  "img3.png",
  "img4.png",
  "img5.png"
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
      grid-template-columns: repeat(6, 1fr); /* truque para 3 e depois 2 */
      gap: 8px;
    }

    .pp-galeria > img{
      grid-column: span 2;  
      width: 100%;
      height: 340px;
      object-fit: cover;
      display: block;
      border-radius: 0;
    }

    /* últimas 2 imagens: 2 por fila (metade/metade) */
    .pp-galeria > img:nth-last-child(-n+2){
      grid-column: span 3;
      height: 400px;
    }

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

  <a href="../paginas/portfolio.php" class="pp-back">
    <i class="bi bi-arrow-left"></i>
    Voltar ao Portfólio
  </a>
</main>

<?php require_once __DIR__ . '/../paginas/rodape.php'; ?>
</body>
</html>
