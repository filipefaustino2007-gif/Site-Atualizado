<?php
include 'protecao.php';
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

// === Filtros ===
$nome  = $_GET['nome']  ?? '';
$ordem = $_GET['ordem'] ?? 'm2';

// Montar WHERE
$where  = ["u.acesso_id < 6"]; // só funcionários
$params = [];

if ($nome !== '') {
  $where[]  = "u.nome LIKE ?";
  $params[] = "%$nome%";
}

// Ordenação
switch ($ordem) {
  case 'projetos':
    $orderBy = "projetos_concluidos DESC, m2_concluidos DESC, u.nome ASC";
    break;
  case 'm2':
  default:
    $orderBy = "m2_concluidos DESC, projetos_concluidos DESC, u.nome ASC";
    break;
}

$sql = "
  SELECT 
    u.id,
    u.nome,
    COALESCE(SUM(ap.metros_quadrados), 0)                                                   AS m2_total,
    COALESCE(SUM(CASE WHEN p.estado = 'Concluído' THEN ap.metros_quadrados END), 0)         AS m2_concluidos,
    COALESCE(COUNT(DISTINCT p.id), 0)                                                       AS projetos_totais,
    COALESCE(COUNT(DISTINCT CASE WHEN p.estado = 'Concluído' THEN p.id END), 0)             AS projetos_concluidos
  FROM utilizadores u
  JOIN projetos_funcionarios pf ON pf.funcionario_id = u.id
  JOIN projetos p                ON p.id = pf.projeto_id
  LEFT JOIN propostas prop       ON prop.id = p.proposta_id
  LEFT JOIN areas_proposta ap    ON ap.id_proposta = prop.id
  " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
  GROUP BY u.id, u.nome
  ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title>Produtividade | SupremeXpansion</title>
  <link rel="stylesheet" href="../css/produtividade.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
  <br><br><br><br>
  <main>
    <h1><i class="bi bi-graph-up-arrow"></i> Produtividade dos Funcionários</h1>

    <form class="filter-bar" method="get">
      <input type="text" name="nome" placeholder="Nome..." value="<?= htmlspecialchars($nome) ?>">
      <select name="ordem">
        <option value="m2" <?= $ordem==='m2'?'selected':'' ?>>Ordenar por m² concluídos</option>
        <option value="projetos" <?= $ordem==='projetos'?'selected':'' ?>>Ordenar por nº de projetos concluídos</option>
      </select>
      <button type="submit">Filtrar</button>
      <button type="button" class="reset" onclick="window.location.href='produtividade.php'">Reiniciar filtros</button>
    </form>

    <?php if (empty($dados)): ?>
      <p style="color:#777;">Ainda não existem dados de produtividade registados.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="styled-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Funcionário</th>
                <th>Projetos Concluídos</th>
                <th>Metros Quadrados (Concluídos)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dados as $i => $f): ?>
                <tr onclick="window.location.href='ver_funcionario.php?id=<?= $f['id'] ?>'">
                  <td><?= $i + 1 ?></td>
                  <td>
                    <?= htmlspecialchars($f['nome']) ?>
                    <?php if ($i == 0): ?><span class="badge badge-top"><i class="bi bi-trophy-fill"></i> Top</span><?php endif; ?>
                  </td>
                  <td><?= (int)$f['projetos_concluidos'] ?></td>
                  <td><?= number_format((float)$f['m2_concluidos'], 2, ',', '.') ?> m²</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
        </table>
      </div>

    <?php endif; ?>
  </main>
<?php include 'rodape.php'; ?>
</body>
</html>
