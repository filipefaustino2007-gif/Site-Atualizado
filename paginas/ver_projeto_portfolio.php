<?php
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Projeto inválido.");

// === Buscar dados do projeto ===
$sqlProjeto = "
  SELECT p.id, p.nome_projeto, i.ficheiro AS capa
  FROM projetos p
  LEFT JOIN projeto_imagens i ON i.projeto_id = p.id AND i.tipo = 'capa'
  WHERE p.id = ?
";
$stmt = $pdo->prepare($sqlProjeto);
$stmt->execute([$id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$projeto) die("Projeto não encontrado.");

// === Buscar galeria de imagens ===
$sqlImgs = "SELECT ficheiro FROM projeto_imagens WHERE projeto_id = ? AND tipo = 'galeria'";
$stmt = $pdo->prepare($sqlImgs);
$stmt->execute([$id]);
$galeria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Buscar PDFs (agora da tabela projeto_docs) ===
$sqlDocs = "SELECT ficheiro FROM projeto_docs WHERE projeto_id = ?";
$stmt = $pdo->prepare($sqlDocs);
$stmt->execute([$id]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title><?= htmlspecialchars($projeto['nome_projeto']) ?> | Portfólio SupremeXpansion</title>
  <link rel="stylesheet" href="../css/portofolio.css">
  <link rel="stylesheet" href="../css/ver_projeto_portfolio.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <style>
  /* === Hero / Capa === */
  .hero {
    position: relative;
    width: 100%;
    height: 60vh;
    background: url('../uploads/projetos/<?= $projeto['id'] ?>/capa/<?= htmlspecialchars($projeto['capa']) ?>') center/cover no-repeat;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  </style>
</head>
<body>

  <!-- Hero -->
  <section class="hero">
    <h1><?= htmlspecialchars($projeto['nome_projeto']) ?></h1>
  </section>

  <!-- Conteúdo -->
  <main>

    <?php if (!empty($galeria)): ?>
      <section>
        <h2>Galeria</h2>
        <div class="galeria">
          <?php foreach ($galeria as $img): ?>
            <img src="../uploads/projetos/<?= $projeto['id'] ?>/galeria/<?= htmlspecialchars($img['ficheiro']) ?>" alt="">
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!empty($docs)): ?>
      <section>
        <h2>Documentos</h2>
        <div class="docs-preview">
          <?php 
          foreach ($docs as $d): 
            $ficheiro = htmlspecialchars($d['ficheiro']);
            $path = "../uploads/projetos/{$projeto['id']}/docs/$ficheiro";
            $absPath = __DIR__ . "/../uploads/projetos/{$projeto['id']}/docs/$ficheiro";
          ?>
            <?php if (file_exists($absPath)): ?>
              <div class="pdf-card">
                <iframe src="<?= $path ?>#view=FitH" frameborder="0"></iframe>
                <div class="pdf-info">
                  <p><?= $ficheiro ?></p>
                  <a href="<?= $path ?>" target="_blank"><i class="bi bi-download"></i> Abrir em nova aba</a>
                </div>
              </div>
            <?php else: ?>
              <p style="color:#ff8080;"><i class="bi bi-exclamation-triangle-fill"></i> Ficheiro não encontrado: <?= $ficheiro ?></p>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <br><br>
    <a href="portfolio.php" class="back-btn"><i class="bi bi-arrow-left"></i> Voltar ao Portfólio</a>
  </main>

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
