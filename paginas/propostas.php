<?php
include 'protecao.php';
$isCliente = ($_SESSION['nivel_acesso'] == 6);
$emailUser = $_SESSION['email'] ?? '';

$isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;
require_once __DIR__ . '/../conexao/conexao.php';
require_once __DIR__ . '/../conexao/funcoes.php';
include 'cabecalho.php';

// === Funções auxiliares ===
function euro($v) { return number_format($v ?? 0, 2, ',', '.') . ' €'; }

// ================== Construção segura da query com filtros ==================

$estado = $_GET['estado'] ?? '';

if ($estado === 'renegociadas') {
    // Mostrar apenas renegociações
    $baseSql = "
      SELECT 
        p.*,
        COALESCE(sv.servicos, '') AS servicos
      FROM propostas p
      LEFT JOIN (
        SELECT 
          sp.id_proposta,
          GROUP_CONCAT(DISTINCT sp.nome_servico ORDER BY sp.nome_servico SEPARATOR ', ') AS servicos
        FROM servicos_proposta sp
        GROUP BY sp.id_proposta
      ) sv ON sv.id_proposta = p.id
      WHERE p.id_proposta_origem IS NOT NULL AND p.estado != 'cancelada'
    ";

} else {
    // Lógica normal (mostrar só propostas finais — sem versões acima)
    $baseSql = "
      SELECT 
        p.*,
        COALESCE(sv.servicos, '') AS servicos
      FROM propostas p
      LEFT JOIN (
        SELECT 
          sp.id_proposta,
          GROUP_CONCAT(DISTINCT sp.nome_servico ORDER BY sp.nome_servico SEPARATOR ', ') AS servicos
        FROM servicos_proposta sp
        GROUP BY sp.id_proposta
      ) sv ON sv.id_proposta = p.id
      WHERE NOT EXISTS (
        SELECT 1 FROM propostas r
        WHERE r.id_proposta_origem = p.id
      )
    ";

}

$where   = [];
$params  = [];

// 🔒 CLIENTE SÓ VÊ AS SUAS PRÓPRIAS PROPOSTAS
if ($isCliente) {
    $where[]  = "email_cliente = ?";
    $params[] = $emailUser;
}


// Campo cliente
$cliente = $_GET['cliente'] ?? '';
if ($cliente !== '') {
    $where[]  = "(nome_cliente LIKE ? OR email_cliente LIKE ?)";
    $params[] = '%' . $cliente . '%';
    $params[] = '%' . $cliente . '%';
}

$codigo  = $_GET['codigo'] ?? '';
if ($codigo !== '') {
    $where[]  = "codigo LIKE ?";
    $params[] = '%' . $codigo . '%';
}

// Outros estados normais
if ($estado !== '' && $estado !== 'renegociadas') {
    $where[]  = "estado = ?";
    $params[] = $estado;
}

$pais    = $_GET['pais'] ?? '';
if ($pais !== '') {
    $where[]  = "codigo_pais = ?";
    $params[] = $pais;
}

// Montagem final
$sql = $baseSql
     . (count($where) ? " AND " . implode(" AND ", $where) : "")
     . " ORDER BY COALESCE(p.data_emissao, p.criado_em) DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Propostas | SupremExpansion</title>
