<?php
include 'protecao.php';
include '../conexao/conexao.php';
include 'cabecalho.php';

$email = $_GET['email'] ?? '';
if (!$email) die("Cliente inválido.");

// === Buscar dados do cliente (a partir de propostas) ===
$sqlCliente = "SELECT nome_cliente, email_cliente, codigo_pais FROM propostas WHERE email_cliente = ? LIMIT 1";
$stmt = $pdo->prepare($sqlCliente);
$stmt->execute([$email]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    die("<p style='color:red; text-align:center;'>Cliente não encontrado.</p>");
}

// === Buscar propostas ===
$sqlProp = "SELECT id, codigo, data_emissao, estado, total_final 
            FROM propostas 
            WHERE NOT EXISTS (
                SELECT 1 FROM propostas r
                WHERE r.id_proposta_origem = propostas.id
            )
            and email_cliente = ? 
            ORDER BY data_emissao DESC";
$stmtProp = $pdo->prepare($sqlProp);
$stmtProp->execute([$email]);
$propostas = $stmtProp->fetchAll(PDO::FETCH_ASSOC);

// === Totais ===
$total = 0;
foreach ($propostas as $p) $total += (float)$p['total_final'];

// helper de data segura
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
  <title>Cliente <?= htmlspecialchars($cliente['nome_cliente']) ?> | SupremeXpansion</title>
  <link rel="stylesheet" href="../css/ver_cliente.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br>
<main class="cliente-container">
  <h1>Cliente: <?= htmlspecialchars($cliente['nome_cliente']) ?></h1>
  <a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>


  <section class="info-box">
    <p><b>Email:</b> <?= htmlspecialchars($cliente['email_cliente']) ?></p>
    <p><b>Moeda:</b> <?= htmlspecialchars($cliente['codigo_pais']) ?></p>
  </section>

  <section class="propostas-box">
      <h2>Propostas Associadas</h2>

      <?php if (empty($propostas)): ?>
        <p>Nenhuma proposta registada para este cliente.</p>

      <?php else: ?>

      <div class="table-scroll no-scrollbar" style="max-height:360px; overflow-y:auto;">
          <table class="styled-table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Data Emissão</th>
                <th>Estado</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($propostas as $p): ?>
              <tr class="row-click" data-href="ver_proposta.php?id=<?= $p['id'] ?>">
                <td><?= htmlspecialchars($p['codigo']) ?></td>
                <td><?= dataSegura($p['data_emissao']) ?></td>
                <td>
                  <span class="badge <?= strtolower($p['estado']) ?>">
                      <?= htmlspecialchars($p['estado']) ?>
                  </span>
                </td>
                <td>€<?= number_format((float)$p['total_final'], 2, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
      </div>

      <?php endif; ?>
  </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.row-click').forEach(row => {
    row.addEventListener('click', () => {
      const url = row.dataset.href;
      if (url) window.location.href = url;
    });
  });
});
</script>
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
