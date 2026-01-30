<?php
// paginas/renegociar.php
include 'protecao.php';
include '../conexao/conexao.php';
include '../conexao/funcoes.php';
include './cabecalho.php';

$id_antiga = $_GET['id'] ?? null;
if (!$id_antiga) die("Proposta inválida.");

// ===== Proposta original =====
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
$stmt->execute([$id_antiga]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$proposta) die("Proposta não encontrada.");

// ===== Áreas da proposta original =====
$stmt = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
$stmt->execute([$id_antiga]);
$areasAntigas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Serviços da proposta original (com nome e tipo_opcao) =====
$stmt = $pdo->prepare("
  SELECT sp.*, s.nome AS nome_servico, s.tipo_opcao
  FROM servicos_proposta sp
  LEFT JOIN servicos_produtos s ON sp.id_servico = s.id
  WHERE sp.id_proposta = ?
  ORDER BY sp.id_proposta ASC
");
$stmt->execute([$id_antiga]);
$servicosAntigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$incTec  = (int)($proposta['inclui_tecnico_alimentacao'] ?? 1);
$incDist = (int)($proposta['inclui_distancia'] ?? 1);
$incluiLodImgs = (int)($proposta['inclui_lod_imgs'] ?? 1);
$topoMais10 = (int)($proposta['topo_aplicar_mais10'] ?? 1);

$precoTec = (float)($proposta['preco_tecnico_alimentacao'] ?? 130);
$precoDist = (float)($proposta['preco_distancia'] ?? 0);
$precoDesl = (float)($proposta['preco_deslocacao_total'] ?? ($precoTec + $precoDist));


// ===== Catálogo de serviços para poderes adicionar novos =====
$stmt = $pdo->query("SELECT id, nome, tipo_opcao FROM servicos_produtos ORDER BY nome ASC");
$todosServicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper seguro para HTML
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Renegociar <?= h($proposta['codigo']) ?> | SupremExpansion</title>
<link rel="stylesheet" href="../css/renegociar.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br><br>
<div class="container">
  <h1>Renegociar Proposta <?= h($proposta['codigo']) ?></h1>
  <a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>

  <form id="formProposta" method="POST" action="salvar_proposta.php" enctype="multipart/form-data">
    <input type="hidden" name="parent_id" value="<?= (int)$proposta['id'] ?>">

    <!-- ====== Dados base ====== -->
    <label>Cliente:</label>
    <input type="text" name="nome_cliente" value="<?= h($proposta['nome_cliente']) ?>" required>

    <label>Email do Cliente:</label>
    <input style="width:100%; padding:8px; border-radius:8px; border:1px solid #ccc; margin-top:5px; margin-bottom:12px;" type="email" name="email_cliente" value="<?= h($proposta['email_cliente']) ?>" required>

    <label>Telefone do Cliente:</label>
    <input type="text" name="telefone_cliente" value="<?= h($proposta['telefone_cliente']) ?>">

    <label>Empresa:</label>
    <input
      type="text"
      name="empresa_nome"
      id="empresa_nome"
      value="<?= h($proposta['empresa_nome'] ?? '') ?>"
      placeholder="Ex: Empresa Lda"
    />

    <label>NIF da Empresa:</label>
    <input
      type="text"
      name="empresa_nif"
      id="empresa_nif"
      value="<?= h($proposta['empresa_nif'] ?? '') ?>"
      placeholder="Ex: 123456789"
      maxlength="20"
    />

    <label>Nome da Obra:</label>
    <input type="text" name="nome_obra" value="<?= h($proposta['nome_obra']) ?>" required>

    <label>Endereço da Obra:</label>
    <input type="text" name="endereco_obra" value="<?= h($proposta['endereco_obra']) ?>" required>

    <label>Distância (km só ida):</label>
    <input type="number" id="distancia" name="distancia" min="0" step="1"
          value="<?= h((string)$proposta['distancia_km']) / 2 ?>"
          oninput="atualizarResumo()">

    <!-- Deslocação: componentes (fixo + distância) -->
    <div id="deslocacaoBox" style="margin-top:10px; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa;">
      <div style="font-weight:800; margin-bottom:8px;">Deslocação</div>

      <label style="display:flex; align-items:center; gap:10px; margin:8px 0;">
        <input type="checkbox" id="chk_tecnico_alim" <?= $incTec ? 'checked' : '' ?> onchange="atualizarResumo()">
        <span style="flex:1;">Adicionar Técnico + Alimentação (fixo)</span>
        <span id="valor_tecnico_alim" data-eur="130" style="font-weight:800;">130,00 €</span>
      </label>

      <label style="display:flex; align-items:center; gap:10px; margin:8px 0;">
        <input type="checkbox" id="chk_distancia" <?= $incDist ? 'checked' : '' ?> onchange="atualizarResumo()">
        <span style="flex:1;">Adicionar Distância (km × 0,40 × 2 × dias)</span>
        <span id="valor_distancia" data-eur="0" style="font-weight:800;">0,00 €</span>
      </label>

      <div style="font-size:12px; color:#666; margin-top:6px;">
        Dias estimados: <span id="deslocacao_dias_lbl">1</span>
      </div>

      <!-- hiddens para salvar_proposta.php -->
      <input type="hidden" name="inclui_tecnico_alimentacao" id="inclui_tecnico_alimentacao" value="<?= $incTec ?>">
      <input type="hidden" name="inclui_distancia" id="inclui_distancia" value="<?= $incDist ?>">
      <input type="hidden" name="preco_tecnico_alimentacao" id="preco_tecnico_alimentacao" value="<?= $precoTec ?>">
      <input type="hidden" name="preco_distancia" id="preco_distancia" value="<?= $precoDist ?>">
      <input type="hidden" name="preco_deslocacao_total" id="preco_deslocacao_total" value="<?= $precoDesl ?>">
      <input type="hidden" name="topo_aplicar_mais10" id="topo_aplicar_mais10" value="<?= $topoMais10 ?>">

    </div>

    <label>Moeda:</label>
    <select id="moedaSelect" name="codigo_pais" required>
      <option value="EUR" <?= ($proposta['codigo_pais']=='EUR'?'selected':'') ?>>Euro</option>
      <option value="GBP" <?= ($proposta['codigo_pais']=='GBP'?'selected':'') ?>>Libra</option>
      <option value="JPY" <?= ($proposta['codigo_pais']=='JPY'?'selected':'') ?>>Iene</option>
      <option value="USD" <?= ($proposta['codigo_pais']=='USD'?'selected':'') ?>>Dólar</option>
    </select>

    <!-- Taxa de conversão (editável) -->
    <div id="taxaBox" style="margin-top:10px; display:none;">
      <div style="font-size:13px; color:#444; margin-bottom:6px;">
        Conversão usada (editável):
      </div>

      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <div style="font-weight:600;">
          1 <span id="taxaMoedaLabel">USD</span> =
        </div>

        <input
          type="number"
          id="taxaParaEUR"
          step="0.000001"
          min="0"
          value="1"
          style="width:160px; padding:8px; border:1px solid #ddd; border-radius:8px;"
        />

        <div style="font-weight:600;">EUR</div>
      </div>
    </div>


    <hr>

    <!-- ====== Serviços ====== -->
    <h2>Serviços</h2>
    <div id="servicosContainer"></div>

    <div style="display:flex; gap:10px; align-items:center;">
      <select id="novoServico" style="flex:1;">
        <option value="">— Selecionar novo serviço —</option>
        <?php foreach($todosServicos as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($s['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn" id="addServicoBtn">Adicionar Serviço</button>
    </div>

    <div class="muted">• Alguns serviços têm opções (ex.: Nível de Detalhe, Modelo 3D, BIM).  
    • “Levantamento Topográfico” permite introduzir preço manual (s/ IVA) e soma +10%.</div>

    <hr>

    <!-- ====== Áreas ====== -->
    <h2>Áreas da Obra</h2>
    <div id="areasContainer"></div>
    <button type="button" class="btn" id="addAreaBtn">Adicionar Área</button>

    <hr>

    <!-- ====== Resumo (como no criar_proposta) ====== -->
    <div id="resumoPrecos" style="margin-top: 8px;">
      <h2>Resumo de Preços</h2>
      <table>
        <thead>
          <tr><th style="text-align:left;">Serviço</th><th style="text-align:right;">Preço Total (<span id="moedaHeaderLabel">€</span>)</th></tr>
        </thead>
        <tbody id="tabelaResumoBody">
          <tr>
            <td>
              Levantamento Laser Scan
              <div id="laser_total_m2_info" style="font-size:12px; color:#666; margin-top:4px;">
                Total áreas: 0 m²
              </div>
            </td>

            <td id="preco_total_laser" data-eur="0" style="text-align:right;">0.00 €</td>
            <input type="hidden" name="preco_total_laser" id="input_preco_total_laser">
          </tr>
          <tr id="linha_topografico" style="display:none;">
            <td>Levantamento Topográfico (+10%)</td>
            <td id="preco_total_topo" data-eur="0" style="text-align:right;">0.00 €</td>
            <input type="hidden" name="preco_topografico" id="input_preco_topografico">
          </tr>
          <tr id="linha_drone" style="display:none;">
            <td>Levantamento Drone </td>
            <td id="preco_total_drone" data-eur="0" style="text-align:right;">0.00 €</td>
            <input type="hidden" name="preco_total_drone" id="input_preco_total_drone">
          </tr>
          <tr id="linha_render" style="display:none;">
            <td>Renderizações</td>
            <td id="preco_total_render" data-eur="0" style="text-align:right;">0.00 €</td>
            <input type="hidden" name="total_render" id="input_total_render">
          </tr>


        </tbody>
      </table>
    </div>
    <hr>

    <h2>Custos & Margem (preview)</h2>

    <label>Custos Extra (manual, s/ IVA) (€):</label>
    <input
      type="number"
      name="custos_extra"
      id="custos_extra"
      step="0.01"
      min="0"
      value="<?= h((string)($proposta['custos_extra'] ?? 0)) ?>"
      oninput="window.RN?.onCustosExtraManual?.()"
    />
    <div class="muted">
      • Por defeito, preenche com o valor do Topográfico (s/ IVA).  
      • Se alterares manualmente aqui, deixa de seguir o Topográfico.
    </div>

    <div class="preview-grid" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; margin-top:12px;">
      <div class="preview-box" style="background:#fafafa; padding:10px; border-radius:10px;">
        <div class="muted">Sub custos da empresa</div>
        <div id="pv_sub_custos" style="font-weight:700;">0,00 €</div>
      </div>

      <div class="preview-box" style="background:#fafafa; padding:10px; border-radius:10px;">
        <div class="muted">Tempo estimado (dias)</div>
        <div id="pv_tempo_dias" style="font-weight:700;">0</div>
      </div>

      <div class="preview-box" style="background:#fafafa; padding:10px; border-radius:10px;">
        <div class="muted">Margem liberta</div>
        <div id="pv_margem" style="font-weight:700;">0,00 €</div>
      </div>
    </div>


    <div id="tabelaTotais" class="totalz">
      <h2>TOTAIS</h2>
      <table>
        <tbody>
          <tr>
            <td>TOTAL BRUTO:</td>
            <td id="total_bruto" data-eur="0" style="text-align:right;">0.00 €</td>
          </tr>
          <tr>
            <td>DESCONTO GLOBAL (%):</td>
            <td style="text-align:right;">
              <input type="number" id="desconto_global" name="desconto_percentagem" value="<?= h((string)($proposta['desconto_percentagem'] ?? 0)) ?>" min="0" step="1" max="100" style="width:90px; text-align:right;" oninput="atualizarTotaisFinais()"> %
            </td>
          </tr>
          <tr>
            <td>TOTAL COM DESCONTO:</td>
            <td id="total_com_desconto" data-eur="0" style="text-align:right;">0.00 €</td>
          </tr>
          <tr>
            <td>TOTAL I.V.A. (23%):</td>
            <td id="total_iva" data-eur="0" style="text-align:right;">0.00 €</td>
          </tr>
          <tr style="font-weight:bold;">
            <td>TOTAL FINAL:</td>
            <td id="total_final" data-eur="0" style="text-align:right;">0.00 €</td>
          </tr>
        </tbody>
      </table>
    </div>

    <hr>

    <label>Observações:</label>
    <textarea name="observacoes" rows="4"><?= h($proposta['observacoes']) ?></textarea>
    
    <hr>

    <h2>PDF</h2>

    <label style="display:flex; align-items:center; gap:10px; margin:10px 0;">
      <input type="checkbox" id="chk_incluir_lod_imgs" <?= $incluiLodImgs ? 'checked' : '' ?> onchange="syncLodImgsHidden()">
      <span>Deseja adicionar as imagens do nível de detalhe (LOD) no PDF?</span>
    </label>

    <input type="hidden" name="incluir_lod_imgs" id="incluir_lod_imgs" value="<?= $incluiLodImgs ?>">

    <script>
    function syncLodImgsHidden(){
      const chk = document.getElementById('chk_incluir_lod_imgs');
      const hid = document.getElementById('incluir_lod_imgs');
      if (!chk || !hid) return;
      hid.value = chk.checked ? "1" : "0";
    }
    document.addEventListener("DOMContentLoaded", syncLodImgsHidden);
    </script>

    <hr>
    <button type="submit" class="btn"><i class="bi bi-save-fill"></i> Salvar Renegociação</button>
  </form>
</div>

<script>
(() => {
  'use strict';

  // ========= DADOS PHP → JS =========
  const SERVICOS_BASE = <?= json_encode($todosServicos, JSON_UNESCAPED_UNICODE) ?>;
  const SERVICOS_OLD  = <?= json_encode($servicosAntigos, JSON_UNESCAPED_UNICODE) ?>;
  const AREAS_OLD     = <?= json_encode($areasAntigas, JSON_UNESCAPED_UNICODE) ?>;
  const TOPO_MAIS10_DB = <?= (int)$topoMais10 ?>;

  // ========= ELEMENTOS =========
  const servicosContainer = document.getElementById('servicosContainer');
  const novoServico       = document.getElementById('novoServico');
  const addServicoBtn     = document.getElementById('addServicoBtn');
  const areasContainer    = document.getElementById('areasContainer');
  const addAreaBtn = document.getElementById('addAreaBtn');

  addAreaBtn?.addEventListener('click', () => {
    adicionarArea();        // cria nova área
    atualizarResumo();      // recalcula tudo e atualiza visualmente
  });


  // ========= ESTADO =========
  const state = {
    custosExtraManual: false,
    servicoCount: 0,
    areaCount: 0,
    topoInputId: null,
    topoMais10: (typeof TOPO_MAIS10_DB !== "undefined" ? !!TOPO_MAIS10_DB : true),
    totals: { laser: 0, topo: 0 }
  };


  // ========= MAPAS DE OPÇÕES / AJUSTES =========
  const opcaoMapPorTipo = {
    "niveldedetalhe": ["1:200", "1:100", "1:50", "1:20", "1:1"],
    "modelo3dniveldedetalhe": ["1:200", "1:100", "1:50", "1:20"],
    "bim": ["Bricscad", "Archicad", "Revit"]
  };
  const ajustesNivelDetalhe = { "1:200": -10, "1:100": 0, "1:50": 60, "1:20": 130, "1:1": 300 };
  const ajustesModelo3D    = { "1:200": 0,   "1:100": 60, "1:50": 130, "1:20": 300, "1:1": 300 };
  const ajustesBIM         = { "Bricscad": 20, "Archicad": 20, "Revit": 20 };
  


  // ========= HELPERS =========
  function hNum(v){ const n = parseFloat(v); return isNaN(n) ? 0 : n; }
  function euro(n){ n = Number(n||0); return n.toFixed(2).replace('.', ',') + ' €'; }
  function parseEuroText(txt){
    if (!txt) return 0;
    let s = String(txt).replace(/\s|€/g,'').replace(/\./g,'').replace(',', '.');
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }
  function normalizarTexto(txt){
    return (txt||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]/gi,'').toLowerCase();
  }
  function setOpcaoDropdown(id, disabled = true){
    const opt = novoServico?.querySelector(`option[value="${id}"]`);
    if (opt) opt.disabled = disabled;
  }
  function jaExisteCardPorId(id){
    return !!servicosContainer.querySelector(`.servico-card[data-id="${id}"]`);
  }
  function getCustosExtra(){
    const el = document.getElementById('custos_extra');
    return el ? hNum(el.value) : 0;
  }

  function setCustosExtra(v){
    const el = document.getElementById('custos_extra');
    if (!el) return;
    el.value = (Number(v||0)).toFixed(2);
  }

  function onCustosExtraManual(){
    // quando o user mexe no input, deixa de seguir o topo automaticamente
    state.custosExtraManual = true;
    atualizarTotaisFinais();
  }
  function ensureLinhaDeslocacao() {
    let linhaDesloc = document.getElementById("linha_deslocacao");
    if (!linhaDesloc) {
      const row = document.createElement("tr");
      row.id = "linha_deslocacao";
      row.innerHTML = `
        <td>Deslocação</td>
        <td id="preco_total_deslocacao" data-eur="0" style="text-align:right;"></td>
      `;
      document.getElementById("tabelaResumoBody")?.appendChild(row);
      setMoney(document.getElementById("preco_total_deslocacao"), 0);
    }
  }
  function round6(n){
    n = Number(n || 0);
    return Math.round(n * 1e6) / 1e6;
  }

  function fmtPm2_6(n){
    // até 6 decimais, sem zeros finais (fica igual ao "criar proposta")
    const v = round6(n);
    return v.toFixed(6).replace(/\.?0+$/, '');
  }
 

  // ======================
  // MOEDAS (UI) + CONVERSÃO
  // ======================
  window.__CUR = {
    code: "EUR",
    rateToEUR: 1, // 1 [code] = X EUR
  };

  const CURRENCY_META = {
    EUR: { symbolFallback: "€", decimals: 2, defaultRateToEUR: 1 },
    USD: { symbolFallback: "$", decimals: 2, defaultRateToEUR: 0.85 },
    GBP: { symbolFallback: "£", decimals: 2, defaultRateToEUR: 1.15 },
    JPY: { symbolFallback: "¥", decimals: 0, defaultRateToEUR: 0.0055 },
  };

  function round2(n){ n = Number(n || 0); return Math.round(n * 100) / 100; }

  function formatCurrency(amount, code) {
    const meta = CURRENCY_META[code] || CURRENCY_META.EUR;
    const n = Number(amount || 0);

    try {
      return new Intl.NumberFormat("pt-PT", {
        style: "currency",
        currency: code,
        minimumFractionDigits: meta.decimals,
        maximumFractionDigits: meta.decimals,
      }).format(n);
    } catch (e) {
      return n.toFixed(meta.decimals).replace(".", ",") + " " + (meta.symbolFallback || "");
    }
  }

  function eurToSelectedCurrency(eurAmount) {
    const eur = Number(eurAmount || 0);
    const code = window.__CUR.code;
    const rateToEUR = Number(window.__CUR.rateToEUR || 1);
    if (code === "EUR") return eur;
    if (rateToEUR <= 0) return 0;
    return eur / rateToEUR;
  }

  function ensureMoneySubline(el) {
    if (!el) return null;
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

  function setMoney(el, eurAmount) {
    if (!el) return;
    const eur = round2(eurAmount);
    el.dataset.eur = String(eur);

    el.innerHTML = "";

    const main = document.createElement("div");
    main.className = "money-main";

    const shown = eurToSelectedCurrency(eur);
    main.textContent = formatCurrency(shown, window.__CUR.code);
    el.appendChild(main);

    if (window.__CUR.code !== "EUR") {
      const sub = ensureMoneySubline(el);
      sub.textContent = "≈ " + formatCurrency(eur, "EUR");
    }
  }

  function refreshAllMoney() {
    document.querySelectorAll("[data-eur]").forEach(el => {
      const eur = parseFloat(el.dataset.eur || "0") || 0;
      setMoney(el, eur);
    });

    const meta = CURRENCY_META[window.__CUR.code] || CURRENCY_META.EUR;
    const headerLabel = document.getElementById("moedaHeaderLabel");
    if (headerLabel) headerLabel.textContent = meta.symbolFallback;

    const taxaBox = document.getElementById("taxaBox");
    const taxaLabel = document.getElementById("taxaMoedaLabel");
    const taxaInput = document.getElementById("taxaParaEUR");

    if (window.__CUR.code === "EUR") {
      if (taxaBox) taxaBox.style.display = "none";
    } else {
      if (taxaBox) taxaBox.style.display = "block";
      if (taxaLabel) taxaLabel.textContent = window.__CUR.code;
      if (taxaInput) taxaInput.value = String(window.__CUR.rateToEUR);
    }
  }

  function initCurrencyUI() {
    const sel = document.getElementById("moedaSelect");
    const taxaInput = document.getElementById("taxaParaEUR");
    if (!sel) return;

    window.__CUR.code = sel.value || "EUR";
    window.__CUR.rateToEUR = (CURRENCY_META[window.__CUR.code]?.defaultRateToEUR || 1);

    refreshAllMoney();

    sel.addEventListener("change", () => {
      const code = sel.value || "EUR";
      window.__CUR.code = code;
      window.__CUR.rateToEUR = (CURRENCY_META[code]?.defaultRateToEUR || 1);

      atualizarAjustesTotais();
      refreshAllMoney();
    });

    if (taxaInput) {
      taxaInput.addEventListener("input", () => {
        const v = parseFloat(taxaInput.value);
        if (!isNaN(v) && v > 0) window.__CUR.rateToEUR = v;
        refreshAllMoney();
      });
    }
  }

  // ========= SERVIÇOS =========
  function removerServico(btn){
    const card = btn.closest('.servico-card');
    if (!card) return;
    const id = card.getAttribute('data-id');
    // Se for topográfico, libertar "topoInputId"
    if (state.topoInputId && card.querySelector(`#${state.topoInputId}`)){
      state.topoInputId = null;
      const lt = document.getElementById('linha_topografico');
      if (lt) lt.style.display = 'none';
      state.totals.topo = 0;
    }
    if (!state.custosExtraManual) {
      setCustosExtra(0);
    }

    const opt = novoServico?.querySelector(`option[value="${id}"]`);
    if (opt) opt.disabled = false;
    card.remove();
    setAltimetricoDisponivel();

    atualizarAjustesTotais();
  }

  function adicionarServico(serv, opcaoSelecionada = "", precoManual = ""){
    if (!serv || jaExisteCardPorId(serv.id)) return;
        // Altimétrico só pode entrar se Drone existir
    if (Number(serv.id) === ID_ALT && !hasDrone()) return;

    const card = document.createElement('div');
    card.className = 'servico-card';
    card.dataset.id = serv.id;

    const nomeNorm = normalizarTexto(serv.nome);
    let html = `
      <strong>${serv.nome}</strong>
      <input type="hidden" name="servicos[${state.servicoCount}][id]" value="${serv.id}">
      <button type="button" class="btn btn-remove-servico" onclick="window.RN.removerServico(this)">Remover</button>
    `;

    // Topográfico: único, com input de preço (s/ IVA)
    if (nomeNorm === 'levantamentotopografico'){
      const topoId = `preco_topografico_${Date.now()}`;
      const chkId  = `chk_topo_mais10_${Date.now()}`;
      state.topoInputId = topoId;

      html = `
        <strong>${serv.nome}</strong>
        <input type="hidden" name="servicos[${state.servicoCount}][id]" value="${serv.id}">

        <div style="margin-top:8px;">
          <label>Preço (s/ IVA) (€):</label>
          <input type="number" class="input-preco-topografico" id="${topoId}"
                name="servicos[${state.servicoCount}][preco]" step="0.01" min="0"
                value="${precoManual || ''}" oninput="window.RN.atualizarAjustesTotais()">

          <label style="display:flex; align-items:center; gap:10px; margin-top:10px;">
            <input type="checkbox" id="${chkId}" ${state.topoMais10 ? "checked" : ""}>
            <span>Aplicar +10% no Topográfico</span>
          </label>

          <div class="muted" id="topo_hint_txt">
            ${state.topoMais10 ? "Será aplicado +10% a este valor." : "Sem +10% aplicado."}
          </div>

          <div id="pdf_prev_topo" data-eur="0"
              style="margin-top:6px; font-size:12px; color:#666; font-style:italic;">
            PDF (c/ desloc. + IVA): —
          </div>
        </div>

        <button type="button" class="btn btn-remove-servico" onclick="window.RN.removerServico(this)">Remover</button>
      `;

      setTimeout(() => {
        const topoEl = document.getElementById(topoId);
        const chkEl  = document.getElementById(chkId);
        const hid    = document.getElementById("topo_aplicar_mais10");
        const hint   = document.getElementById("topo_hint_txt");

        if (chkEl) {
          chkEl.addEventListener("change", () => {
            state.topoMais10 = chkEl.checked;
            if (hid) hid.value = state.topoMais10 ? "1" : "0";
            if (hint) hint.textContent = state.topoMais10 ? "Será aplicado +10% a este valor." : "Sem +10% aplicado.";
            atualizarAjustesTotais();
          });
        }

        // garantir hidden coerente no load
        if (hid) hid.value = state.topoMais10 ? "1" : "0";

        // custos extra segue topo base se ainda não foi manual
        if (topoEl) {
          if (!state.custosExtraManual) setCustosExtra(hNum(topoEl.value));

          topoEl.addEventListener('input', () => {
            if (!state.custosExtraManual) setCustosExtra(hNum(topoEl.value));
            atualizarAjustesTotais();
          });
        }
      }, 0);
    }


    if (nomeNorm === "levantamentodronefotos"){
      html = `
        <strong>${serv.nome}</strong>
        <input type="hidden" name="servicos[${state.servicoCount}][id]" value="${serv.id}">

        <div style="margin-top:8px;">
          <label>Preço base automático:</label>
          <div id="preco_drone_base" style="background:#eee; padding:6px 10px; border-radius:8px;">0.00 €</div>
        </div>

        <div style="margin-top:12px;">
          <label>Opções adicionais:</label><br>
          <label>
              <input type="checkbox" class="chk-drone" name="opcoes_drone[]" value="Georreferenciação" data-preco="200">
              Georreferenciação (+200 €)
          </label><br>

          <label>
              <input type="checkbox" class="chk-drone" name="opcoes_drone[]" value="Criação de Nuvem de Pontos" data-preco="200">
              Criação de Nuvem de Pontos (+200 €)
          </label><br>

          <label>
              <input type="checkbox" class="chk-drone" name="opcoes_drone[]" value="Modelo DIM" data-preco="100">
              Modelo DIM (+100 €)
          </label><br>

          <label>
              <input type="checkbox" class="chk-drone" name="opcoes_drone[]" value="Modelo Renderizado Materiais" data-preco="100">
              Modelo Renderizado Materiais (+100 €)
          </label>

        </div>
        <br>
        <button type="button" class="btn btn-remove-servico" onclick="window.RN.removerServico(this)">
          Remover
        </button>
      `;

      card.innerHTML = html;
      servicosContainer.appendChild(card);
      setOpcaoDropdown(serv.id,true);
      state.servicoCount++;

      // listeners
      document.querySelectorAll(".chk-drone").forEach(chk => {
          chk.addEventListener("change", atualizarAjustesTotais);
      });

      // atualizar preço inicial
      atualizarDronePrecoBase();
      atualizarAjustesTotais();

      return;
    }


    // Opções por tipo_opcao OU por nome normalizado
    const tipoOp = normalizarTexto(serv.tipo_opcao || '');
    const chave = opcaoMapPorTipo[tipoOp] ? tipoOp : (opcaoMapPorTipo[nomeNorm] ? nomeNorm : null);

    if (chave){
      html += `
        <div style="margin-top:8px;">
          <label>Opção:</label>
          <select class="select-opcao" name="opcao_servico[${serv.id}]" data-tipo="${chave}" onchange="window.RN.atualizarAjustesTotais()">
            <option value="">Selecione uma opção</option>
            ${opcaoMapPorTipo[chave].map(op => `<option value="${op}" ${op===opcaoSelecionada?'selected':''}>${op}</option>`).join('')}
          </select>
        </div>
      `;
    }

    card.innerHTML = html;
    servicosContainer.appendChild(card);
    setOpcaoDropdown(serv.id, true);
    state.servicoCount++;
    setAltimetricoDisponivel();

    atualizarAjustesTotais();
  }

  addServicoBtn?.addEventListener('click', () => {
    const id = novoServico?.value;
    if (!id) return;
    const s = SERVICOS_BASE.find(x => String(x.id) === String(id));
    adicionarServico(s);
    const opt = novoServico?.querySelector(`option[value="${id}"]`);
    if (opt) opt.disabled = true;
    if (novoServico) novoServico.value = "";
  });

  // ========= ÁREAS =========
  function getTotalM2Areas(){
    let totalM2 = 0;
    for (let i=0; i<state.areaCount; i++){
      totalM2 += hNum(document.getElementById(`area_m2_${i}`)?.value);
    }
    return totalM2;
  }

  // preço/m² GLOBAL (auto) calculado pelo TOTAL m²
  function calcularPrecoPorMetroGlobal(totalM2){
    totalM2 = Number(totalM2 || 0);
    if (totalM2 <= 0) return 0;
    if (totalM2 <= 200) return 600 / totalM2; // garante que o total dá 600
    return 600 / (200 + Math.pow(totalM2 - 200, 0.741));
  }


  function adicionarArea(nome="", m2="", preco_m2="", subtotalPref="", exterior=0, subtotalManualAtivo=false, precoM2Manual=false) {


    const i = state.areaCount;
    const div = document.createElement('div');
    div.className = 'area-item';

    const precoManualAttr = ""; // ✅ não assumes preço manual só porque subtotal manual está ativo



    div.innerHTML = `
      <strong>Área ${i+1}</strong>
      <div class="area-flex">
        <div>
          <label>Nome da Área</label>
          <input type="text" name="areas[${i}][nome]" id="area_nome_${i}" value="${nome || ('Área ' + (i+1))}">
        </div>
        <div>
          <label>Metros Quadrados</label>
          <input type="number" name="areas[${i}][m2]" id="area_m2_${i}" min="0" step="0.01" value="${m2 || ''}" oninput="window.RN.atualizarArea(${i})">
        </div>
        <div>
          <label>Preço/m² (€)</label>
          <input type="number" name="areas[${i}][preco_m2]" id="area_preco_${i}" step="0.01" value="${preco_m2 || ''}" ${precoManualAttr} oninput="window.RN.onPrecoM2Change(${i})">

        </div>
        <div style="margin-top:8px;">
          <label style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" id="area_subtotal_manual_chk_${i}" ${subtotalManualAtivo  ? "checked" : ""} onchange="window.RN.onToggleSubtotalManual(${i})">
            <span><b>Subtotal manual</b> (fixo)</span>
          </label>

          <div id="wrap_subtotal_manual_${i}" style="margin-top:6px;  display:${subtotalManualAtivo ? "block" : "none"};">
            <label>Subtotal manual (€):</label>
            <input type="number" id="area_subtotal_manual_inp_${i}" step="0.01" min="0"
                  value="${(subtotalPref ? parseEuroText(subtotalPref) : 0) || ''}"
                  oninput="window.RN.onSubtotalManualChange(${i})">
            <div class="muted">• Quando ativo, o subtotal não muda com arredondamentos. • O preço/m² é recalculado só para referência.</div>
          </div>
        </div>

        <input type="hidden" name="areas[${i}][subtotal_manual]" id="area_subtotal_hidden_${i}" value="">
        <input type="hidden" name="areas[${i}][subtotal_manual_ativo]" id="area_subtotal_ativo_${i}" value="${subtotalManualAtivo  ? 1 : 0}">


        <div class="area-toggle ${exterior ? 'is-exterior' : ''}" id="area_toggle_wrap_${i}">
          <label class="switch" title="Marcar como área exterior">
            <input type="checkbox"
                  name="areas[${i}][exterior]"
                  id="area_ext_${i}"
                  ${exterior ? "checked" : ""}
                  onchange="window.RN.onToggleExterior(${i})">
            <span class="slider"></span>
          </label>

          <div class="toggle-texts">
            <div class="toggle-title">Área exterior</div>
            <div class="toggle-sub">Exterior: 2€/m² • Interior: 400€ por área</div>
          </div>

          <span class="badge" id="area_badge_${i}">${exterior ? "EXTERIOR" : "INTERIOR"}</span>
        </div>


        <div>
          <label>Subtotal</label>

          <div id="subtotal_area_${i}" data-eur="0"
              style="padding:8px 10px; background:#fafafa; border-radius:8px;">
            ${subtotalPref || '0.00 €'}
          </div>

          
        </div>

      </div>
      <div style="margin-top:8px;">
        <button type="button" class="btn btn-remove-area" onclick="window.RN.removerArea(this)">Remover Área</button>
        <button type="button" class="btn" onclick="window.RN.redefinirPrecoM2(${i})">Redefinir preço M²</button>
      </div>
    `;
    areasContainer.appendChild(div);
    // marcar preço/m² como manual (sem ativar subtotal manual)
    if (precoM2Manual) {
      const precoInput = document.getElementById(`area_preco_${i}`);
      if (precoInput) precoInput.dataset.manual = "true";
    }

    state.areaCount++;
    // chamada programática (no mesmo escopo) é OK:
    atualizarArea(i);
    syncToggleUI(i);

  }

  function removerArea(btn){
    const item = btn.closest('.area-item');
    if (!item) return;
    item.remove();
    renumerarAreas();
    atualizarResumo();
  }

  function renumerarAreas(){
    const areaDivs = areasContainer.querySelectorAll('.area-item');
    state.areaCount = 0;
    areaDivs.forEach((div, i) => {
      const strong = div.querySelector('strong');
      
      if (strong) strong.textContent = `Área ${i+1}`;

      const nome = div.querySelector(`[id^="area_nome_"]`);
      const m2   = div.querySelector(`[id^="area_m2_"]`);
      const pm2  = div.querySelector(`[id^="area_preco_"]`);
      const sub  = div.querySelector(`[id^="subtotal_area_"]`);
      const ext = div.querySelector(`[id^="area_ext_"]`);

      const wrap = div.querySelector(`[id^="area_toggle_wrap_"]`);
      const badge = div.querySelector(`[id^="area_badge_"]`);

      const hidSub = div.querySelector(`[id^="area_subtotal_hidden_"]`);
      const hidAtv = div.querySelector(`[id^="area_subtotal_ativo_"]`);

      if (hidSub) {
        hidSub.id = `area_subtotal_hidden_${i}`;
        hidSub.name = `areas[${i}][subtotal_manual]`;
      }
      if (hidAtv) {
        hidAtv.id = `area_subtotal_ativo_${i}`;
        hidAtv.name = `areas[${i}][subtotal_manual_ativo]`;
      }


      if (wrap) wrap.id = `area_toggle_wrap_${i}`;
      if (badge) badge.id = `area_badge_${i}`;

      if (ext){
        ext.id = `area_ext_${i}`;
        ext.name = `areas[${i}][exterior]`;
        ext.setAttribute('onchange', `window.RN.onToggleExterior(${i})`);
        // e sincroniza UI após renumerar
        setTimeout(() => window.RN.onToggleExterior(i), 0);
      }
      const wasManual = pm2?.dataset?.manual;
      if (pm2){
        pm2.id = `area_preco_${i}`;
        pm2.name = `areas[${i}][preco_m2]`;
        pm2.setAttribute('oninput', `window.RN.onPrecoM2Change(${i})`);

        if (wasManual) pm2.dataset.manual = "true";
        else delete pm2.dataset.manual;
      }


      if (nome){ nome.id = `area_nome_${i}`; nome.name = `areas[${i}][nome]`; }
      if (m2){ m2.id = `area_m2_${i}`; m2.name = `areas[${i}][m2]`; m2.setAttribute('oninput', `window.RN.atualizarArea(${i})`); }
      if (pm2){ pm2.id = `area_preco_${i}`; pm2.name = `areas[${i}][preco_m2]`; pm2.setAttribute('oninput', `window.RN.atualizarSubtotalManual(${i})`); }
      if (sub){ sub.id = `subtotal_area_${i}`; }

      state.areaCount++;
    });
  }

  function atualizarArea(i) {
    const m2Input    = document.getElementById(`area_m2_${i}`);
    const precoInput = document.getElementById(`area_preco_${i}`);
    const subSpan    = document.getElementById(`subtotal_area_${i}`);
    if (!m2Input || !precoInput || !subSpan) return;

    const m2 = hNum(m2Input.value);

    // MODO C: SUBTOTAL MANUAL
    if (isSubtotalManual(i)) {
      const hid = document.getElementById(`area_subtotal_hidden_${i}`);
      const subtotalFix = hNum(hid?.value);

      setMoney(subSpan, subtotalFix);

      const pm2 = (m2 > 0) ? (subtotalFix / m2) : 0;
      precoInput.value = fmtPm2_6(pm2);
      precoInput.readOnly = true;

      atualizarResumo();
      return;
    }

    // MODO B: PREÇO/M² MANUAL
    if (precoInput.dataset.manual) {
      precoInput.readOnly = false;
      const pm2 = hNum(precoInput.value);
      setMoney(subSpan, m2 * pm2);
      atualizarResumo();
      return;
    }

    // MODO A: AUTO (GLOBAL)
    precoInput.readOnly = false;
    const pm2Auto = round2(calcularPrecoPorMetroGlobal(getTotalM2Areas()));
    precoInput.value = (isFinite(pm2Auto) ? pm2Auto.toFixed(2) : "0.00");
    setMoney(subSpan, m2 * pm2Auto);

    atualizarResumo();
  }


  function isSubtotalManual(i){
    const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
    return (hidAtivo && String(hidAtivo.value) === "1");
  }

  function getSubtotalArea(i){
    const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);
    const precoM2 = hNum(document.getElementById(`area_preco_${i}`)?.value);

    if (isSubtotalManual(i)) {
      const hid = document.getElementById(`area_subtotal_hidden_${i}`);
      return hNum(hid?.value);
    }
    return m2 * precoM2;
  }

  function setSubtotalManual(i, subtotal){
    subtotal = Number(subtotal || 0);

    const hid = document.getElementById(`area_subtotal_hidden_${i}`);
    const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
    if (hid) hid.value = subtotal.toFixed(2);
    if (hidAtivo) hidAtivo.value = "1";

    const chk = document.getElementById(`area_subtotal_manual_chk_${i}`);
    const wrap = document.getElementById(`wrap_subtotal_manual_${i}`);
    const inp = document.getElementById(`area_subtotal_manual_inp_${i}`);
    if (chk) chk.checked = true;
    if (wrap) wrap.style.display = "block";
    if (inp) inp.value = subtotal ? subtotal.toFixed(2) : "";

    // preço/m² vira DERIVADO (readOnly)
    const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);
    const precoInput = document.getElementById(`area_preco_${i}`);
    const subSpan = document.getElementById(`subtotal_area_${i}`);

    if (subSpan) setMoney(subSpan, subtotal);

    if (precoInput){
      const pm2 = (m2 > 0) ? (subtotal / m2) : 0;
      precoInput.value = fmtPm2_6(pm2);
      precoInput.readOnly = true;

      // IMPORTANTE: não marcar dataset.manual aqui
      // (dataset.manual fica reservado para "preço/m² manual")
    }
  }


  function clearSubtotalManual(i){
    const hid = document.getElementById(`area_subtotal_hidden_${i}`);
    const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
    if (hid) hid.value = "";
    if (hidAtivo) hidAtivo.value = "0";

    const chk = document.getElementById(`area_subtotal_manual_chk_${i}`);
    const wrap = document.getElementById(`wrap_subtotal_manual_${i}`);
    if (chk) chk.checked = false;
    if (wrap) wrap.style.display = "none";

    const precoInput = document.getElementById(`area_preco_${i}`);
    if (precoInput) {
      // volta a editável (se for auto, atualizarArea recalcula; se for manual, mantém)
      precoInput.readOnly = false;
    }
  }


  function onToggleSubtotalManual(i){
    const chk = document.getElementById(`area_subtotal_manual_chk_${i}`);
    if (!chk) return;

    if (chk.checked) {
      const inp = document.getElementById(`area_subtotal_manual_inp_${i}`);
      const val = hNum(inp?.value);
      setSubtotalManual(i, val);
    } else {
      clearSubtotalManual(i);
      atualizarArea(i); // recalcula auto
    }

    atualizarResumo();
  }

  function onSubtotalManualChange(i){
    const inp = document.getElementById(`area_subtotal_manual_inp_${i}`);
    const val = hNum(inp?.value);
    setSubtotalManual(i, val);
    atualizarResumo();
  }

  function atualizarSubtotalManual(i) {
    const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value || 0);
    const precoInput = document.getElementById(`area_preco_${i}`);
    const subSpan = document.getElementById(`subtotal_area_${i}`);

    if (precoInput) precoInput.dataset.manual = "true";

    const p = parseFloat(precoInput?.value || 0);
    const subtotal = m2 * p;
    setMoney(subSpan, subtotal);
    const hid = document.getElementById(`area_subtotal_hidden_${i}`);
    const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
    if (hid) hid.value = (subtotal || 0).toFixed(2);
    if (hidAtivo) hidAtivo.value = "1";


    atualizarResumo();
  }

  function redefinirPrecoM2(i) {
    const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value || 0);
    const precoInput = document.getElementById(`area_preco_${i}`);
    const subSpan = document.getElementById(`subtotal_area_${i}`);

    const precoM2_raw = calcularPrecoPorMetroGlobal(getTotalM2Areas());
    const precoM2 = round2(precoM2_raw);

    if (precoInput) precoInput.value = (isFinite(precoM2) ? precoM2.toFixed(2) : '0.00');

    const subtotal = m2 * precoM2;
    if (subSpan) setMoney(subSpan, subtotal);

    if (precoInput) delete precoInput.dataset.manual;

    const hid = document.getElementById(`area_subtotal_hidden_${i}`);
    const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
    if (hid) hid.value = "";
    if (hidAtivo) hidAtivo.value = "0";

    atualizarResumo();
  }

  // ========= DESLOCAÇÃO & TOTAIS =========
  function calcularDeslocacao(){
    const distancia = hNum(document.getElementById('distancia')?.value);

    // total m²
    let totalM2 = 0;
    for (let i=0; i<state.areaCount; i++){
      totalM2 += hNum(document.getElementById(`area_m2_${i}`)?.value);
    }

    // dias (igual ao teu)
    let dias = 1;
    if (totalM2 > 0) dias = Math.min(Math.round((totalM2 / 4000)+0.5), 100);

    // componentes
    const tecnicoAlimDia = 130;
    const tecnicoAlimTotal = tecnicoAlimDia * dias;
    const distanciaTotal  = distancia * 0.4 * 2 * dias;

    // checkboxes
    const incTec  = !!document.getElementById("chk_tecnico_alim")?.checked;
    const incDist = !!document.getElementById("chk_distancia")?.checked;

    const totalDeslocacao =
      (incTec ? tecnicoAlimTotal : 0) +
      (incDist ? distanciaTotal : 0);

    return { totalDeslocacao, dias, tecnicoAlimDia, tecnicoAlimTotal, distanciaTotal, incTec, incDist };

  }

  function atualizarPreviewsPDFPorArea() {
    const IVA = 0.23;

    const d = calcularDeslocacao();
    const deslocPorArea = (state.areaCount > 0)
      ? (Number(d.totalDeslocacao || 0) / state.areaCount)
      : 0;

    // ===== PREVIEW POR ÁREA =====
    for (let i = 0; i < state.areaCount; i++) {
      const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);
      const precoM2 = hNum(document.getElementById(`area_preco_${i}`)?.value);
      const subtotal = getSubtotalArea(i);


      const preview = (subtotal + deslocPorArea) * (1 + IVA);

      const elPrev = document.getElementById(`pdf_prev_area_${i}`);
      if (elPrev) {
        elPrev.textContent =
          "PDF (c/ desloc. + IVA): " +
          formatCurrency(eurToSelectedCurrency(preview), window.__CUR.code) +
          (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(preview, "EUR")})` : "");
        elPrev.dataset.eur = String(round2(preview));
      }
    }

    // ===== PREVIEW TOPOGRÁFICO =====
    const topoPrev = document.getElementById("pdf_prev_topo");

    if (state.topoInputId && topoPrev) {
      const topoBase = hNum(document.getElementById(state.topoInputId)?.value);
      const topoFinal = topoBase * (state.topoMais10 ? 1.10 : 1);
      const previewTopo = topoFinal * (1 + IVA);


      topoPrev.textContent =
        "PDF (c/ desloc. + IVA): " +
        formatCurrency(eurToSelectedCurrency(previewTopo), window.__CUR.code) +
        (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(previewTopo, "EUR")})` : "");
      topoPrev.dataset.eur = String(round2(previewTopo));
    } else if (topoPrev) {
      topoPrev.textContent = "PDF (c/ desloc. + IVA): —";
      topoPrev.dataset.eur = "0";
    }
  }
        
  function atualizarResumo(){
    atualizarAjustesTotais();
  }

  function calcularPrecoDrone(m2){
      m2 = Number(m2||0);
      if (m2 <= 0) return 0;
      if (m2 <= 1000) return 250;
      if (m2 >= 50000) return 3000;

      const ratio = (m2 - 1000) / (50000 - 1000);
      return 250 + ratio * (3000 - 250);
  }
    const ID_ALT = 17;

  function calcularPrecoAltimetrico(m2){
    m2 = Number(m2 || 0);
    if (m2 <= 0) return 0;

    if (m2 <= 2000) return 400;
    if (m2 >= 10000) return 2500;

    const t = (m2 - 2000) / (10000 - 2000); // 0..1
    const curva = Math.pow(t, 0.72);        // sobe muito aos poucos no início
    return 400 + curva * (2500 - 400);
  }

  function totalM2Areas(){
    let total = 0;
    for(let i=0; i<state.areaCount; i++){
      total += hNum(document.getElementById(`area_m2_${i}`)?.value);
    }
    return total;
  }

  function hasDrone(){
    return Array.from(document.querySelectorAll('.servico-card'))
      .some(c => String(c.dataset.id) === "13" || c.querySelector('strong')?.textContent?.toLowerCase().includes("drone"));
  }

  function setAltimetricoDisponivel(){
    const optAlt = novoServico?.querySelector(`option[value="${ID_ALT}"]`);
    if (!optAlt) return;

    const ok = hasDrone();
    optAlt.disabled = !ok;

    // se drone saiu e alt ainda está selecionado → remove
    if (!ok && jaExisteCardPorId(String(ID_ALT))) {
      const cardAlt = servicosContainer.querySelector(`.servico-card[data-id="${ID_ALT}"]`);
      if (cardAlt) cardAlt.remove();
      // também libertar dropdown
      optAlt.disabled = true;
      atualizarAjustesTotais();
    }
  }


  function atualizarDronePrecoBase(){
      let totalM2 = 0;
      for(let i=0; i<state.areaCount; i++){
          totalM2 += hNum(document.getElementById(`area_m2_${i}`)?.value);
      }

      const precoBase = calcularPrecoDrone(totalM2);
      const out = document.getElementById("preco_drone_base");

      if (out) setMoney(out, precoBase);


      return precoBase;
  }


  function atualizarAjustesTotais(){
    // 1) TOTAL m² e preço/m² GLOBAL (modo automático)
    const totalM2 = getTotalM2Areas();
    const precoM2Global_raw = calcularPrecoPorMetroGlobal(totalM2);
    const precoM2Global = round2(precoM2Global_raw);


    // Atualiza texto no resumo
    const infoM2 = document.getElementById("laser_total_m2_info");
    if (infoM2) infoM2.textContent = `Total áreas: ${Math.round(totalM2)} m²`;

    // Recalcula subtotais com o preço global para áreas NÃO manuais
    let totalBase = 0;

    for (let i=0; i<state.areaCount; i++){
      const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);

      const precoInput = document.getElementById(`area_preco_${i}`);
      const subSpan = document.getElementById(`subtotal_area_${i}`);

      let subtotal = 0;

      if (isSubtotalManual(i)) {
        // SUBTOTAL MANUAL
        const hid = document.getElementById(`area_subtotal_hidden_${i}`);
        const subtotalFix = hNum(hid?.value);

        subtotal = subtotalFix;

        if (precoInput){
          const pm2 = (m2 > 0) ? (subtotalFix / m2) : 0;
          precoInput.value = fmtPm2_6(pm2);
          precoInput.readOnly = true;
          // não mexer no dataset.manual aqui
        }
      } else {
        // NÃO é subtotal manual → preço pode ser auto ou manual
        if (precoInput) precoInput.readOnly = false;

        let pm2 = 0;

        if (precoInput?.dataset.manual) {
          // PREÇO/M² MANUAL
          pm2 = hNum(precoInput.value);
        } else {
          // AUTO
          pm2 = round2(precoM2Global_raw);
          if (precoInput) precoInput.value = (isFinite(pm2) ? pm2.toFixed(2) : "0.00");
        }

        subtotal = m2 * pm2;
      }

      if (subSpan) setMoney(subSpan, subtotal);
      totalBase += subtotal;
    }


    let totalLaserSemTopo = totalBase;


    // 2) Ajustes (LOD, Modelo 3D, BIM)
    document.querySelectorAll('select.select-opcao').forEach(sel => {
      const tipo = sel.dataset.tipo;
      const val  = sel.value;
      if (!val) return;
      if (tipo === 'niveldedetalhe' && ajustesNivelDetalhe[val] !== undefined)
        totalLaserSemTopo += totalBase * (ajustesNivelDetalhe[val]/100);
      if (tipo === 'modelo3dniveldedetalhe' && ajustesModelo3D[val] !== undefined)
        totalLaserSemTopo += totalBase * (ajustesModelo3D[val]/100);
      if (tipo === 'bim' && ajustesBIM[val] !== undefined)
        totalLaserSemTopo += totalBase * (ajustesBIM[val]/100);
    });

    // 3) Georreferenciação
    const temGeo = Array.from(document.querySelectorAll('.servico-card strong'))
      .some(el => normalizarTexto(el.textContent)==='georreferenciacao');
    if (temGeo){
      if (totalBase <= 1000) totalLaserSemTopo += 200;
      else totalLaserSemTopo += 200 + (Math.floor(totalBase/1000) * 50);
    }

    // 4) Topográfico (+10%), fora do Laser
    let topoTotal = 0;
    if (state.topoInputId){
      const topoRaw = hNum(document.getElementById(state.topoInputId)?.value);
      topoTotal = topoRaw * (state.topoMais10 ? 1.10 : 1);

      const lt = document.getElementById('linha_topografico');
      if (lt){
        lt.style.display = '';
        const tdLabel = lt.querySelector("td:first-child");
        if (tdLabel) tdLabel.textContent = state.topoMais10 ? "Levantamento Topográfico (+10%)" : "Levantamento Topográfico";

        const cell = document.getElementById('preco_total_topo');
        if (cell) setMoney(cell, topoTotal);

      } 
    } else {
      const lt = document.getElementById('linha_topografico');
      if (lt) lt.style.display = 'none';
    }

    // 4b) Drone — fora do Laser Scan
    let totalDrone = 0;

    const existeDrone = Array.from(document.querySelectorAll('.servico-card strong'))
        .some(el => el.textContent.toLowerCase().includes("drone"));

        if (existeDrone) {

          const base = atualizarDronePrecoBase();
          let extras = 0;

          document.querySelectorAll('.chk-drone:checked').forEach(chk => {
              extras += hNum(chk.dataset.preco);
          });

          // ✅ Altimétrico (ID 17) acrescenta ao Drone
          let extraAlt = 0;
          if (jaExisteCardPorId(String(ID_ALT))) {
            const m2 = totalM2Areas();
            extraAlt = calcularPrecoAltimetrico(m2);
          }

          totalDrone = base + extras + extraAlt;

          state.totals.drone = totalDrone;

          let linhaDrone = document.getElementById("linha_drone");
          if (linhaDrone) {
              linhaDrone.style.display = "";
              setMoney(document.getElementById("preco_total_drone"), totalDrone);

          }

        } else {
            state.totals.drone = 0;

            const ld = document.getElementById("linha_drone");
            if (ld) ld.style.display = "none";
        }


    state.totals.drone = totalDrone;

    // =============================
    // RENDERIZAÇÕES (SERVIÇO ID 16)
    // =============================
    let totalRender = 0;
    const temRender = !!document.querySelector('.servico-card[data-id="16"]');

    if (temRender) {
      for (let i = 0; i < state.areaCount; i++) {
        const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);
        const isExterior = !!document.getElementById(`area_ext_${i}`)?.checked;

        if (m2 <= 0) continue;

        if (isExterior) totalRender += m2 * 2;  // 2€/m² exterior
        else totalRender += 400;                // 400€ por área interior
      }
    }

    // Mostrar/ocultar linha no resumo
    const linhaRender = document.getElementById("linha_render");
    if (linhaRender) {
      if (temRender && totalRender > 0) {
        linhaRender.style.display = "";
        setMoney(document.getElementById("preco_total_render"), totalRender);

      } else {
        linhaRender.style.display = "none";
        const out = document.getElementById("preco_total_render");
        if (out) setMoney(out, 0);

      }
    }
    document.getElementById("input_total_render").value = (totalRender || 0);

    state.totals.render = totalRender;
    // ✅ IGUAL AO CRIAR: Render entra no Laser (não fica separado)
    totalLaserSemTopo += totalRender;




    

    
    

    // Resumo
    const d = calcularDeslocacao();
    state.totals.deslocacao = d.totalDeslocacao;

    // Atualiza UI do bloco deslocação
    setMoney(document.getElementById("valor_tecnico_alim"), d.tecnicoAlimTotal);

    setMoney(document.getElementById("valor_distancia"), d.distanciaTotal);

    const diasLbl = document.getElementById("deslocacao_dias_lbl");
    if (diasLbl) diasLbl.textContent = String(d.dias);

    // hiddens
    document.getElementById("inclui_tecnico_alimentacao").value = d.incTec ? "1" : "0";
    document.getElementById("inclui_distancia").value = d.incDist ? "1" : "0";
    document.getElementById("preco_tecnico_alimentacao").value = String(round2(d.tecnicoAlimTotal));

    document.getElementById("preco_distancia").value = String(round2(d.distanciaTotal));
    document.getElementById("preco_deslocacao_total").value = String(round2(d.totalDeslocacao));

    // Linha deslocação (sempre existe)
    ensureLinhaDeslocacao();
    setMoney(document.getElementById('preco_total_deslocacao'), d.totalDeslocacao);

    const linhaDesloc = document.getElementById('linha_deslocacao');
    if (linhaDesloc) {
      const td = linhaDesloc.querySelector('td:first-child');
      if (td) td.textContent = `Deslocação (${d.dias} dia${d.dias>1?'s':''})`;
    }

    // ✅ Agora sim: calcula e escreve Laser
    state.totals.laser = totalLaserSemTopo + d.totalDeslocacao;
    state.totals.topo  = topoTotal;

    setMoney(document.getElementById('preco_total_laser'), state.totals.laser);

    // hiddens finais
    document.getElementById("input_preco_total_laser").value = (state.totals.laser || 0);
    document.getElementById("input_preco_topografico").value = (state.totals.topo || 0);
    document.getElementById("input_preco_total_drone").value = (state.totals.drone || 0);

    atualizarTotaisFinais();
    atualizarPreviewsPDFPorArea();


  }
  function onPrecoM2Change(i){
    const m2Input   = document.getElementById(`area_m2_${i}`);
    const precoInput= document.getElementById(`area_preco_${i}`);
    const subSpan   = document.getElementById(`subtotal_area_${i}`);
    const hidAtivo  = document.getElementById(`area_subtotal_ativo_${i}`);
    const hidSub    = document.getElementById(`area_subtotal_hidden_${i}`);

    if (!m2Input || !precoInput || !subSpan) return;

    // Se estava em subtotal manual, ao mexer no preço/m² nós saímos desse modo (igual ao criar)
    if (hidAtivo) hidAtivo.value = "0";
    if (hidSub) hidSub.value = "";

    // Marca como "preço/m² manual"
    precoInput.dataset.manual = "true";
    precoInput.readOnly = false;

    const m2 = hNum(m2Input.value);
    const pm2 = hNum(precoInput.value);

    setMoney(subSpan, m2 * pm2);
    atualizarResumo();
  }


  function atualizarTotaisFinais(){
      const descontoPercent = parseFloat(document.getElementById('desconto_global')?.value) || 0;


    const totalLaser = Number(state?.totals?.laser || document.getElementById("input_preco_total_laser")?.value || 0);
    const totalTopo  = Number(state?.totals?.topo  || document.getElementById("input_preco_topografico")?.value || 0);
    const totalDrone = Number(state?.totals?.drone || document.getElementById("input_preco_total_drone")?.value || 0);

    const totalBruto = totalLaser + totalTopo + totalDrone; // ✅ igual ao criar




    const valorDesconto = totalBruto * (descontoPercent/100);
    const totalComDesconto = totalBruto - valorDesconto;
    const totalSemIVA = totalComDesconto;
    const deslocacao = Number(state?.totals?.deslocacao || 0);
    const custosExtra = getCustosExtra();

    // base para sub-custos (nunca negativa)
    let baseSub = totalSemIVA - custosExtra - deslocacao;
    if (baseSub < 0) baseSub = 0;

    const subCustos = (baseSub * 0.25) + 80;

    // tempo em dias inteiro (arredondamento normal)
    const tempoDias = Math.round(subCustos / 40);

    // margem liberta
    const margem = totalSemIVA - (subCustos + custosExtra) - deslocacao;

    // escrever no UI
    const pvTotal = document.getElementById('pv_total_sem_iva');
    const pvDesl  = document.getElementById('pv_deslocacao');
    const pvSub   = document.getElementById('pv_sub_custos');
    const pvTempo = document.getElementById('pv_tempo_dias');
    const pvMarg  = document.getElementById('pv_margem');

    if (pvTotal) pvTotal.textContent = euro(totalSemIVA);
    if (pvDesl)  pvDesl.textContent  = euro(deslocacao);
    if (pvSub)   pvSub.textContent   = euro(subCustos);
    if (pvTempo) pvTempo.textContent = String(tempoDias);
    if (pvMarg)  pvMarg.textContent  = euro(margem);

    const totalIVA = totalComDesconto * 0.23;
    const totalFinal = totalComDesconto + totalIVA;

    setMoney(document.getElementById('total_bruto'), totalBruto);
    setMoney(document.getElementById('total_com_desconto'), totalComDesconto);
    setMoney(document.getElementById('total_iva'), totalIVA);
    setMoney(document.getElementById('total_final'), totalFinal);

  }
  document.getElementById('desconto_global')?.addEventListener('input', () => {
    atualizarTotaisFinais(); // usa os valores guardados
  });

  function syncToggleUI(i){
  const chk = document.getElementById(`area_ext_${i}`);
  const wrap = document.getElementById(`area_toggle_wrap_${i}`);
  const badge = document.getElementById(`area_badge_${i}`);
  if (!chk || !wrap || !badge) return;

  const isExt = !!chk.checked;
  wrap.classList.toggle('is-exterior', isExt);
  badge.textContent = isExt ? "EXTERIOR" : "INTERIOR";
}

