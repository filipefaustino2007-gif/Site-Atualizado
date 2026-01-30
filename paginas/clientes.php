<?php
include 'protecao.php'; 

require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

// === Filtros ===
$nome = $_GET['nome'] ?? '';
$pais = $_GET['pais'] ?? '';
$tipo = $_GET['tipo'] ?? '';

$where = [];
$params = [];

if ($nome !== '') {
  $where[] = "(c.nome LIKE ? OR c.email LIKE ?)";
  $params[] = "%$nome%";
  $params[] = "%$nome%";
}

if ($pais !== '') {
  $where[] = "c.codigo_pais = ?";
  $params[] = $pais;
}

if ($tipo === 'registado') {
  $where[] = "u.id IS NOT NULL";
} elseif ($tipo === 'nao_registado') {
  $where[] = "u.id IS NULL";
}
$isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;


$sql = "
  SELECT
    c.nome AS nome_cliente,
    c.email AS email_cliente,
    c.telefone,
    u.id AS utilizador_id,
    c.registado,

    (
        SELECT SUM(
            CASE 
                WHEN pj.estado = 'Concluído' THEN 0  
                WHEN pr.estado = 'adjudicada' THEN 
                    CASE 
                        WHEN pr.pagamento_inicial_pago = 0 THEN pr.total_final
                        ELSE pr.total_final - pr.pagamento_inicial_valor
                    END
                ELSE 0
            END
        )
        FROM propostas pr
        LEFT JOIN projetos pj ON pj.proposta_id = pr.id
        WHERE pr.email_cliente = c.email
    ) AS em_falta

  FROM clientes c
  LEFT JOIN utilizadores u ON u.email = c.email
  " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
  ORDER BY c.nome ASC
";




$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Clientes | SupremeXpansion</title>
  <link rel="icon" type="image/png" href="../img/icon.png">
  <link rel="stylesheet" href="../css/clientes.css">
  <script src="../js/clientes.js" defer></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br>
<main class="clientes-container">
  <h1>Gestão de Clientes</h1>

  <form class="filter-bar" method="get">
    <input type="text" name="nome" placeholder="Nome ou email..." value="<?= htmlspecialchars($nome) ?>">
    <select name="pais">
      <option value="">Todos os países</option>
      <option value="PT" <?= $pais==='PT'?'selected':'' ?>>Portugal</option>
      <option value="UK" <?= $pais==='UK'?'selected':'' ?>>Inglaterra</option>
    </select>
    <select name="tipo">
      <option value="">Todos</option>
      <option value="registado" <?= $tipo==='registado'?'selected':'' ?>>Registados</option>
      <option value="nao_registado" <?= $tipo==='nao_registado'?'selected':'' ?>>Não registados</option>
    </select>
    <button type="submit">Filtrar</button>
  </form>

  <div class="table-box no-scrollbar">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Email</th>
          <?php if (!$isComercial): ?>

          <th>Em Falta Pagar</th>
          <?php endif; ?>

          <th>Conta no Sistema</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clientes as $c): 
          $href = $c['utilizador_id'] 
            ? 'ver_utilizador.php?id=' . $c['utilizador_id'] 
            : 'ver_cliente.php?email=' . urlencode($c['email_cliente']);
        ?>
        <tr class="row-click" data-href="<?= htmlspecialchars($href) ?>">
          <td><?= htmlspecialchars($c['nome_cliente'] ?? '—') ?></td>
          <td><?= htmlspecialchars($c['email_cliente'] ?? '—') ?></td>
          <?php if (!$isComercial): ?>

          <td>
            <?php 
              $falta = floatval($c['em_falta'] ?? 0);
              echo $falta > 0 
                ? number_format($falta, 2, ',', '.') . " €"
                : "—";
            ?>
          </td>
          <?php endif; ?>

          <td>
            <?php if (!empty($c['utilizador_id'])): ?>
              <span class="status ativo">Ativo</span>
            <?php else: ?>
              <span class="status inativo">Não registado</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
