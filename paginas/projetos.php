<?php
include 'protecao.php';
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';


function primeiroEUltimoNome($nome) {
  $nome = trim((string)$nome);
  if ($nome === '') return '—';

  // normaliza espaços
  $parts = preg_split('/\s+/', $nome);
  $parts = array_values(array_filter($parts, fn($p) => trim($p) !== ''));

  if (count($parts) <= 1) return $parts[0] ?? '—';
  return $parts[0] . ' ' . $parts[count($parts)-1];
}



$isCliente = (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] == 6);
$userEmail = $_SESSION['email'] ?? null;


$nome = $_GET['nome'] ?? '';
$estado = $_GET['estado'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$entrega = $_GET['entrega'] ?? '';   // '' | 'entregues' | 'nao_entregues'
$pagamento = $_GET['pagamento'] ?? ''; // '' | 'pagos' | 'nao_pagos'


$where = [];
$params = [];

// 🔍 Filtro por nome do projeto, obra ou cliente
if ($nome !== '') {
  $where[] = "(
    pr.nome_cliente LIKE ?
    OR pr.nome_obra LIKE ?
    OR pr.numero_projeto LIKE ?
    OR pr.codigo_base LIKE ?
  )";
  $params = array_merge($params, ["%$nome%", "%$nome%", "%$nome%", "%$nome%"]);
}


// 🔍 Filtro por estado
if ($estado !== '') {
  $where[] = "p.estado = ?";
  $params[] = $estado;
}

// ✅ Filtro por data exata de início (sem >=, apenas igual)
if ($data_inicio !== '') {
  $where[] = "DATE(p.data_inicio) = ?";
  $params[] = $data_inicio;
}
// 🔍 Filtro Entrega
if ($entrega !== '') {
    if ($entrega === 'entregues') {
        $where[] = "COALESCE(p.entregue, 0) = 1";
    } elseif ($entrega === 'nao_entregues') {
        $where[] = "COALESCE(p.entregue, 0) = 0";
    }
}

// 🔍 Filtro Pagamento
if ($pagamento !== '') {
    if ($pagamento === 'pagos') {
        $where[] = "COALESCE(p.pago, 0) = 1";
    } elseif ($pagamento === 'nao_pagos') {
        $where[] = "COALESCE(p.pago, 0) = 0";
    }
}

// 🔒 Verificar se é cliente
$isCliente = (isset($_SESSION['nivel_acesso']) && $_SESSION['nivel_acesso'] == 6);
$userEmail = $_SESSION['email'] ?? null;

// CLIENTE SÓ VÊ PROJETOS ASSOCIADOS AO SEU EMAIL
if ($isCliente && $userEmail) {
    $where[] = "pr.email_cliente = ?";
    $params[] = $userEmail;
}

$isFuncionario = ($_SESSION['nivel_acesso'] ?? 0) == 5;
$isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;

$userId = (int)($_SESSION['user_id'] ?? 0);

// tenta ir buscar nome da sessão (se existir)
$userNome = $_SESSION['nome'] ?? null;

// se não existir na sessão, vai ao BD
if ((!$userNome || trim($userNome) === '') && $userId > 0) {
  $st = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ? LIMIT 1");
  $st->execute([$userId]);
  $userNome = $st->fetchColumn() ?: null;
}

// fallback final
if (!$userNome || trim($userNome) === '') {
  $userNome = $_SESSION['email'] ?? 'Utilizador';
}

// agora sim: reduz para 1º + último nome
$userNome = primeiroEUltimoNome($userNome);


if ($isFuncionario || $isComercial) {
    $where[] = "p.id IN (
        SELECT projeto_id 
        FROM projetos_funcionarios 
        WHERE funcionario_id = ?
    )";
    $params[] = $userId;
}