function onToggleExterior(i){
  syncToggleUI(i);
  atualizarResumo(); // recalcula (render, laser, etc.)
}


  // ========= PRELOAD DA PÁGINA =========
  window.addEventListener('DOMContentLoaded', () => {
    ensureLinhaDeslocacao();

    // Serviços antigos
    SERVICOS_OLD.forEach(s => {
      const base = SERVICOS_BASE.find(x => String(x.id) === String(s.id_servico));
      if (!base) return;

      const nomeNorm = normalizarTexto(s.nome_servico || base.nome);
      
      if (nomeNorm === "levantamentotopografico") {

          // valor final salvo na BD (com +10%)
          const precoTopoBD = <?= floatval($proposta['preco_levantamento_topografico'] ?? 0) ?>;

          let precoBase = "";
          if (precoTopoBD > 0) {
            // se na BD estava com +10%, então remove
            precoBase = (TOPO_MAIS10_DB ? (precoTopoBD / 1.10) : precoTopoBD).toFixed(2);
          }


          adicionarServico(base, "", precoBase);
          return;
      }


      // ===== DRONE =====
      if (nomeNorm === "levantamentodronefotos") {

          adicionarServico(base);

          // aplicar extras guardados na base de dados
          setTimeout(() => {

            let selecionadas = [];

            try {
                if (s.opcao_escolhida) {
                    const json = JSON.parse(s.opcao_escolhida);
                    if (Array.isArray(json)) selecionadas = json;
                }
            } catch (e) {
                console.warn("Erro ao ler JSON do drone:", e);
            }

            // Ativa as checkboxes corretas
            document.querySelectorAll(".chk-drone").forEach(chk => {
                const label = chk.parentElement.textContent.trim().toLowerCase();
                chk.checked = selecionadas.some(opt => label.includes(opt.toLowerCase()));
            });

            atualizarDronePrecoBase();
            atualizarAjustesTotais();
          }, 120);


          return;
      }

      // ===== OUTROS SERVIÇOS (LOD, BIM, Modelo 3D) =====
      adicionarServico(base, s.opcao_escolhida || "");

      

    });

    setTimeout(() => {
      const ce = document.getElementById('custos_extra');
      if (!ce) return;

      // se já veio preenchido da BD, marca como manual para não ser atropelado
      if (hNum(ce.value) > 0) {
        state.custosExtraManual = true;
      }

      atualizarAjustesTotais();
    }, 250);


    // Áreas antigas (preserva preço/m2)
    // --- detetar qual era o preço auto na proposta antiga ---
    const totalM2Old = AREAS_OLD.reduce((acc, a) => acc + hNum(a.metros_quadrados), 0);
    const precoAutoOld = round2(calcularPrecoPorMetroGlobal(totalM2Old));

    AREAS_OLD.forEach(a => {
      const m2BD = hNum(a.metros_quadrados);
      const precoBD = round2(hNum(a.preco_m2));
      const subtotalBD = round2(hNum(a.subtotal));

      const subtotalCalc = round2(m2BD * precoBD);

      const eraSubtotalManual = (m2BD > 0 && Math.abs(subtotalBD - subtotalCalc) > 0.05);
      const isPrecoManual = (!eraSubtotalManual && precoBD > 0 && Math.abs(precoBD - precoAutoOld) > 0.01);

      const exterior = Number(a.exterior || 0); // ✅ FIX

      adicionarArea(
        a.nome_area || "",
        a.metros_quadrados || "",
        a.preco_m2 || "",
        euro(subtotalBD),
        exterior,
        eraSubtotalManual,  // ✅ só isto liga subtotal manual
        isPrecoManual       // ✅ isto marca preço/m² como manual
      );

      if (eraSubtotalManual) {
        setTimeout(() => setSubtotalManual(state.areaCount - 1, subtotalBD), 0);
      }
    });



    if (AREAS_OLD.length === 0) adicionarArea();

    // listeners extra
    document.addEventListener('change', e => {
      if (e.target && e.target.classList.contains('select-opcao')) atualizarAjustesTotais();
    });

    // Atualizar automaticamente ao alterar a distância
    const distanciaInput = document.getElementById('distancia');
    if (distanciaInput) {
      distanciaInput.addEventListener('input', () => {
        window.RN.atualizarAjustesTotais();
      });
    }
    // só depois de services/areas carregados
    setAltimetricoDisponivel();

    atualizarResumo();
    initCurrencyUI();
    setAltimetricoDisponivel();
    atualizarAjustesTotais();
    refreshAllMoney();
  });

  setTimeout(() => {
    atualizarResumo();
    atualizarAjustesTotais();
    refreshAllMoney();
  }, 200);
  document.getElementById("chk_tecnico_alim")?.addEventListener("change", atualizarResumo);
  document.getElementById("chk_distancia")?.addEventListener("change", atualizarResumo);
  document.getElementById("distancia")?.addEventListener("input", atualizarResumo);

  // ========= EXPOSTO GLOBAL (para handlers inline do HTML) =========
  window.atualizarResumo = atualizarResumo;
  window.atualizarAjustesTotais = atualizarAjustesTotais;
  window.atualizarTotaisFinais = atualizarTotaisFinais;


  // ========= EXPOSTO PARA ATRIBUTOS INLINE =========
  window.RN = {
    removerServico,
    atualizarAjustesTotais,
    atualizarSubtotalManual,
    atualizarArea,
    redefinirPrecoM2,
    removerArea,
    atualizarResumo,
    onToggleExterior, // ✅ novo
    onToggleSubtotalManual,
    onPrecoM2Change,
    onSubtotalManualChange

  };
  window.RN.onCustosExtraManual = onCustosExtraManual;


})();
</script>



