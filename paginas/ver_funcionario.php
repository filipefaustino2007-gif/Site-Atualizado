<?php
include 'protecao.php';
include '../conexao/conexao.php';
include 'cabecalho.php';

$isFuncionario = (int)($_SESSION['nivel_acesso'] ?? 0) === 5;
$meuId = (int)($_SESSION['user_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);

if ($isFuncionario && $id !== $meuId) {
    // podes redirecionar ou mostrar mensagem
    header("Location: ver_funcionario.php?id=" . $meuId);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Funcionário inválido.");

// — Dados do funcionário
$sql = "
  SELECT 
    u.id, u.nome, u.email, u.telefone, u.morada, u.contribuinte, u.data_registo,
    a.nome_acesso AS cargo
  FROM utilizadores u
  LEFT JOIN acesso a ON a.id = u.acesso_id
  WHERE u.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$f) die("Funcionário não encontrado.");

// — Projetos atribuídos a este funcionário (com soma de m² vindos de areas_proposta)
$stmtProj = $pdo->prepare("
  SELECT 
    p.id,
    p.nome_projeto,
    p.estado AS estado_projeto,
    p.data_inicio,
    p.data_termino,
    pf.area,

    -- m² do projeto (soma das áreas da proposta)
    COALESCE(SUM(ap.metros_quadrados), 0) AS m2_projeto,

    -- nº de membros na mesma área desse projeto
    (
      SELECT COUNT(DISTINCT pf2.funcionario_id)
      FROM projetos_funcionarios pf2
      WHERE pf2.projeto_id = p.id
        AND pf2.area = pf.area
    ) AS membros_area,

    -- m² partilhados pela equipa dessa área
    COALESCE(SUM(ap.metros_quadrados), 0) / NULLIF((
      SELECT COUNT(DISTINCT pf2.funcionario_id)
      FROM projetos_funcionarios pf2
      WHERE pf2.projeto_id = p.id
        AND pf2.area = pf.area
    ), 0) AS metros_quadrados

  FROM projetos_funcionarios pf
  JOIN projetos p ON p.id = pf.projeto_id
  LEFT JOIN propostas prop ON prop.id = p.proposta_id
  LEFT JOIN areas_proposta ap ON ap.id_proposta = prop.id
  WHERE pf.funcionario_id = ?
  GROUP BY p.id, pf.area
  ORDER BY p.data_inicio DESC, p.id DESC
");

$stmtProj->execute([$id]);
$linhas = $stmtProj->fetchAll(PDO::FETCH_ASSOC);


// =========================================
// PRODUTIVIDADE (m² partilhados por área)
// - Só projetos "Concluído"
// - Divide os m² do projeto pelo nº de membros NA MESMA ÁREA do projeto
// - Exclui LEVANTAMENTO (como indica a nota 3D/2D/BIM)
// =========================================

// 1) nº de projetos concluídos (distintos)
$stmtCount = $pdo->prepare("
  SELECT COUNT(DISTINCT p.id)
  FROM projetos_funcionarios pf
  JOIN projetos p ON p.id = pf.projeto_id
  WHERE pf.funcionario_id = ?
    AND p.estado = 'Concluído'
    AND pf.area <> 'LEVANTAMENTO'
");
$stmtCount->execute([$id]);
$totalProjetosConcluidos = (int)($stmtCount->fetchColumn() ?? 0);

// 2) m² concluídos (partilhados por área)
$stmtProd = $pdo->prepare("
  SELECT
    p.id AS projeto_id,
    pf.area,
    COALESCE(SUM(ap.metros_quadrados), 0) AS m2_projeto,
    (
      SELECT COUNT(*)
      FROM projetos_funcionarios pf2
      WHERE pf2.projeto_id = p.id
        AND pf2.area = pf.area
    ) AS membros_area,
    COALESCE(SUM(ap.metros_quadrados), 0) / NULLIF((
      SELECT COUNT(*)
      FROM projetos_funcionarios pf2
      WHERE pf2.projeto_id = p.id
        AND pf2.area = pf.area
    ), 0) AS m2_creditado
  FROM projetos_funcionarios pf
  JOIN projetos p ON p.id = pf.projeto_id
  LEFT JOIN propostas prop ON prop.id = p.proposta_id
  LEFT JOIN areas_proposta ap ON ap.id_proposta = prop.id
  WHERE pf.funcionario_id = ?
    AND p.estado = 'Concluído'
    AND pf.area <> 'LEVANTAMENTO'
  GROUP BY p.id, pf.area
");
$stmtProd->execute([$id]);
$rowsProd = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$totalM2Concluido = 0.0;
foreach ($rowsProd as $r) {
  $totalM2Concluido += (float)($r['m2_creditado'] ?? 0);
}




// — Agregar por projeto (para mostrar várias áreas numa só linha visual, se preferires por-projeto)
$projetosAgr = [];           // [projeto_id] => ['nome'=>..., 'estado'=>..., 'inicio'=>..., 'termino'=>..., 'areas'=>[...]]
$totalProjetosConcluidos = 0;
$totalM2 = 0.0;

foreach ($linhas as $row) {
  $pid = (int)$row['id'];
  if (!isset($projetosAgr[$pid])) {
    $projetosAgr[$pid] = [
      'id' => $pid,
      'nome' => $row['nome_projeto'],
      'estado' => $row['estado_projeto'],
      'inicio' => $row['data_inicio'],
      'termino' => $row['data_termino'],
      'areas' => [] // cada item: ['area'=>'3D','estado'=>'Em produção','m2'=>123]
    ];
  }
  $projetosAgr[$pid]['areas'][] = [
    'area' => $row['area'],
    'estado' => $row['estado_area'] ?? '—',
    'm2' => is_null($row['metros_quadrados']) ? null : (float)$row['metros_quadrados']
  ];

  // produtividade: somar m2 (se existirem) e contar concluídos
  if (!is_null($row['metros_quadrados'])) {
    $totalM2 += (float)$row['metros_quadrados'];
  }
}

// contar concluídos (por projeto)
foreach ($projetosAgr as $p) {
  if ($p['estado'] === 'Concluído') $totalProjetosConcluidos++;
}

// util
function dataSegura($d) {
  if (empty($d)) return '—';
  $t = strtotime($d);
  return $t ? date('d/m/Y', $t) : '—';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title>Funcionário <?= htmlspecialchars($f['nome']) ?> | SupremeXpansion</title>
  <link rel="stylesheet" href="../css/ver_funcionario.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<br><br><br><br>
<main class="container">
  <h1><?= htmlspecialchars($f['nome']) ?></h1>
  <a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>

  <div class="grid">
    <section class="card">
      <div class="info">
        <p><b>Email:</b> <?= htmlspecialchars($f['email']) ?></p>
        <p><b>Telefone:</b> <?= htmlspecialchars($f['telefone'] ?? '—') ?></p>
        <p><b>Morada:</b> <?= htmlspecialchars($f['morada'] ?? '—') ?></p>
        <p><b>NIF:</b> <?= htmlspecialchars($f['contribuinte'] ?? '—') ?></p>
        <p><b>Cargo:</b> <?= htmlspecialchars($f['cargo'] ?? '—') ?></p>
        <p><b>Registado em:</b> <?= dataSegura($f['data_registo']) ?></p>
      </div>
    </section>

    <section class="card">
      <h3 style="margin:0 0 10px;color:#a30101"><i class="bi bi-bar-chart-fill"></i> Produtividade</h3>
      <div class="kpi">
        <div class="item">
          <h4>Projetos Concluídos</h4>
          <p><?= $totalProjetosConcluidos ?></p>
        </div>
        <div class="item">
          <h4>m² (áreas atribuídas)</h4>
          <p><?= number_format($totalM2Concluido, 2, ',', '.') ?> m²</p>
        </div>
      </div>
      <p class="sub" style="margin-top:10px">* Soma dos m² das áreas (3D/2D/BIM) atribuídas a este funcionário.</p>
    </section>
  </div>

  <section>
      <h3 style="color:#a30101;margin:20px 0 8px">
          <i class="bi bi-kanban-fill"></i> Projetos atribuídos
      </h3>

      <?php if (empty($projetosAgr)): ?>
        <p>Este funcionário ainda não está atribuído a projetos.</p>

      <?php else: ?>

      <!-- WRAPPER COM SCROLL INTERNO -->
      <div class="table-scroll no-scrollbar" style="overflow-y:auto;">

        <table class="styled-table">
          <thead>
            <tr>
              <th>Projeto</th>
              <th>Áreas atribuídas</th>
              <th>Estado do Projeto</th>
              <th>Início</th>
              <th>Término</th>
            </tr>
          </thead>

          <tbody>
          <?php foreach ($projetosAgr as $p): ?>
            <tr class="row-click" onclick="window.location='ver_projeto.php?id=<?= $p['id'] ?>'">
              <td><?= htmlspecialchars($p['nome']) ?></td>
              <td>
                <div class="tags">
                  <?php foreach ($p['areas'] as $a): ?>
                    <span class="tag">
                      <?= htmlspecialchars($a['area']) ?>
                      <?= $a['m2'] ? ' · '.number_format($a['m2'],2,',','.') .' m²' : '' ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                  <span class="badge <?= str_replace(' ','-', $p['estado']) ?>">
                      <?= htmlspecialchars($p['estado']) ?>
                  </span>
              </td>
              <td><?= dataSegura($p['inicio']) ?></td>
              <td><?= dataSegura($p['termino']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>

        </table>

      </div> <!-- /table-scroll -->

      <?php endif; ?>
  </section>

</main>
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