<link rel="stylesheet" href="../css/global.css">
<link rel="stylesheet" href="../css/propostas.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br><br>
<main class="container">
    <h1>
        Propostas
        <?php if (!$isCliente && !$isContabilista): ?>
            <a href="criar_proposta.php" class="button">+ Criar Proposta</a>
        <?php endif; ?>

    </h1>

    <!-- Barra de filtros -->
    <form class="filter-bar" method="GET">
        <input type="text" name="cliente" placeholder="Filtrar por cliente..." value="<?= htmlspecialchars($_GET['cliente'] ?? '') ?>">
        <input type="text" name="codigo" placeholder="Código do projeto..." value="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>">
        <select name="estado">
            <option value="">Todos os estados</option>
            <option value="pendente">Pendente</option>
            <option value="cancelada">Cancelada</option>
            <option value="adjudicada">Adjudicada</option>
            <option value="renegociadas">Renegociadas</option>
            <option value="arquivada">Arquivada</option>
        </select>
        <select name="pais">
            <option value="">Todas as Moedas</option>
            <option value="EUR">Euro</option>
            <option value="GBP">Libra</option>
            <option value="JPY">Iene</option>
            <option value="USD">Dólar</option>
        </select>
        <button type="submit">Filtrar</button>
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

    <!-- Tabela com scroll -->
    <div class="table-wrapper no-scrollbar">
      <div class="table-scroll">
        <table>
            <thead>
              <tr>
                <th>Data</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Total s/ IVA</th>
                <th>Nº Projeto</th>
                <th>Nome Projeto</th>
                <th>Serviços</th>
                <th>Tempo (dias)</th>
                <th>Sub Custos</th>
                <th>Custos Extra</th>
                <th>Deslocação</th>
                <th>IVA</th>
                <th>Total Final</th>
                <th>Margem</th>
                <th>Pagamento Inicial</th>
                <th>Ações</th>
              </tr>
            </thead>

            <tbody>
            <?php if (empty($propostas)): ?>
                <tr><td colspan="16">❌ Nenhuma proposta encontrada.</td></tr>
            <?php else:
                foreach ($propostas as $p):
                    $emissao = !empty($p['data_emissao'])
                        ? date('d/m/Y', strtotime($p['data_emissao']))
                        : (!empty($p['criado_em']) ? date('d/m/Y', strtotime($p['criado_em'])) : '—');
            ?>
            <tr class="clickable-row" data-href="ver_proposta.php?id=<?= (int)$p['id'] ?>">
              <?php
                $emissao = !empty($p['data_emissao'])
                  ? date('d/m/Y', strtotime($p['data_emissao']))
                  : (!empty($p['criado_em']) ? date('d/m/Y', strtotime($p['criado_em'])) : '—');

                $moeda = $p['codigo_pais'] ?? 'EUR';

                // "Total s/ IVA" = total_liquido (já com desconto, mas ainda sem IVA)
                $totalSemIva = (float)($p['total_liquido'] ?? 0);

                $numProjeto = $p['numero_projeto'] ?? ($p['codigo_base'] ?? '—');
                $nomeProjeto = $p['nome_obra'] ?? '—';

                // serviços agregados (GROUP_CONCAT)
                $listaServicos = trim((string)($p['servicos'] ?? ''));
                if ($listaServicos === '') $listaServicos = '—';

                // Campos novos (se não existirem ainda, ficam 0/—)
                $subCustos  = (float)($p['sub_custos_empresa'] ?? 0);
                $custosExtra = (float)($p['custos_extra'] ?? 0);
                $deslocacao  = (float)($p['preco_deslocacao'] ?? 0);
                $iva         = (float)($p['total_iva'] ?? 0);
                $totalFinal  = (float)($p['total_final'] ?? 0);
                $margem      = (float)($p['margem_liberta'] ?? 0);

                // Tempo em dias inteiro:
                // usa o guardado (tempo_producao_dias). Se estiver vazio, calcula por subCustos/40 com arredondamento normal
                $tempoDiasDB = $p['tempo_producao_dias'] ?? null;
                $tempoDias = ($tempoDiasDB !== null && $tempoDiasDB !== '')
                  ? (int)$tempoDiasDB
                  : (int)round($subCustos / 40);
              ?>

              <td><?= $emissao ?></td>

              <td><?= htmlspecialchars($p['nome_cliente']) ?></td>



              <td>
                <span class="badge <?= htmlspecialchars($p['estado'] ?? '') ?>">
                  <?= htmlspecialchars($p['estado'] ?? '—') ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$totalSemIva, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($totalSemIva) ?>
                </span>
              </td>

              <td><strong><?= htmlspecialchars((string)$numProjeto) ?></strong></td>

              <td><?= htmlspecialchars((string)$nomeProjeto) ?></td>

              <?php
              // ... dentro do foreach das propostas ($p)

              // 1) buscar serviços desta proposta (exemplo) — ajusta a query se já tiveres isto pronto
              $stmtS = $pdo->prepare("
                SELECT sp.nome_servico, sp.opcao_escolhida
                FROM servicos_proposta sp
                WHERE sp.id_proposta = ?
                ORDER BY sp.id_proposta ASC
              ");
              $stmtS->execute([$p['id']]);
              $servs = $stmtS->fetchAll(PDO::FETCH_ASSOC);

              // 2) construir texto tooltip
              $lista = [];
              foreach ($servs as $s) {
                $nome = trim($s['nome_servico'] ?? '');
                $opc  = trim($s['opcao_escolhida'] ?? '');
                if ($nome === '') continue;
                $lista[] = $opc ? ($nome . " (" . $opc . ")") : $nome;
              }
              $tooltip = !empty($lista) ? implode("&#10;", $lista) : "Sem serviços registados";

              ?>
              <td>
                <span class="servicos-pill" data-tooltip="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>">
                  Ver
                </span>
              </td>


              <td style="text-align:center;"><strong><?= (int)$tempoDias ?></strong></td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$subCustos, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($subCustos) ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$custosExtra, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($custosExtra) ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$deslocacao, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($deslocacao) ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$iva, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($iva) ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$totalFinal, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($totalFinal) ?>
                </span>
              </td>

              <td>
                <span class="money" data-eur="<?= htmlspecialchars((string)$margem, ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                  <?= euro($margem) ?>
                </span>
              </td>

              <td>
                <?php if (!empty($p['pagamento_inicial_pago'])): ?>
                  <small>
                    <span class="money"
                      data-eur="<?= htmlspecialchars((string)($p['pagamento_inicial_valor'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"
                      data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
                      <?= euro((float)($p['pagamento_inicial_valor'] ?? 0)) ?>
                    </span>
                  </small>
                <?php else: ?>
                  <span class="badge pendente">Pendente</span>
                <?php endif; ?>
              </td>

              <td class="actions">
                <?php if ($isCliente || $isContabilista ): ?>
                  <a href="ver_proposta.php?id=<?= (int)$p['id'] ?>" class="view"><i class="bi bi-eye-fill"></i> Ver</a>
                <?php else: ?>
                  <?php 
                    $estadoRow = strtolower(trim($p['estado'] ?? ''));
                    if ($estadoRow === 'adjudicada'):
                  ?>
                    <?php if (empty($p['pagamento_inicial_pago'])): ?>
                      <button class="btn-estado cinzento" data-id="<?= (int)$p['id'] ?>" data-estado="arquivada"><i class="bi bi-box-seam"></i> Arquivar</button>
                    <?php else: ?>
                      <span style="color:green; font-weight:600;"><i class="bi bi-check-circle-fill"></i> Proposta adjudicada</span>
                    <?php endif; ?>

                  <?php elseif ($estadoRow === 'cancelada'): ?>
                    <a href="renegociar.php?id=<?= (int)$p['id'] ?>" class="renegociar"><i class="bi bi-arrow-repeat"></i> Renegociar</a>

                  <?php else: ?>
                    <a href="renegociar.php?id=<?= (int)$p['id'] ?>" class="renegociar"><i class="bi bi-arrow-repeat"></i> Renegociar</a>
                    <button class="btn-estado verde" data-id="<?= (int)$p['id'] ?>" data-estado="adjudicada"><i class="bi bi-check-circle-fill"></i> Adjudicar</button>
                    <button class="btn-estado vermelho" data-id="<?= (int)$p['id'] ?>" data-estado="cancelada"><i class="bi bi-x-circle-fill"></i> Cancelar</button>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>

            <?php endforeach; endif; ?>
            </tbody>

        </table>
      </div>
    </div>
</main>
<?php include 'rodape.php'; ?>

<script>
// Linha clicável (exceto botões/links)
function popupMensagem(texto, callback = null) {
  document.getElementById("popup-message").innerText = texto;
  document.getElementById("popup-overlay").style.display = "flex";

  const okBtn = document.getElementById("popup-ok");
  okBtn.onclick = () => {
    document.getElementById("popup-overlay").style.display = "none";
    if (callback) callback();
  };
}
function popupConfirm(mensagem, callbackYes, callbackNo = null) {
  document.getElementById("popup-confirm-message").innerText = mensagem;
  document.getElementById("popup-confirm-overlay").style.display = "flex";

  const btnYes = document.getElementById("popup-confirm-yes");
  const btnNo  = document.getElementById("popup-confirm-no");

  btnYes.onclick = () => {
    document.getElementById("popup-confirm-overlay").style.display = "none";
    if (callbackYes) callbackYes();
  };

  btnNo.onclick = () => {
    document.getElementById("popup-confirm-overlay").style.display = "none";
    if (callbackNo) callbackNo();
  };
}

document.querySelectorAll('.clickable-row').forEach(row => {
  row.addEventListener('click', e => {
    if (!e.target.closest('button') && !e.target.closest('a')) {
      window.location.href = row.dataset.href;
    }
  });
});

// Botões de estado com confirmação
document.querySelectorAll('.btn-estado').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const id = btn.dataset.id;
    const estado = btn.dataset.estado;

    let msg = '';
    if (estado === 'adjudicada') msg = 'Tem a certeza que quer adjudicar esta proposta?';
    if (estado === 'cancelada')  msg = 'Tem a certeza que quer cancelar esta proposta?';
    if (estado === 'arquivada')  msg = 'Tem a certeza que quer arquivar esta proposta?';

    if (!msg) return;

    // 🔥 Substituir confirm() pelo popupConfirm()
   popupConfirm(msg, () => {

      // ✅ Se for adjudicar: vai DIRETO ao adjudicar_proposta.php
      if (estado.toLowerCase() === 'adjudicada') {
        window.location.href = "adjudicar_proposta.php?id=" + id;
        return;
      }

      // ✅ Para os outros estados continua a usar update_estado.php
      fetch('update_estado.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ id, estado }).toString()
      })
      .then(r => r.json())
      .then(res => {
        if (res.ok) {
          const tr = btn.closest('tr');
          const actions = tr.querySelector('.actions');
          const badge = tr.querySelector('.badge');
          const estadoLower = estado.toLowerCase();

          badge.textContent = estadoLower.charAt(0).toUpperCase() + estadoLower.slice(1);
          badge.className = "badge " + estadoLower;

          // (o resto igual para cancelada/arquivada)
        } else {
          popupMensagem('Erro ao atualizar estado: ' + (res.error || 'desconhecido'));
        }
      })
      .catch(err => popupMensagem('Falha na ligação: ' + err.message));

    });


  });
});

