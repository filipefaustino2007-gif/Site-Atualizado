<?php
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

$sql = "
  SELECT p.id, p.nome_projeto, i.ficheiro AS capa
  FROM projetos p
  LEFT JOIN projeto_imagens i ON i.projeto_id = p.id AND i.tipo = 'capa'
  WHERE p.estado = 'Concluído'
    AND p.tipo_projeto = 'comerciais'
    AND i.ficheiro IS NOT NULL
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
  <title>Portfolio Comerciais - SupremeXpansion</title>
  <link rel="stylesheet" href="../css/portofolio.css">
</head>
<body>
<?php include 'cabecalho.php'; ?>

<section class="portfolio-base">
  <img src="../img/portofolio.png" alt="Imagem de Portfólio">
  <div class="portfolio-text">
    <h1>PORTFOLIO COMERCIAIS</h1>
  </div>
</section>

<div class="portfolio-divider"></div>

<section class="portfolio-content">
  <?php if (empty($projetos)): ?>
    <p style="color:#fff; text-align:center; width:100%;">Ainda não existem projetos comerciais concluídos.</p>
  <?php else: ?>
    <?php foreach ($projetos as $p): ?>
      <div class="portfolio-card" onclick="window.location.href='ver_projeto_portfolio.php?id=<?= $p['id'] ?>'">
        <img src="../uploads/projetos/<?= $p['id'] ?>/capa/<?= htmlspecialchars($p['capa']) ?>" alt="<?= htmlspecialchars($p['nome_projeto']) ?>">
        <h3><?= htmlspecialchars($p['nome_projeto']) ?></h3>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php include 'rodape.php'; ?>
</body>
</html>