<?php include './rodape.php'; ?>
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
  async function fetchEmpresaPorEmail(email) {
    email = (email || "").trim();
    if (!email) return null;

    try {
      const res = await fetch(`get_cliente_empresa.php?email=${encodeURIComponent(email)}`, {
        headers: { "Accept": "application/json" }
      });

      if (!res.ok) return null;
      return await res.json();
    } catch (e) {
      console.warn("Erro a procurar empresa do cliente:", e);
      return null;
    }
  }

  function fillEmpresaFields(data) {
    const inpEmpresa = document.getElementById("empresa_nome");
    const inpNif = document.getElementById("empresa_nif");
    if (!inpEmpresa || !inpNif) return;

    // Só preenche se vier valor do backend
    if (data?.empresa_nome) inpEmpresa.value = data.empresa_nome;
    if (data?.empresa_nif) inpNif.value = data.empresa_nif;
  }

  // Quando muda o email → tenta buscar e preencher
  const emailInput = document.querySelector('input[name="email_cliente"]');
  if (emailInput) {
    let t = null;

    const onChangeEmail = () => {
      clearTimeout(t);
      t = setTimeout(async () => {
        const data = await fetchEmpresaPorEmail(emailInput.value);

        // Se existir cliente no sistema, preenche.
        // Se não existir, não mexe (fica editável e podes escrever).
        if (data?.found) fillEmpresaFields(data);
      }, 300);
    };

    emailInput.addEventListener("input", onChangeEmail);
    emailInput.addEventListener("blur", onChangeEmail);

    // opcional: ao carregar a página, tenta “confirmar” dados pelo email (sem estragar o que já está preenchido)
    window.addEventListener("DOMContentLoaded", onChangeEmail);
  }

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