</script>

<!-- Popup de Confirmação (Sim / Não) -->
<div id="popup-confirm-overlay" class="popup-overlay" style="display:none;">
  <div class="popup-box">
    <img src="../img/logo.png" class="popup-logo" alt="Logo">
    <p id="popup-confirm-message"></p>

    <div class="popup-buttons">
      <button id="popup-confirm-yes" class="popup-yes">SIM</button>
      <button id="popup-confirm-no" class="popup-no">NÃO</button>
    </div>
  </div>
</div>
<script>
(() => {
  'use strict';

  const CURRENCY_META = {
    EUR: { decimals: 2 },
    USD: { decimals: 2 },
    GBP: { decimals: 2 },
    JPY: { decimals: 0 },
  };

  const FX = {
    EUR: 1,    // 1 EUR = 1 EUR
    USD: 0.85, // 1 USD = 0.85 EUR (editável)
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
      sub.style.fontSize = "12px";
      sub.style.color = "#666";
      sub.style.marginTop = "4px";
      sub.style.whiteSpace = "nowrap";
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

  // ligar inputs
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
    // carregar valor inicial do input
    const v0 = safeNum(input.value);
    if (v0 > 0) FX[code] = v0;
  }

  bindFx(inUSD, "USD");
  bindFx(inGBP, "GBP");
  bindFx(inJPY, "JPY");

  refreshAll();
})();
</script>
<script>
(function(){
  const tip = document.createElement("div");
  tip.className = "sx-tooltip";
  document.body.appendChild(tip);

  function setLines(text){
    let t = String(text || "");

    // aceitar várias formas de quebra
    t = t.replace(/&#10;|&#x0A;|<br\s*\/?>/gi, "\n");

    const lines = t.split("\n").map(s => s.trim()).filter(Boolean);

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

    const x = r.left + (r.width/2);
    const y = r.top - 12;

    tip.style.left = x + "px";
    tip.style.top  = y + "px";
    tip.style.transform = "translate(-50%, -100%)";

    requestAnimationFrame(() => {
      const tr = tip.getBoundingClientRect();
      let dx = 0;
      if (tr.left < pad) dx = pad - tr.left;
      if (tr.right > window.innerWidth - pad) dx = (window.innerWidth - pad) - tr.right;
      if (dx) tip.style.left = (x + dx) + "px";
    });
  }

  document.querySelectorAll(".servicos-pill[data-tooltip]").forEach(el => {
    el.addEventListener("mouseenter", () => {
      setLines(el.dataset.tooltip); // aqui \n funciona porque o HTML já decodifica &#10;
      place(el);
      tip.classList.add("show");
    });
    el.addEventListener("mousemove", () => place(el));
    el.addEventListener("mouseleave", () => tip.classList.remove("show"));
  });

  window.addEventListener("scroll", () => tip.classList.remove("show"), { passive:true });
})();
</script>

</body>
</html>
