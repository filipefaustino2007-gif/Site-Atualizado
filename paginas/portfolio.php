<?php
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

// Buscar projetos concluídos com capa
$sql = "
  SELECT p.id, p.nome_projeto, i.ficheiro AS capa
  FROM projetos p
  LEFT JOIN projeto_imagens i ON i.projeto_id = p.id AND i.tipo = 'capa'
  WHERE p.estado = 'Concluído' AND i.ficheiro IS NOT NULL
  ORDER BY p.data_termino DESC
";
$stmt = $pdo->query($sql);
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portfolio - SupremeXpansion</title>
  <link rel="stylesheet" href="../css/portofolio.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
 
  </style>
</head>

<body>

<!-- Imagem principal -->
<section class="portfolio-base">
  <img src="../img/portofolio.png" alt="Imagem de Portfólio">
  <div class="portfolio-text">
    <h1>PORTFOLIO</h1>
    <div class="underline"></div>
  </div>
</section>

<!-- Linha vermelha divisória -->
<div class="portfolio-divider"></div>

<!-- Conteúdo dos projetos -->
<section class="portfolio-content">
    <!-- imagens inseridas manualmente -->

    <!-- PROJETOS À MÃO (BASE 19) -->

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto1.php'">
    <img src="../img/img15.png" alt="Projeto 1">
    <h3>Moradia Santarém</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto2.php'">
    <img src="../img/img12.png" alt="Projeto 2">
    <h3>Fábrica Alimentar</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto3.php'">
    <img src="../img/img13.png" alt="Projeto 3">
    <h3>Confragem Ponte</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto4.php'">
    <img src="../img/img9.png" alt="Projeto 4">
    <h3>Projeto Moradia</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto5.php'">
    <img src="../img/img14.png" alt="Projeto 5">
    <h3>CNEMA</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto6.php'">
    <img src="../img/img11.png" alt="Projeto 6">
    <h3>Levantamento Armazém</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto7.php'">
    <img src="../img/img16.png" alt="Projeto 7">
    <h3>Linhas de Vapor em Cardiff UK</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto8.php'">
    <img src="../img/img17.png" alt="Projeto 8">
    <h3>Mapeamento Drone Londres UK</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto9.php'">
    <img src="../img/img10.png" alt="Projeto 9">
    <h3>Levantamento Moradia</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto10.php'">
    <img src="../img/servico3_11.png" alt="Projeto 10">
    <h3>Projeto North Flinchley UK</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto11.php'">
    <img src="../img/img6.png" alt="Projeto 11">
    <h3>Remodelação de Prédio</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto12.php'">
    <img src="../img/img8.png" alt="Projeto 12">
    <h3>Ruína</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto13.php'">
    <img src="../img/img7.png" alt="Projeto 13">
    <h3>Taberna do Quinzena</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto14.php'">
    <img src="../img/img5.png" alt="Projeto 14">
    <h3>Armazém Cofersan</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto15.php'">
    <img src="../img/img4.png" alt="Projeto 15">
    <h3>Destilaria da Longra</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto16.php'">
    <img src="../img/img3.png" alt="Projeto 16">
    <h3>Estádio Municipal de Coimbra</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto17.php'">
    <img src="../img/img2.png" alt="Projeto 17">
    <h3>Herdade Alentejo</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto18.php'">
    <img src="../img/img18_pj.png" alt="Projeto 18">
    <h3>Palácio Tomar</h3>
  </div>

  <div class="portfolio-card" onclick="window.location.href='../projetos/projeto19.php'">
    <img src="../img/img19_pj.png" alt="Projeto 19">
    <h3>Prédio Lisboa</h3>
  </div>

  <!-- <?php if (empty($projetos)): ?>
    <p style="color:#fff; text-align:center; width:100%;">Ainda não existem projetos concluídos.</p>
  <?php else: ?>
    <?php foreach ($projetos as $p): ?>
      <div class="portfolio-card" onclick="window.location.href='ver_projeto_portfolio.php?id=<?= $p['id'] ?>'">
        <img src="../uploads/projetos/<?= $p['id'] ?>/capa/<?= htmlspecialchars($p['capa']) ?>" alt="<?= htmlspecialchars($p['nome_projeto']) ?>">
        <h3><?= htmlspecialchars($p['nome_projeto']) ?></h3>
      </div>
    <?php endforeach; ?>

  <?php endif; ?> -->
</section>

<?php include 'rodape.php'; ?>
<button id="btnTopoHeader" class="btn-topo-header" type="button" aria-label="Voltar ao topo" style="position: fixed; right: 18px; bottom: 18px; width: 52px; height: 52px; border: none; border-radius: 14px; cursor: pointer; background: #a30101; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 22px rgba(0,0,0,.18); z-index: 9999; opacity: 0; transform: translateY(10px); pointer-events: none; transition: .25s ease;">
  <i class="bi bi-arrow-up" style="font-size: 20px; line-height: 1;"></i>
</button>

<script>
(function(){
  const btn = document.getElementById("btnTopoHeader");
  if (!btn) return;

  // Tenta detetar o header. Se não existir, usa o topo.
  const header = document.querySelector("header") || document.querySelector(".cabecalho") || document.querySelector("#cabecalho");
  const getHeaderBottom = () => {
    if (!header) return 120; // fallback
    const rect = header.getBoundingClientRect();
    // bottom relativo ao documento (scrollY + bottom do rect)
    return window.scrollY + rect.bottom;
  };

  let headerBottomPx = getHeaderBottom();

  // recalcular em resize (porque o header pode mudar altura)
  window.addEventListener("resize", () => {
    headerBottomPx = getHeaderBottom();
  });

  function onScroll(){
    // mostra só quando já passaste o header (com folga)
    const passou = window.scrollY > (headerBottomPx - 30);
    btn.classList.toggle("show", passou);

    // Estilos no botão diretamente (depois de passar o cabeçalho)
    if (passou) {
      btn.style.opacity = '1';
      btn.style.transform = 'translateY(0)';
      btn.style.pointerEvents = 'auto';
    } else {
      btn.style.opacity = '0';
      btn.style.transform = 'translateY(10px)';
      btn.style.pointerEvents = 'none';
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
})();
</script>

</body>
</html>