$sql = "
  SELECT
    p.id,
    p.estado,
    p.data_inicio,
    p.data_termino,
    p.entregue,
    p.pago,
    p.comentarios,
    p.comentarios_ultimo_editor_id,
    p.comentarios_ultima_edicao,
    p.comissao_comercial,

    pr.data_emissao,
    pr.nome_cliente,
    pr.email_cliente,
    pr.codigo_pais,
    pr.total_liquido,
    pr.numero_projeto,
    pr.codigo_base,
    pr.nome_obra,
    pr.sub_custos_empresa,
    pr.custos_extra,
    pr.preco_deslocacao,
    pr.total_iva,
    pr.total_final,
    pr.margem_liberta,
    pr.pagamento_inicial_pago,
    pr.pagamento_inicial_valor,
    pr.empresa_nome,
    pr.empresa_nif,

    u.nome AS comentarios_editor_nome,

    COALESCE(sv.servicos, '') AS servicos,

    NULL AS empresa_faturar_nome,
    NULL AS empresa_faturar_nif

  FROM projetos p
  LEFT JOIN propostas pr ON pr.id = p.proposta_id
  LEFT JOIN utilizadores u ON u.id = p.comentarios_ultimo_editor_id
  LEFT JOIN (
    SELECT
      sp.id_proposta,
      GROUP_CONCAT(
        DISTINCT
        CONCAT(
          sp.nome_servico,
          IF(sp.opcao_escolhida IS NULL OR sp.opcao_escolhida = '', '', CONCAT(' (', sp.opcao_escolhida, ')'))
        )
        ORDER BY sp.nome_servico
        SEPARATOR '\n'
      ) AS servicos
    FROM servicos_proposta sp
    GROUP BY sp.id_proposta
  ) sv ON sv.id_proposta = pr.id
  " . (count($where) ? "WHERE " . implode(" AND ", $where) : "") . "
  ORDER BY COALESCE(p.data_inicio, pr.data_emissao) DESC, p.id DESC
";




