<?php
include 'protecao.php';
include '../conexao/conexao.php';
include 'cabecalho.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die("Utilizador inválido.");

// — Dados do utilizador (cliente)
$sqlUser = "
  SELECT u.id, u.nome, u.email, u.telefone, u.morada, u.contribuinte, u.data_registo, a.nome_acesso
  FROM utilizadores u
  LEFT JOIN acesso a ON a.id = u.acesso_id
  WHERE u.id = ?
";
$stmt = $pdo->prepare($sqlUser);
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) die("Utilizador não encontrado.");

// — Propostas deste cliente (por email)
$sqlProp = "SELECT id, codigo, data_emissao, estado, total_final 
            FROM propostas 
            WHERE NOT EXISTS (
                SELECT 1 FROM propostas r
                WHERE r.id_proposta_origem = propostas.id
            )
            and email_cliente = ?
            ORDER BY data_emissao DESC";
$stmtP = $pdo->prepare($sqlProp);
$stmtP->execute([$u['email']]);
$propostas = $stmtP->fetchAll(PDO::FETCH_ASSOC);
$isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;

// === Calcular faturação real do cliente ===
$sqlFaturacao = "
  SELECT 
    SUM(
      CASE 
        WHEN pj.estado = 'Concluído' THEN pj.valor_total
        WHEN pj.estado != 'Concluído' AND pr.pagamento_inicial_pago = 1 THEN 
            CASE 
                WHEN pr.pagamento_inicial_valor > 0 THEN pr.pagamento_inicial_valor 
                ELSE (pr.total_final * 0.5)
            END
        ELSE 0
      END
    ) AS total_faturado
  FROM projetos pj
  JOIN propostas pr ON pr.id = pj.proposta_id
  WHERE pr.email_cliente = ?
";

// === Total em falta pagar ===
$sqlEmFalta = "
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
    ) AS em_falta
    FROM propostas pr
    LEFT JOIN projetos pj ON pj.proposta_id = pr.id
    WHERE pr.email_cliente = ?
";

$stmtEF = $pdo->prepare($sqlEmFalta);
$stmtEF->execute([$u['email']]);
$emFalta = (float)($stmtEF->fetchColumn() ?? 0);

$stmtF = $pdo->prepare($sqlFaturacao);
$stmtF->execute([$u['email']]);
$totalFaturado = (float)($stmtF->fetchColumn() ?? 0);



// — Total faturado pelas propostas (indicativo)
$total = 0.0;
foreach ($propostas as $p) $total += (float)$p['total_final'];

// — Projetos do cliente (via propostas do email do cliente)
$stmtProj = $pdo->prepare("
  SELECT 
    p.id,
    p.nome_projeto,
    p.estado,
    p.data_inicio,
    p.data_termino,
    p.valor_total
  FROM projetos p
  JOIN propostas prop ON prop.id = p.proposta_id
  WHERE prop.email_cliente = ?
  ORDER BY p.data_inicio DESC, p.id DESC
");
$stmtProj->execute([$u['email']]);
$projetos = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

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
  <title>Utilizador <?= htmlspecialchars($u['nome']) ?> | SupremeXpansion</title>
  <link rel="stylesheet" href="../css/ver_utilizador.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<br><br><br><br><br>
<main class="utilizador-container">
  <h1>Utilizador: <?= htmlspecialchars($u['nome']) ?></h1>
  <a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>

  <section class="info">
    <p><b>Email:</b> <?= htmlspecialchars($u['email']) ?></p>
    <p><b>Telefone:</b> <?= htmlspecialchars($u['telefone'] ?? '—') ?></p>
    <p><b>Morada:</b> <?= htmlspecialchars($u['morada'] ?? '—') ?></p>
    <p><b>NIF:</b> <?= htmlspecialchars($u['contribuinte'] ?? '—') ?></p>
    <p><b>Perfil:</b> <?= htmlspecialchars($u['nome_acesso']) ?></p>
    <p><b>Registado em:</b> <?= dataSegura($u['data_registo']) ?></p>
  </section>

  <?php if (!$isComercial): ?>

  <section class="financeiro">
    <h2>Resumo Financeiro</h2>
    <br>
    <p><b>Total Faturado:</b> 
        <?= number_format($totalFaturado, 2, ',', '.') ?> €
    </p>
    <br>
    <p><b>Em Falta Pagar:</b> 
        <?= $emFalta > 0 
            ? number_format($emFalta, 2, ',', '.') . "€"
            : "—" ?>
    </p>
  </section>

  <?php endif; ?>

  <section class="propostas">
      <h2>Propostas deste Utilizador</h2>

      <?php if (empty($propostas)): ?>
        <p>Sem propostas registadas.</p>

      <?php else: ?>

      <div class="table-scroll no-scrollbar" style="max-height:340px; overflow-y:auto;">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Data</th>
              <th>Estado</th>
              <?php if (!$isComercial): ?>

              <th>Valor</th>
              <?php endif; ?>

            </tr>
          </thead>

          <tbody>
          <?php foreach ($propostas as $p): ?>
            <tr class="row-click" onclick="window.location='ver_proposta.php?id=<?= $p['id'] ?>'">
              <td><?= htmlspecialchars($p['codigo']) ?></td>
              <td><?= dataSegura($p['data_emissao']) ?></td>

              <?php
              $estadoOriginal = strtolower(trim($p['estado']));
              $map = [
                  'pendente'    => 'pendente',
                  'adjudicada'  => 'adjudicada',
                  'adjudicado'  => 'adjudicada',
                  'aceite'      => 'aceite',
                  'cancelado'   => 'cancelada',
                  'cancelada'   => 'cancelada',
              ];
              $estadoClass = $map[$estadoOriginal] ?? 'pendente';
              ?>

              <td><span class="badge <?= $estadoClass ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
              <?php if (!$isComercial): ?>

              <td><?= number_format((float)$p['total_final'], 2, ',', '.') ?> €</td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php endif; ?>
  </section>


  <section class="projetos">
      <h2>Projetos do Cliente</h2>

      <?php if (empty($projetos)): ?>
        <p>Este cliente ainda não tem projetos associados.</p>

      <?php else: ?>

      <div class="table-scroll no-scrollbar" style="max-height:340px; overflow-y:auto;">
        <table class="styled-table">
          <thead>
            <tr>
              <th>Projeto</th>
              <th>Estado</th>
              <th>Início</th>
              <th>Término</th>
              <?php if (!$isComercial): ?>

              <th>Valor Total</th>
              <?php endif; ?>

            </tr>
          </thead>

          <tbody>
          <?php foreach ($projetos as $pr): ?>
            <tr class="row-click" onclick="window.location='ver_projeto.php?id=<?= $pr['id'] ?>'">
              <td><?= htmlspecialchars($pr['nome_projeto']) ?></td>
              <td>
                  <span class="badge <?= str_replace(' ','-',$pr['estado']) ?>">
                      <?= htmlspecialchars($pr['estado']) ?>
                  </span>
              </td>
              <td><?= dataSegura($pr['data_inicio']) ?></td>
              <td><?= dataSegura($pr['data_termino']) ?></td>
              <?php if (!$isComercial): ?>

              <td><?= number_format((float)$pr['valor_total'], 2, ',', '.') ?> €</td>
              <?php endif; ?>

            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

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