$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projetos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Projetos | SupremeXpansion</title>
<link rel="stylesheet" href="../css/projetos.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br>
<main class="projetos-container">
  <h1>Gestão de Projetos</h1>

  <!-- 🔍 Filtros -->
  <form class="filter-bar" method="get">
    <input type="text" name="nome" placeholder="Nome do projeto / cliente..." value="<?= htmlspecialchars($nome) ?>">
    <select name="estado">
      <option value="">Todos os estados</option>
      <?php
      $estados = ['Em processamento', 'Em produção', 'Concluído', 'Cancelado'];
      foreach ($estados as $e) {
        $sel = ($estado === $e) ? 'selected' : '';
        echo "<option value='".htmlspecialchars($e)."' $sel>".htmlspecialchars($e)."</option>";
      }
      ?>
    </select>
    <select name="entrega">
      <option value="" <?= $entrega==='' ? 'selected' : '' ?>>Todas as Entregas</option>
      <option value="entregues" <?= $entrega==='entregues' ? 'selected' : '' ?>>Entregues</option>
      <option value="nao_entregues" <?= $entrega==='nao_entregues' ? 'selected' : '' ?>>Não Entregues</option>
    </select>

    <select name="pagamento">
      <option value="" <?= $pagamento==='' ? 'selected' : '' ?>>Todos os Pagamentos</option>
      <option value="pagos" <?= $pagamento==='pagos' ? 'selected' : '' ?>>Pagos</option>
      <option value="nao_pagos" <?= $pagamento==='nao_pagos' ? 'selected' : '' ?>>Não Pagos</option>
    </select>

    <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
    <button type="submit">Filtrar</button>
    <button type="button" class="reset" onclick="window.location.href='projetos.php'">Reiniciar Filtros</button>
  </form>
  <div id="fxBox" style="margin:12px 0; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa;">
    <div style="font-weight:700; margin-bottom:8px;">Conversões (editável)</div>

    <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
      <div style="display:flex; gap:8px; align-items:center;">
        <span style="min-width:70px;">1 USD =</span>
        <input id="fx_USD" type="number" step="0.000001" min="0" value="0.85"
              style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
        <span>EUR</span>
      </div>

      <div style="display:flex; gap:8px; align-items:center;">
        <span style="min-width:70px;">1 GBP =</span>
        <input id="fx_GBP" type="number" step="0.000001" min="0" value="1.15"
              style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
        <span>EUR</span>
      </div>

      <div style="display:flex; gap:8px; align-items:center;">
        <span style="min-width:70px;">1 JPY =</span>
        <input id="fx_JPY" type="number" step="0.000001" min="0" value="0.0055"
              style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
        <span>EUR</span>
      </div>
    </div>

    <div style="font-size:12px; color:#666; margin-top:8px;">
      * EUR é a base (1 EUR = 1 EUR). Ajusta os valores se precisares.
    </div>
  </div>
  <?php if (!$isCliente): ?>
    <div id="saveBar" style="display:none; margin: 10px 0; gap:10px; align-items:center;">
      <button id="btnSaveComments" type="button" class="btn-confirmar-estado" style="background:#a30101; color:#fff; border:none; padding:10px 14px; border-radius:10px; font-weight:800; cursor:pointer;">
        <i class="bi bi-save2-fill"></i> Guardar comentários
      </button>
      <span id="saveHint" style="font-weight:700; color:#444;">Há alterações por guardar.</span>
    </div>
  <?php endif; ?>

  <!-- Scroll horizontal no topo -->
  <div id="topScrollWrap" class="top-scroll-wrap">
    <div id="topScroll" class="top-scroll">
      <div id="topScrollInner" class="top-scroll-inner"></div>
    </div>
  </div>

  <div class="table-wrapper no-scrollbar">
    <table>
      <thead>
        <tr>
          <th style="width:44px;"></th>
          <th>Data</th>
          <th>Cliente</th>
          <th>Estado</th>
          <th>Total s/ IVA</th>
          <th>Nº Proposta</th>
          <th>Nome Obra</th>
          <th>Serviços</th>
          <th>Tempo (dias)</th>
          <th>Sub Custos</th>
          <th>Custos Extra</th>
          <th>Deslocação</th>
          <th>IVA</th>
          <th>Total Final</th>
          <th>Margem</th>
          <th>Entregue</th>
          <th>Pago</th>
          <th>Comentários</th>
          <th>Comissão</th>
          <th>Empresa</th>
          <th>NIF</th>
        </tr>
      </thead>

      <tbody>
      <?php if (empty($projetos)): ?>
        <tr><td colspan="20">Nenhum projeto encontrado.</td></tr>
      <?php else: ?>
        <?php foreach ($projetos as $p): ?>

          <?php
            $data = !empty($p['data_emissao'])
              ? date('d/m/Y', strtotime($p['data_emissao']))
              : (!empty($p['data_inicio']) ? date('d/m/Y', strtotime($p['data_inicio'])) : '—');

            $totalSemIva = (float)($p['total_liquido'] ?? 0);
            $numProposta = $p['numero_projeto'] ?? ($p['codigo_base'] ?? '—');
            $nomeObra    = $p['nome_obra'] ?? '—';

            $subCustos   = (float)($p['sub_custos_empresa'] ?? 0);
            $custosExtra = (float)($p['custos_extra'] ?? 0);
            $deslocacao  = (float)($p['preco_deslocacao'] ?? 0);
            $iva         = (float)($p['total_iva'] ?? 0);
            $totalFinal  = (float)($p['total_final'] ?? 0);
            $margem      = (float)($p['margem_liberta'] ?? 0);

            $tempoDiasDB = $p['tempo_producao_dias'] ?? null; // se existir na proposta
            $tempoDias = ($tempoDiasDB !== null && $tempoDiasDB !== '')
              ? (int)$tempoDiasDB
              : (int)round($subCustos / 40);

            $servicosTooltip = trim((string)($p['servicos'] ?? ''));
            if ($servicosTooltip === '') $servicosTooltip = "Sem serviços";

            $entregue = !empty($p['entregue']) ? 1 : 0;
            $pago     = !empty($p['pago']) ? 1 : 0;

            $comentarios = trim((string)($p['comentarios'] ?? ''));
            $comissao = $p['comissao_comercial'] ?? null;

            $empresa = $p['empresa_faturar_nome'] ?? null;
            $nif     = $p['empresa_faturar_nif'] ?? null;
          ?>

          
          <tr class="row-main clickable"
            data-projeto-id="<?= (int)$p['id'] ?>"
            onclick="window.location.href='ver_projeto.php?id=<?= (int)$p['id'] ?>'">


            <td class="cell-expand">
              <button type="button" class="btn-pin" aria-label="Destacar linha">
                <i class="bi bi-caret-right-fill"></i>
              </button>
            </td>

            <td><?= $data ?></td>
            <td><?= htmlspecialchars($p['nome_cliente'] ?? '—') ?></td>

            <td>
              <span class="badge <?= strtolower(str_replace(' ', '-', $p['estado'] ?? '')) ?>">
                <?= htmlspecialchars($p['estado'] ?? '—') ?>
              </span>
            </td>
            <?php $moeda = strtoupper(trim((string)($p['codigo_pais'] ?? 'EUR'))); ?>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$totalSemIva, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($totalSemIva, 2, ',', '.') ?>
              </span>
            </td>
            <td><strong><?= htmlspecialchars((string)$numProposta) ?></strong></td>
            <td><?= htmlspecialchars((string)$nomeObra) ?></td>

            <td>
              <span class="servicos-pill"
                    data-tooltip="<?= htmlspecialchars($servicosTooltip, ENT_QUOTES, 'UTF-8') ?>">
                Ver
              </span>
            </td>

            <td style="text-align:center;"><strong><?= (int)$tempoDias ?></strong></td>
            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$subCustos, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($subCustos, 2, ',', '.') ?>
              </span>
            </td>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$custosExtra, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($custosExtra, 2, ',', '.') ?>
              </span>
            </td>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$deslocacao, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($deslocacao, 2, ',', '.') ?>
              </span>
            </td>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$iva, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($iva, 2, ',', '.') ?>
              </span>
            </td>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$totalFinal, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($totalFinal, 2, ',', '.') ?>
              </span>
            </td>

            <td>
              <span class="money"
                    data-eur="<?= htmlspecialchars((string)$margem, ENT_QUOTES, 'UTF-8') ?>"
                    data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                <?= number_format($margem, 2, ',', '.') ?>
              </span>
            </td>

            <td><span class="badge <?= $entregue ? 'adjudicada' : 'pendente' ?>"><?= $entregue ? 'Sim' : 'Não' ?></span></td>
            <td><span class="badge <?= $pago ? 'adjudicada' : 'pendente' ?>"><?= $pago ? 'Sim' : 'Não' ?></span></td>

            <?php
              $editorNome = primeiroEUltimoNome($p['comentarios_editor_nome'] ?? '');
              $editorNome = $editorNome !== '' ? $editorNome : '—';

              $editorData = $p['comentarios_ultima_edicao'] ?? null;
              $editorDataFmt = $editorData ? date('d/m/Y H:i', strtotime($editorData)) : '';
            ?>
            <td class="cell-comment">
              <?php if ($isCliente): ?>
                <div class="comment-view"><?= $comentarios !== '' ? htmlspecialchars($comentarios) : '—' ?></div>
              <?php else: ?>
                <div
                  class="comment-edit"
                  contenteditable="true"
                  data-id="<?= (int)$p['id'] ?>"
                  spellcheck="false"
                ><?= htmlspecialchars($comentarios) ?></div>
              <?php endif; ?>

              <div class="comment-meta"
                  data-meta-for="<?= (int)$p['id'] ?>">
                <span class="who"><i class="bi bi-pencil-square"></i> <?= htmlspecialchars($editorNome) ?></span>
                <?php if ($editorDataFmt): ?>
                  <span class="when">• <?= htmlspecialchars($editorDataFmt) ?></span>
                <?php endif; ?>
              </div>
            </td>


            <td>
              <?php if ($comissao !== null && $comissao !== ''): ?>
                <span class="money"
                      data-eur="<?= htmlspecialchars((string)((float)$comissao), ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= number_format((float)$comissao, 2, ',', '.') ?>
                </span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['empresa_nome'] ?? '—') ?></td>
            <td><?= htmlspecialchars($p['empresa_nif'] ?? '—') ?></td>

          </tr>

        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>

    </table>
  </div>
</main>
<?php include 'rodape.php'; ?>
<style>
  .sx-tooltip{
    position: fixed;
    z-index: 999999;
    max-width: 520px;
    background: #111827;
    color: #fff;
    padding: 14px 16px;
    border-radius: 14px;
    box-shadow: 0 18px 36px rgba(0,0,0,.28);
    font-size: 15px;
    font-weight: 800;
    line-height: 1.35;
    text-align: center;
    pointer-events: none;
    opacity: 0;
    transform: translateY(6px);
    transition: .12s ease;
  }
  .sx-tooltip.show{
    opacity: 1;
    transform: translateY(0);
  }
  .sx-tooltip .line{
    padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,.10);
  }
  .sx-tooltip .line:last-child{ border-bottom: none; }
</style>
<script>
(() => {
  'use strict';

  const CURRENCY_META = {
    EUR: { decimals: 2 },
    USD: { decimals: 2 },
    GBP: { decimals: 2 },
    JPY: { decimals: 0 },
  };

  // 1 [moeda] = X EUR (editável)
  const FX = {
    EUR: 1,
    USD: 0.85,
    GBP: 1.15,
    JPY: 0.0055,
  };

  function safeNum(v){
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function formatCurrency(amount, code) {
    const meta = CURRENCY_META[code] || CURRENCY_META.EUR;
    const n = Number(amount || 0);

    try {
      return new Intl.NumberFormat("pt-PT", {
        style: "currency",
        currency: code,
        minimumFractionDigits: meta.decimals,
        maximumFractionDigits: meta.decimals
      }).format(n);
    } catch (e) {
      return n.toFixed(meta.decimals).replace(".", ",") + " " + code;
    }
  }

  // EUR -> moeda (usando taxa 1 [moeda] = X EUR)
  function eurToCurrency(eur, code){
    code = (code || 'EUR').toUpperCase();
    if (code === 'EUR') return eur;
    const rate = FX[code];
    if (!rate || rate <= 0) return 0;
    return eur / rate;
  }

  function ensureSubline(el){
    let sub = el.querySelector(":scope > .money-sub");
    if (!sub) {
      sub = document.createElement("div");
      sub.className = "money-sub";
      sub.style.fontSize = "11px";
      sub.style.color = "#666";
      sub.style.marginTop = "3px";
      sub.style.whiteSpace = "nowrap";
      sub.style.lineHeight = "1.1";
      el.appendChild(sub);
    }
    return sub;
  }

  function renderMoney(el){
    const eur = safeNum(el.dataset.eur);
    const code = (el.dataset.currency || 'EUR').toUpperCase();
    const mainText = formatCurrency(eurToCurrency(eur, code), code);

    el.innerHTML = "";
    const main = document.createElement("div");
    main.className = "money-main";
    main.style.fontWeight = "900";
    main.textContent = mainText;
    el.appendChild(main);

    if (code !== 'EUR') {
      const sub = ensureSubline(el);
      sub.textContent = "≈ " + formatCurrency(eur, "EUR");
    }
  }

  function refreshAll(){
    document.querySelectorAll(".money[data-eur][data-currency]").forEach(renderMoney);
  }

  // inputs
  const inUSD = document.getElementById("fx_USD");
  const inGBP = document.getElementById("fx_GBP");
  const inJPY = document.getElementById("fx_JPY");

  function bindFx(input, code){
    if (!input) return;
    input.addEventListener("input", () => {
      const v = safeNum(input.value);
      if (v > 0) FX[code] = v;
      refreshAll();
    });
    const v0 = safeNum(input.value);
    if (v0 > 0) FX[code] = v0;
  }

  bindFx(inUSD, "USD");
  bindFx(inGBP, "GBP");
  bindFx(inJPY, "JPY");

  refreshAll();
})();

(function(){
  const tip = document.createElement("div");
  tip.className = "sx-tooltip";
  document.body.appendChild(tip);

  function setLines(text){
    const lines = String(text || "").split("\n").map(s => s.trim()).filter(Boolean);
    tip.innerHTML = lines.length
      ? lines.map(l => `<div class="line">${escapeHtml(l)}</div>`).join("")
      : `<div class="line">Sem serviços</div>`;
  }

  function escapeHtml(str){
    return str.replace(/[&<>"']/g, m => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[m]));
  }

  function place(el){
    const r = el.getBoundingClientRect();
    const pad = 12;

    // mostra acima do botão
    const x = r.left + (r.width/2);
    const y = r.top - 12;

    tip.style.left = x + "px";
    tip.style.top  = y + "px";
    tip.style.transform = "translate(-50%, -100%)";

    // garantir que não sai do ecrã
    requestAnimationFrame(() => {
      const tr = tip.getBoundingClientRect();
      let dx = 0;
      if (tr.left < pad) dx = pad - tr.left;
      if (tr.right > window.innerWidth - pad) dx = (window.innerWidth - pad) - tr.right;
      if (dx) {
        tip.style.left = (x + dx) + "px";
      }
    });
  }

  document.querySelectorAll(".servicos-pill[data-tooltip]").forEach(el => {
    el.addEventListener("mouseenter", () => {
      setLines(el.dataset.tooltip);
      place(el);
      tip.classList.add("show");
    });
    el.addEventListener("mousemove", () => place(el));
    el.addEventListener("mouseleave", () => tip.classList.remove("show"));
  });

  window.addEventListener("scroll", () => tip.classList.remove("show"), { passive:true });
})();
</script>
<script>
(() => {
  'use strict';

  const isCliente = <?= $isCliente ? 'true' : 'false' ?>;

  const tableWrap = document.querySelector('.table-wrapper');
  const table = tableWrap?.querySelector('table');
  const topScroll = document.getElementById('topScroll');
  const topInner = document.getElementById('topScrollInner');

  // ===== Top scrollbar sincronizado =====
  function syncTopScrollWidth(){
    if (!table || !topInner) return;
    topInner.style.width = table.scrollWidth + "px";
  }

  if (tableWrap && topScroll) {
    // sync scroll left both ways
    let lock = false;
    topScroll.addEventListener('scroll', () => {
      if (lock) return;
      lock = true;
      tableWrap.scrollLeft = topScroll.scrollLeft;
      lock = false;
    }, { passive: true });

    tableWrap.addEventListener('scroll', () => {
      if (lock) return;
      lock = true;
      topScroll.scrollLeft = tableWrap.scrollLeft;
      lock = false;
    }, { passive: true });

    window.addEventListener('resize', syncTopScrollWidth);
    setTimeout(syncTopScrollWidth, 50);
  }

  (() => {
    'use strict';

    const isCliente = <?= $isCliente ? 'true' : 'false' ?>;

    // ====== Selecionar/destacar linha ao clicar na seta ======
    document.querySelectorAll('.btn-pin').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation(); // IMPORTANTÍSSIMO: não deixa a linha navegar

        const tr = btn.closest('tr.row-main');
        if (!tr) return;

        // opcional: só 1 selecionada de cada vez
        document.querySelectorAll('tr.row-main.is-selected').forEach(x => {
          if (x !== tr) x.classList.remove('is-selected');
        });

        tr.classList.toggle('is-selected');
      });
    });

    // ====== Comentários: não navegar quando clicas/edita ======
    document.querySelectorAll('.comment-edit').forEach(el => {
      el.addEventListener('click', (e) => e.stopPropagation()); // não abre projeto
      el.addEventListener('mousedown', (e) => e.stopPropagation());
    });

    // ====== Guardar comentários (o teu bloco atual pode ficar) ======
    if (isCliente) return;

    const CURRENT_USER_NAME = <?= json_encode(primeiroEUltimoNome($userNome), JSON_UNESCAPED_UNICODE) ?>;


    const saveBar = document.getElementById('saveBar');
    const btnSave = document.getElementById('btnSaveComments');
    const dirty = new Map();

    function showSaveBar(){
      if (!saveBar) return;
      saveBar.style.display = dirty.size ? 'flex' : 'none';
    }

    function setDirty(el, id, text){
      dirty.set(id, text);
      el.classList.add('is-dirty');
      showSaveBar();
    }

    function clearDirty(){
      dirty.clear();
      document.querySelectorAll('.comment-edit.is-dirty').forEach(x => x.classList.remove('is-dirty'));
      showSaveBar();
    }

    document.querySelectorAll('.comment-edit[data-id]').forEach(el => {
      const id = Number(el.dataset.id);
      el.dataset.original = (el.textContent || '').trim();

      // Enter não cria newline
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          el.blur();
        }
      });

      el.addEventListener('blur', () => {
        const v = (el.textContent || '').trim();
        const orig = (el.dataset.original || '').trim();
        if (v !== orig) {
          el.dataset.original = v;
          setDirty(el, id, v);
        }
      });
    });

    btnSave?.addEventListener('click', async () => {
      if (!dirty.size) return;

      btnSave.disabled = true;
      btnSave.style.opacity = '0.7';
      btnSave.style.cursor = 'not-allowed';

      const updates = Array.from(dirty.entries()).map(([id, comentarios]) => ({ id, comentarios }));

      try {
        const res = await fetch('update_projeto_comentarios.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
          body: JSON.stringify({ updates })
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data?.ok) {
          alert(data?.msg || 'Erro ao guardar comentários.');
          return;
        }

        clearDirty();
        // depois do save OK e antes/apos clearDirty()
        updates.forEach(({ id }) => {
          const meta = document.querySelector(`.comment-meta[data-meta-for="${id}"]`);
          if (!meta) return;

          const now = new Date();
          const dd = String(now.getDate()).padStart(2,'0');
          const mm = String(now.getMonth()+1).padStart(2,'0');
          const yy = now.getFullYear();
          const hh = String(now.getHours()).padStart(2,'0');
          const mi = String(now.getMinutes()).padStart(2,'0');

          meta.innerHTML = `<span class="who"><i class="bi bi-pencil-square"></i> ${escapeHtml(CURRENT_USER_NAME)}</span>
                            <span class="when">• ${dd}/${mm}/${yy} ${hh}:${mi}</span>`;
        });

        function escapeHtml(str){
          return String(str || '').replace(/[&<>"']/g, m => ({
            "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
          }[m]));
        }

      } catch (e) {
        alert('Erro ao guardar comentários.');
      } finally {
        btnSave.disabled = false;
        btnSave.style.opacity = '1';
        btnSave.style.cursor = 'pointer';
      }
    });

  })();


  function escapeHtml(str){
    return String(str || '').replace(/[&<>"']/g, m => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[m]));
  }

  document.querySelectorAll('.btn-expand').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const tr = btn.closest('tr');
      if (!tr) return;

      const id = tr.dataset.projetoId;
      const next = tr.nextElementSibling;

      const isOpen = tr.classList.contains('row-expanded');

      // close
      if (isOpen) {
        tr.classList.remove('row-expanded');
        if (next && next.classList.contains('row-detail') && next.dataset.forId === id) {
          next.remove();
        }
        return;
      }

      // open
      tr.classList.add('row-expanded');
      const detail = buildDetailRow(tr);
      tr.insertAdjacentElement('afterend', detail);

      // atualizar largura top scroll (porque layout pode mudar)
      syncTopScrollWidth();
    });
  });

  // ===== Excel-like comentários + botão guardar =====
  if (isCliente) return;

  const saveBar = document.getElementById('saveBar');
  const btnSave = document.getElementById('btnSaveComments');

  const dirty = new Map(); // id -> text

  function showSaveBar(){
    if (!saveBar) return;
    saveBar.style.display = dirty.size ? 'flex' : 'none';
  }

  function setDirty(el, id, text){
    dirty.set(id, text);
    el.classList.add('is-dirty');
    showSaveBar();
  }

  function clearDirty(){
    dirty.clear();
    document.querySelectorAll('.comment-edit.is-dirty').forEach(x => x.classList.remove('is-dirty'));
    showSaveBar();
  }

  document.querySelectorAll('.comment-edit[data-id]').forEach(el => {
    const id = Number(el.dataset.id);
    const original = (el.textContent || '').trim();
    el.dataset.original = original;

    // impedir enter fazer “linha nova” (fica tipo Excel)
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        el.blur();
      }
    });

    // quando sai do foco, marca dirty se mudou
    el.addEventListener('blur', () => {
      const v = (el.textContent || '').trim();
      const orig = (el.dataset.original || '').trim();

      if (v !== orig) {
        el.dataset.original = v;
        setDirty(el, id, v);

        // manter dataset para expand detalhe mostrar o novo
        const tr = el.closest('tr');
        if (tr) tr.dataset.comentarios = v;
      }
    });

    // se clicar na célula, não abrir a linha (não temos onclick no tr, mas por segurança)
    el.addEventListener('click', (e) => e.stopPropagation());
  });

  btnSave?.addEventListener('click', async () => {
    if (!dirty.size) return;

    btnSave.disabled = true;
    btnSave.style.opacity = '0.7';
    btnSave.style.cursor = 'not-allowed';

    const updates = Array.from(dirty.entries()).map(([id, comentarios]) => ({ id, comentarios }));

    try {
      const res = await fetch('update_projeto_comentarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
        body: JSON.stringify({ updates })
      });

      const data = await res.json().catch(() => null);

      if (!res.ok || !data?.ok) {
        alert(data?.msg || 'Erro ao guardar comentários.');
        return;
      }

      clearDirty();
    } catch (e) {
      alert('Erro ao guardar comentários.');
    } finally {
      btnSave.disabled = false;
      btnSave.style.opacity = '1';
      btnSave.style.cursor = 'pointer';
    }
  });

})();
</script>

</body>
</html>
