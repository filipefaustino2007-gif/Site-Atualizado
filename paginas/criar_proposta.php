<?php
include 'protecao.php';
require_once __DIR__ . '/../conexao/conexao.php';
require_once __DIR__ . '/../conexao/funcoes.php';
include 'cabecalho.php';

$edit_id   = $_GET['id'] ?? ($_GET['edit_id'] ?? null);
$parent_id = $_GET['parent'] ?? null;

$proposta_antiga = null;
$areas_antigas = [];
$servicos_antigos = [];
$opcoes_drone_antigas = [];

$base_id = $edit_id ?: $parent_id;

if ($base_id) {
    $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
    $stmt->execute([$base_id]);
    $proposta_antiga = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($proposta_antiga) {
        $stA = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
        $stA->execute([$base_id]);
        $areas_antigas = $stA->fetchAll(PDO::FETCH_ASSOC);

        $stS = $pdo->prepare("
          SELECT sp.*, s.nome AS nome_servico, s.tipo_opcao
          FROM servicos_proposta sp
          LEFT JOIN servicos_produtos s ON sp.id_servico = s.id
          WHERE sp.id_proposta = ?
          ORDER BY sp.id_servico ASC
        ");
        $stS->execute([$base_id]);
        $servicos_antigos = $stS->fetchAll(PDO::FETCH_ASSOC);

        // Drone options (guardar array)
        $opcoes_drone_antigas = [];
        foreach ($servicos_antigos as $sv) {
            // deteta por ID (melhor) ou por nome
            $isDrone = (int)($sv['id_servico'] ?? 0) === 13
                      || stripos(($sv['nome_servico'] ?? ''), 'drone') !== false;

            if ($isDrone && !empty($sv['opcao_escolhida'])) {
                $tmp = json_decode($sv['opcao_escolhida'], true);
                if (is_array($tmp)) $opcoes_drone_antigas = $tmp;
            }
        }

    }
}



// Buscar todos os serviços da base de dados
$query = "SELECT id, nome, tipo_opcao FROM servicos_produtos ORDER BY nome ASC";
$result = $conn->query($query);
$servicos = [];
while ($row = $result->fetch_assoc()) {
    $servicos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Criar Proposta - SupremExpansion</title>
<link rel="stylesheet" href="../css/criar_proposta.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>

<body>
  <br><br><br><br><br>
<div class="container">
    <h1>Criar Nova Proposta 
</h1>
<a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>
    

    <form id="formProposta" action="salvar_proposta.php" method="POST" enctype="multipart/form-data" >
        <label>Número da Proposta (opcional):</label>
        <input type="text" name="numero_proposta_manual" placeholder="Ex: 2025/123">

        <label>Cliente:</label>
        <input type="text" name="nome_cliente" required value="<?= htmlspecialchars($proposta_antiga['nome_cliente'] ?? '') ?>">

        <label>Email do Cliente:</label>
        <input type="text" name="email_cliente" required value="<?= htmlspecialchars($proposta_antiga['email_cliente'] ?? '') ?>">

        <label>Telefone do Cliente:</label>
        <input type="text" name="telefone_cliente" value="<?= htmlspecialchars($proposta_antiga['telefone_cliente'] ?? '') ?>">

        <label>Empresa:</label>
        <input type="text" name="empresa_nome" id="empresa_nome" placeholder="Ex: Empresa Lda" value="<?= htmlspecialchars($proposta_antiga['empresa_nome'] ?? '') ?>">

        <label>NIF da Empresa:</label>
        <input type="text" name="empresa_nif" id="empresa_nif" placeholder="Ex: 123456789" maxlength="20" value="<?= htmlspecialchars($proposta_antiga['empresa_nif'] ?? '') ?>">

        <div id="clienteHint" style="display:none; margin:10px 0; padding:10px 12px; border-radius:12px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-weight:700; display:none; gap:8px; align-items:center;">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span id="clienteHintTxt">Cliente não encontrado. Preenche Empresa e NIF para guardar na ficha.</span>
        </div>


        <label>Nome da Obra:</label>
        <input type="text" name="nome_obra" required value="<?= htmlspecialchars($proposta_antiga['nome_obra'] ?? '') ?>">

        <label>Endereço da Obra:</label>
        <input type="text" name="endereco_obra" required value="<?= htmlspecialchars($proposta_antiga['endereco_obra'] ?? '') ?>">

        <label>Distância (km só ida):</label>
        <input type="number" id="distancia" name="distancia" min="0" step="1" required oninput="atualizarResumo()" value="<?= htmlspecialchars($proposta_antiga['distancia_km'] ? ($proposta_antiga['distancia_km']/2) : 0) ?>">

        <!-- Deslocação: componentes (fixo + distância) -->
        <div id="deslocacaoBox" style="margin-top:10px; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa;">
          <div style="font-weight:800; margin-bottom:8px;">Deslocação</div>

          <label style="display:flex; align-items:center; gap:10px; margin:8px 0;">
            <input type="checkbox" id="chk_tecnico_alim" checked onchange="atualizarResumo()">
            <span style="flex:1;">Adicionar Técnico + Alimentação (fixo)</span>
            <span id="valor_tecnico_alim" data-eur="130" style="font-weight:800;">130,00 €</span>
          </label>

          <label style="display:flex; align-items:center; gap:10px; margin:8px 0;">
            <input type="checkbox" id="chk_distancia" checked onchange="atualizarResumo()">
            <span style="flex:1;">Adicionar Distância (km × 0,40 × 2 × dias)</span>
            <span id="valor_distancia" data-eur="0" style="font-weight:800;">0,00 €</span>
          </label>

          <div style="font-size:12px; color:#666; margin-top:6px;">
            Dias estimados: <span id="deslocacao_dias_lbl">1</span>
          </div>

          <!-- hiddens para o PHP -->
          <input type="hidden" name="inclui_tecnico_alimentacao" id="inclui_tecnico_alimentacao" value="1">
          <input type="hidden" name="inclui_distancia" id="inclui_distancia" value="1">
          <input type="hidden" name="preco_tecnico_alimentacao" id="preco_tecnico_alimentacao" value="130">
          <input type="hidden" name="preco_distancia" id="preco_distancia" value="0">
          <input type="hidden" name="preco_deslocacao_total" id="preco_deslocacao_total" value="130">
        </div>


        <label>Moeda:</label>
        <select id="moedaSelect" name="codigo_pais" required>
          <?php $ccy = $proposta_antiga['codigo_pais'] ?? 'EUR'; ?>
          <option value="">— Selecionar —</option>
          <option value="EUR" <?= $ccy==='EUR'?'selected':'' ?>>Euro</option>
          <option value="GBP" <?= $ccy==='GBP'?'selected':'' ?>>Libra</option>
          <option value="JPY" <?= $ccy==='JPY'?'selected':'' ?>>Iene</option>
          <option value="USD" <?= $ccy==='USD'?'selected':'' ?>>Dólar</option>
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

            <div style="font-size:12px; color:#666;">
              (os valores são mostrados na moeda selecionada, e por baixo aparece a equivalência em €)
            </div>
          </div>
        </div>

        <br>
        <br>
        <hr>
        <br>
        <br>
        <h2>Serviços</h2>
        <br>
        <div id="servicosContainer"></div>

        <select id="novoServico">
            <option value="">— Selecionar novo serviço —</option>
            <?php foreach($servicos as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <br>
        <button type="button" class="btn" id="addServicoBtn">Adicionar Serviço</button>

        <div class="area-section" id="areaSection">
            <h2>Áreas da Obra</h2>
            <br>
            <div id="areasContainer"></div>
            <button type="button" class="btn" id="addAreaBtn">Adicionar Área</button>
        </div>
        <br>

        <div class="upload-wrapper" onclick="document.getElementById('imagem').click();">
        <label for="imagem">
          <i class="fa-solid fa-image"></i> Carregar Imagem da Proposta
        </label>
        <input type="file" name="imagem" id="imagem" accept="image/*" onchange="previewImagem(event)">
        <div id="preview" class="upload-preview" style="display:none;">
          <img id="preview-img" src="#" alt="Pré-visualização">
        </div>
      </div>

      <script>  
      function previewImagem(event) {
        const input = event.target;
        const preview = document.getElementById('preview');
        const img = document.getElementById('preview-img');
        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = e => {
            img.src = e.target.result;
            preview.style.display = 'block';
          };
          reader.readAsDataURL(input.files[0]);
        }
      }
      </script>

              

        <hr>

        <div id="resumoPrecos" style="margin-top: 40px;">
            <h2>Resumo de Preços</h2>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                <tr style="border-bottom:2px solid #a30101;">
                    <th style="text-align:left; padding:8px;">Serviço</th>
                    <th style="text-align:right; padding:8px;">
                      Preço Total (<span id="moedaHeaderLabel">€</span>)
                    </th>

                </tr>
                </thead>
                <tbody id="tabelaResumoBody">
                <tr>
                    <td style="padding:8px;">
                      Levantamento Laser Scan
                      <div id="laser_total_m2_info" style="font-size:12px; color:#666; margin-top:4px;">
                        Total áreas: 0 m²
                      </div>
                    </td>

                    <td id="preco_total_laser" data-eur="0" style="padding:8px; text-align:right;">0.00 €</td>
                    <input type="hidden" name="preco_total_laser" id="input_preco_total_laser">

                </tr>
                <tr id="linha_topografico" style="display:none;">
                    <td style="padding:8px;">Levantamento Topográfico (+10%)</td>
                    <td id="preco_total_topo"  data-eur="0" style="padding:8px; text-align:right;">0.00 €</td>
                    <input type="hidden" name="preco_topografico" id="input_preco_topografico">



                </tr>
                <tr id="linha_drone" style="display:none;">
                    <td style="padding:8px;">Levantamento Drone </td>
                    <td id="preco_total_drone" data-eur="0" style="padding:8px; text-align:right;">0.00 €</td>
                    <input type="hidden" name="preco_total_drone" id="input_preco_total_drone">
                    <input type="hidden" name="opcao_nivel_detalhe" id="input_opcao_nivel_detalhe">
                    <input type="hidden" name="opcao_modelo3d_nivel" id="input_opcao_modelo3d_nivel">
                    <input type="hidden" name="opcao_bim" id="input_opcao_bim">
                </tr>
                <tr id="linha_render" style="display:none;">
                  <td style="padding:8px;">Renderizações</td>
                  <td id="preco_total_render" data-eur="0" style="padding:8px; text-align:right;">0.00 €</td>
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
          value="0"
          oninput="window.RN?.onCustosExtraManual?.()"
        />
        <div class="muted">
          • Por defeito, preenche com o valor do Topográfico (s/ IVA).  
          • Se alterares manualmente aqui, deixa de seguir o Topográfico.
        </div>

        <div class="preview-grid" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; margin-top:12px;">
        
          <div class="preview-box" style="background:#fafafa; padding:10px; border-radius:10px;">
            <div class="muted">Custos de produção subcontratada</div>
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


        <div id="tabelaTotais" class="totais-box">
            <h2>TOTAIS</h2>

            <table class="totais-tabela">
                <tr>
                    <td>Total Bruto</td>
                    <td id="total_bruto" data-eur="0">0.00 €</td>
                </tr>

                <tr>
                    <td>Desconto Global</td>
                    <td>
                        <div class="desconto-wrapper">
                            <input type="number" id="desconto_global" name="desconto_percentagem"
                                  min="0" max="100" value="0"
                                  oninput="atualizarTotaisFinais()"
                                  style="border: none; background-color: #fafafa; width: 60px;" value="<?= (int)($proposta_antiga['desconto_percentagem'] ?? 0) ?>">
                            <span style="height: 23px;">%</span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td>Total c/ Desconto</td>
                    <td id="total_com_desconto" data-eur="0">0.00 €</td>
                </tr>

                <tr>
                    <td>IVA (23%)</td>
                    <td id="total_iva" data-eur="0">0.00 €</td>
                </tr>

                <tr class="total-final">
                    <td>Total Final</td>
                    <td id="total_final" data-eur="0">0.00 €</td>
                </tr>
            </table>
        </div>

        <hr>

        <h2>PDF</h2>

        <label style="display:flex; align-items:center; gap:10px; margin:10px 0;">
          <input type="checkbox" id="chk_incluir_lod_imgs" checked onchange="syncLodImgsHidden()">
          <span>Deseja adicionar as imagens do nível de detalhe (LOD) no PDF?</span>
        </label>

        <input type="hidden" name="incluir_lod_imgs" id="incluir_lod_imgs" value="1">
        <?php if (!empty($edit_id)): ?>
          <input type="hidden" name="edit_id" value="<?= (int)$edit_id ?>">
        <?php endif; ?>

        <?php if (!empty($parent_id) && empty($edit_id)): ?>
          <input type="hidden" name="parent_id" value="<?= (int)$parent_id ?>">
        <?php endif; ?>

        <script>
        function syncLodImgsHidden(){
          const chk = document.getElementById('chk_incluir_lod_imgs');
          const hid = document.getElementById('incluir_lod_imgs');
          if (!chk || !hid) return;
          hid.value = chk.checked ? "1" : "0";
        }
        document.addEventListener("DOMContentLoaded", syncLodImgsHidden);
        </script>


        <br>
        <button type="submit" class="btn">Salvar Proposta</button>
    </form>
</div>
<?php
  include './rodape.php';
?>
<script>
window.__EDIT__ = {
  proposta: <?= json_encode($proposta_antiga ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>,
  areas: <?= json_encode($areas_antigas, JSON_UNESCAPED_UNICODE) ?>,
  servicos: <?= json_encode($servicos_antigos, JSON_UNESCAPED_UNICODE) ?>,
  opcoes_drone: <?= json_encode($opcoes_drone_antigas, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<script>

const IS_EDIT = !!(window.__EDIT__ && window.__EDIT__.proposta && window.__EDIT__.proposta.id);
let HYDRATING = false;

const servicos = <?php echo json_encode($servicos); ?>;
const servicosContainer = document.getElementById('servicosContainer');
const novoServico = document.getElementById('novoServico');
const addServicoBtn = document.getElementById('addServicoBtn');
const areaSection = document.getElementById('areaSection');
const areasContainer = document.getElementById('areasContainer');


const servicosComAreas = [
  'Levantamento Arquitetónico',
  'Plantas',
  'Cortes',
  'Alçados',
  'Modelo 3D',
  'Nuvem de Pontos',
  'Vista Virtual 3D 360°'
];
function setOpcaoDropdown(id, disabled = true) {
  const opt = novoServico.querySelector(`option[value="${id}"]`);
  if (opt) opt.disabled = disabled;
}

function jaExisteCard(id) {
  return !!servicosContainer.querySelector(`.servico-card[data-id="${id}"]`);
}

// === NORMALIZA TEXTO ===
function normalizarTexto(texto) {
  return texto
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/gi, "")
    .toLowerCase();
}

// =============================
// BIM: ao selecionar, remove serviços redundantes
// =============================
const BIM_REMOVE_SERVICOS = [
  "Levantamento Arquitetónico",
  "Plantas",
  "Cortes",
  "Alçados",
  "Modelo 3D - Nível de Detalhe"
].map(normalizarTexto);

// encontra e remove cards cujo <strong> bate no nome (normalizado)
function removerServicoPorNome(nomeAlvoNorm) {
  const cards = Array.from(servicosContainer.querySelectorAll(".servico-card"));
  for (const card of cards) {
    const strong = card.querySelector("strong");
    if (!strong) continue;

    const nomeCardNorm = normalizarTexto(strong.textContent || "");
    if (nomeCardNorm === nomeAlvoNorm) {
      const btnRemover = card.querySelector(".btn-remove-servico");
      if (btnRemover) removerServico(btnRemover);
      else {
        // fallback (se por algum motivo não tiver botão)
        const id = card.getAttribute("data-id");
        const opt = novoServico.querySelector(`option[value="${id}"]`);
        if (opt) opt.disabled = false;
        card.remove();
      }
    }
  }
}

function aplicarRegraBIM() {
  // BIM está ativo?
  const temBIM = Array.from(servicosContainer.querySelectorAll(".servico-card strong"))
    .some(el => normalizarTexto(el.textContent || "") === "bim");

  if (!temBIM) return;

  // remove os redundantes
  BIM_REMOVE_SERVICOS.forEach(removerServicoPorNome);

  // recheck + recalcular (porque áreas e totais podem mudar)
  checkAreas();
  atualizarAjustesTotais();
  atualizarLodPorBIM();

}
// =============================
// BIM ↔ LOD imagens no PDF
// =============================
const lodState = { prevChecked: null };

function setLodImgsEnabled(enabled) {
  const chk = document.getElementById("chk_incluir_lod_imgs");
  const hid = document.getElementById("incluir_lod_imgs");
  if (!chk || !hid) return;

  if (!enabled) {
    // guarda o estado anterior uma vez
    if (lodState.prevChecked === null) lodState.prevChecked = chk.checked;

    // desmarca + desativa + força hidden=0
    chk.checked = false;
    chk.disabled = true;
    hid.value = "0";

    // (opcional) feedback visual
    chk.title = "Desativado porque BIM está selecionado";
  } else {
    // reativa
    chk.disabled = false;

    // restaura o estado anterior (se existir)
    if (lodState.prevChecked !== null) {
      chk.checked = lodState.prevChecked;
      lodState.prevChecked = null;
    }

    // sincroniza hidden com o estado atual
    if (typeof syncLodImgsHidden === "function") syncLodImgsHidden();
    chk.title = "";
  }
}

function atualizarLodPorBIM() {
  const temBIM = Array.from(servicosContainer.querySelectorAll(".servico-card strong"))
    .some(el => normalizarTexto(el.textContent || "") === "bim");

  setLodImgsEnabled(!temBIM);
}



const opcoesServicos = {
  "niveldedetalhe": ["1:200", "1:100", "1:50", "1:20", "1:1"],
  "modelo3dniveldedetalhe": ["1:200", "1:100", "1:50", "1:20"],
  "bim": ["Bricscad", "Archicad", "Revit"],
  "levantamentodrone": [] // importante manter aqui para detetar no JS
};

let servicoCount = 0;
let areaCount = 0;
// =============================
// LEVANTAMENTO ALTIMÉTRICO (ID 17) — DEPENDE DO DRONE
// =============================
const ID_ALTIMETRICO = 17;

function existeServicoPorNomeNorm(nomeNorm) {
  return Array.from(document.querySelectorAll('.servico-card strong'))
    .some(el => normalizarTexto(el.textContent).includes(nomeNorm));
}

function existeDrone() {
  return existeServicoPorNomeNorm("levantamentodronefotos") || existeServicoPorNomeNorm("drone");
}

function existeAltimetrico() {
  return !!servicosContainer.querySelector(`.servico-card[data-id="${ID_ALTIMETRICO}"]`);
}

function totalM2Areas() {
  let total = 0;
  for (let i = 0; i < areaCount; i++) {
    total += parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
  }
  return total;
}
function getTotalM2Areas() {
  let totalM2 = 0;
  for (let i = 0; i < areaCount; i++) {
    totalM2 += parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
  }
  return totalM2;
}

// preço/m² GLOBAL (igual para todas as áreas em modo automático)
function calcularPrecoPorMetroGlobal(totalM2) {
  totalM2 = Number(totalM2 || 0);
  if (totalM2 <= 0) return 0;
  if (totalM2 <= 200) return 600 / totalM2; // garante total=600
  return 600 / (200 + Math.pow(totalM2 - 200, 0.741));
}


// preço: 650€ até 2000m²; curva suave até 2500€ aos 10000m²
function calcularPrecoAltimetrico(m2) {
  m2 = Number(m2 || 0);
  if (m2 <= 0) return 0;
  if (m2 <= 2000) return 400;
  if (m2 >= 10000) return 2500;

  const t = (m2 - 2000) / (10000 - 2000); // 0..1
  const curva = Math.pow(t, 0.72);        // sobe devagar e acelera no fim (muito “aos poucos”)
  return 400 + curva * (2500 - 400);
}

function syncAltimetricoDisponibilidade() {
  // dropdown: só ativo se houver drone
  setOpcaoDropdown(ID_ALTIMETRICO, !existeDrone());

  // se removerem o drone, remove o altimétrico também
  if (!existeDrone() && existeAltimetrico()) {
    const card = servicosContainer.querySelector(`.servico-card[data-id="${ID_ALTIMETRICO}"]`);
    if (card) card.remove();

    // re-enable option (mas continua disabled porque não há drone)
    const opt = novoServico.querySelector(`option[value="${ID_ALTIMETRICO}"]`);
    if (opt) opt.disabled = true;

    atualizarAjustesTotais();
  }
}


// === FUNÇÃO: ATUALIZA SE MOSTRA ÁREAS ===
function checkAreas() {
  if (HYDRATING) { 
    areaSection.style.display = 'block'; // ✅ durante preload, nunca esconder
    return;
  }
  const ativos = Array.from(servicosContainer.querySelectorAll('.servico-card strong'))
    .map(el => el.textContent.trim());
  const precisaAreas = ativos.some(nome => servicosComAreas.includes(nome));
  areaSection.style.display = precisaAreas ? 'block' : 'none';
}


// === REMOVER SERVIÇO ===
function removerServico(botao) {
  const card = botao.parentElement;
  const id = card.getAttribute('data-id');
  const option = novoServico.querySelector(`option[value="${id}"]`);
  // se removeram o topográfico e custos_extra ainda estava em modo auto, zera
  const nome = (card.querySelector('strong')?.textContent || '').toLowerCase();
  if (nome.includes('topográ') || nome.includes('topograf')) {
    if (!state.custosExtraManual) setCustosExtra(0);
  }

  if (option) option.disabled = false;
  card.remove();
  checkAreas();
  syncAltimetricoDisponibilidade();
  atualizarAjustesTotais();
  atualizarLodPorBIM();


}

function ensureLinhaDeslocacao() {
  let linhaDesloc = document.getElementById("linha_deslocacao");
  if (!linhaDesloc) {
    const row = document.createElement("tr");
    row.id = "linha_deslocacao";
    row.innerHTML = `
      <td style="padding:8px;">Deslocação</td>
      <td id="preco_total_deslocacao" style="text-align:right; padding:8px;" data-eur="0"></td>
    `;
    document.getElementById("tabelaResumoBody")?.appendChild(row);
    setMoney(document.getElementById("preco_total_deslocacao"), 0);
  }
}


// === FUNÇÃO GERAL PARA ADICIONAR UM SERVIÇO (usada manual e automaticamente) ===
function adicionarServico(s) {
  if (!s || jaExisteCard(s.id)) return;

  const card = document.createElement('div');
  card.classList.add('servico-card');
  card.setAttribute('data-id', s.id);

  const nomeNorm = normalizarTexto(s.nome);
  // ✅ ALTIMÉTRICO (ID 17) — SÓ SE O DRONE EXISTIR
  if (s.id == ID_ALTIMETRICO) {
    if (!existeDrone()) {
      // não deixa adicionar sem drone
      setOpcaoDropdown(ID_ALTIMETRICO, true);
      return;
    }

    const cardAlt = document.createElement('div');
    cardAlt.classList.add('servico-card');
    cardAlt.setAttribute('data-id', s.id);

    cardAlt.innerHTML = `
      <strong>${s.nome}</strong>
      <input type="hidden" name="servicos[${servicoCount}][id]" value="${s.id}">
      <p style="margin-top:10px; color:#555;">
        Serviço disponível apenas com Levantamento Drone.<br>
        Preço automático conforme área total.
      </p>
      <div style="margin-top:10px;">
        <label><strong>Preço (auto):</strong></label>
        <div id="preco_altimetrico_auto" style="background:#f2f2f2; padding:8px; border-radius:6px; font-weight:bold;">
          0,00 €
        </div>
      </div>
      <button type="button" class="btn btn-remove-servico" onclick="removerServico(this)">Remover</button>
    `;

    servicosContainer.appendChild(cardAlt);
    setOpcaoDropdown(s.id, true);
    servicoCount++;

    atualizarAjustesTotais();
    return;
  }


  // === DETETAR O SERVIÇO RENDERIZAÇÕES (ID 16) ===
  if (s.id == 16) {
      const card = document.createElement('div');
      card.classList.add('servico-card');
      card.setAttribute('data-id', s.id);

      card.innerHTML = `
          <strong>${s.nome}</strong>
          <input type="hidden" name="servicos[${servicoCount}][id]" value="${s.id}">
          <p style="margin-top:10px; color:#555;">
              Este serviço adiciona automaticamente:
              <br>• +400€ por área interior
              <br>• 2€/m² por área exterior
          </p>
          <button type="button" class="btn btn-remove-servico" onclick="removerServico(this)">Remover</button>
      `;

      servicosContainer.appendChild(card);
      setOpcaoDropdown(s.id, true);
      servicoCount++;
      atualizarAjustesTotais();
      return; // impede criar selects desnecessários
  }

  // 🟢 CASO ESPECIAL: LEVANTAMENTO TOPOGRÁFICO
  if (nomeNorm === 'levantamentotopografico') {
    card.innerHTML = `
      <strong>${s.nome}</strong>
      <input type="hidden" name="servicos[${servicoCount}][id]" value="${s.id}">

      <label>Preço (s/ IVA) (€):</label>
      <input
        type="number"
        class="input-preco-topografico"
        id="preco_topografico"
        name="servicos[${servicoCount}][preco]"
        step="0.01"
        min="0"
        oninput="atualizarAjustesTotais()"
      >

      <label style="display:flex; align-items:center; gap:10px; margin-top:10px;">
        <input type="checkbox" id="chk_topo_mais10" checked onchange="syncTopo10Hidden(); atualizarAjustesTotais();">
        <span>Aplicar +10%</span>
      </label>

      <input type="hidden" name="topo_aplicar_mais10" id="topo_aplicar_mais10" value="1">

      <div id="pdf_prev_topo"
          data-eur="0"
          style="margin-top:6px; font-size:12px; color:#666; font-style:italic;">
        PDF (c/ desloc. + IVA): —
      </div>

      <button type="button" class="btn btn-remove-servico" onclick="removerServico(this)">Remover</button>
    `;
  } else {
    // 🔸 SERVIÇOS NORMAIS (sem preço manual)
    card.innerHTML = `
      <strong>${s.nome}</strong>
      <input type="hidden" name="servicos[${servicoCount}][id]" value="${s.id}">
      <button type="button" class="btn btn-remove-servico" onclick="removerServico(this)">Remover</button>
    `;
  }

  // ✅ Link Topográfico → Custos Extra (auto preenchido, com override manual)
  setTimeout(() => {
    const topoEl = document.getElementById('preco_topografico');
    if (!topoEl) return;

    // preenche no arranque (se ainda não foi manual)
    if (!state.custosExtraManual) {
      setCustosExtra(hNum(topoEl.value)); // <- copia o input s/ IVA (como pediste)
    }

    topoEl.addEventListener('input', () => {
      if (!state.custosExtraManual) {
        setCustosExtra(hNum(topoEl.value));
      }
      atualizarAjustesTotais();
    });
  }, 0);


    // 🟢 CASO ESPECIAL: LEVANTAMENTO DRONE
    if (nomeNorm === 'levantamentodronefotos') {

        const cardDrone = document.createElement('div');
        cardDrone.classList.add('servico-card');
        cardDrone.setAttribute('data-id', s.id);

        cardDrone.innerHTML = `
          <strong>${s.nome}</strong>
          <input type="hidden" name="servicos[${servicoCount}][id]" value="${s.id}">

          <div style="margin-top:10px;">
              <label><strong>Preço base automático:</strong></label>
              <div id="preco_drone_base" 
                  style="background:#f2f2f2; padding:8px; border-radius:6px; font-weight:bold;">
                  0.00 €
              </div>
          </div>

          <div style="margin-top:12px;">
              <label><strong>Opções adicionais:</strong></label><br>

              <label>
                  <input type="checkbox" name="opcoes_drone[]" value="Georreferenciação" class="chk-drone" data-preco="200">
                  Georreferenciação (+200€)
              </label><br>

              <label>
                  <input type="checkbox" name="opcoes_drone[]" value="Nuvem de Pontos" class="chk-drone" data-preco="200">
                  Criação de Nuvem de Pontos (+200€)
              </label><br>

              <label>
                  <input type="checkbox" name="opcoes_drone[]" value="Modelo DIM" class="chk-drone" data-preco="100">
                  Modelo DIM (+100€)
              </label><br>

              <label>
                  <input type="checkbox" name="opcoes_drone[]" value="Modelo Renderizado Materiais" class="chk-drone" data-preco="100">
                  Modelo Renderizado Materiais (+100€)
              </label>
          </div>

          <button type="button" class="btn btn-remove-servico" onclick="removerServico(this)">Remover</button>
        `;


        servicosContainer.appendChild(cardDrone);
        
        setOpcaoDropdown(s.id, true);
        servicoCount++;

        // listeners para recalcular extras
        document.querySelectorAll(".chk-drone").forEach(chk => {
            chk.addEventListener("change", atualizarAjustesTotais);
        });
        // se adicionou BIM, aplica regra (mas NÃO durante preload)
        if (!HYDRATING && normalizarTexto(s.nome) === "bim") {
          aplicarRegraBIM();
        }


        atualizarDronePrecoBase();
        atualizarAjustesTotais();
        syncAltimetricoDisponibilidade();
        return;
    }



  // 🔽 SERVIÇOS COM OPÇÕES (Nível de Detalhe, Modelo 3D, BIM)
  const nomeNormalizado = normalizarTexto(s.nome);
  if (opcoesServicos[nomeNormalizado]) {
    const selectOpcao = document.createElement('select');

    // === DEFINIR OPÇÃO DEFAULT AUTOMÁTICA ===
    if (!IS_EDIT) {
      setTimeout(() => {
        if (nomeNormalizado === 'niveldedetalhe') {
          selectOpcao.value = "1:100";
          document.getElementById("input_opcao_nivel_detalhe").value = "1:100";
        }
        if (nomeNormalizado === 'modelo3dniveldedetalhe') {
          selectOpcao.value = "1:200";
          document.getElementById("input_opcao_modelo3d_nivel").value = "1:200";
        }
        atualizarAjustesTotais();
      }, 50);
    }






    selectOpcao.name = `opcao_servico[${s.id}]`;
    selectOpcao.classList.add('select-opcao');

    // === definir o tipo (para aplicar os ajustes certos)
    let tipo = null;
    if (nomeNormalizado === 'niveldedetalhe') tipo = 'lod';
    if (nomeNormalizado === 'modelo3dniveldedetalhe') tipo = 'modelo3d';
    if (nomeNormalizado === 'bim') tipo = 'bim';
    if (tipo) selectOpcao.dataset.tipo = tipo;

    const placeholder = document.createElement('option');
    placeholder.value = "";
    placeholder.textContent = "Selecione uma opção";
    selectOpcao.appendChild(placeholder);

    opcoesServicos[nomeNormalizado].forEach(op => {
      const opt = document.createElement('option');
      opt.value = op;
      opt.textContent = op;
      selectOpcao.appendChild(opt);
    });

    // ⤵️ Listener direto (garante atualização imediata)
    selectOpcao.addEventListener('change', atualizarAjustesTotais);

    card.appendChild(selectOpcao);
  }

  servicosContainer.appendChild(card);
  servicoCount++;
  setOpcaoDropdown(s.id, true);
  checkAreas();
  atualizarAjustesTotais();
}




// === EVENTO BOTÃO "ADICIONAR SERVIÇO" ===
addServicoBtn.addEventListener('click', () => {
  const id = novoServico.value;
  if (!id) return;

  const s = servicos.find(x => x.id == id);
  adicionarServico(s);

  // Desativa a opção na dropdown
  const option = novoServico.querySelector(`option[value="${id}"]`);
  if (option) option.disabled = true;

  // Volta ao valor padrão
  novoServico.value = "";
});

// === ÁREAS ===
// === Função para calcular o preço/m² ===
// Mantém a função mas agora ela usa o TOTAL de m².
// (se ainda não quiseres mudar o nome, ok — mas o comportamento fica correto)
function calcularPrecoPorMetro(m2Ignorado) {
  const totalM2 = getTotalM2Areas();
  return calcularPrecoPorMetroGlobal(totalM2);
}

function onNomeAreaChange(i){
  const inp = document.getElementById(`area_nome_${i}`);
  const title = document.getElementById(`area_title_${i}`);
  if (!inp || !title) return;

  // marca como manual se o user mexeu
  inp.dataset.manual = "true";

  const v = (inp.value || "").trim();
  title.textContent = v ? v : `Área ${i + 1}`;
}


// === Criação de Áreas ===
document.getElementById('addAreaBtn').addEventListener('click', () => {
  const areaDiv = document.createElement('div');
  areaDiv.classList.add('area-item');
  areaDiv.innerHTML = `
      <strong id="area_title_${areaCount}">Área ${areaCount + 1}</strong><br>

      <label style="margin-top:10px;">Nome da Área:</label>
      <input type="text"
            name="areas[${areaCount}][nome]"
            id="area_nome_${areaCount}"
            value="Área ${areaCount + 1}"
            oninput="onNomeAreaChange(${areaCount})"
            style="width:100%;"
      >


      <label>Metros Quadrados:</label>
      <input type="number" name="areas[${areaCount}][m2]" id="area_m2_${areaCount}" 
             min="0" step="0.01" oninput="atualizarArea(${areaCount})">

      <div class="area-toggle" id="area_toggle_wrap_${areaCount}">
        <label class="switch" title="Marcar como área exterior">
          <input type="checkbox"
                name="areas[${areaCount}][exterior]"
                id="area_ext_${areaCount}"
                onchange="onToggleExterior(${areaCount})">
          <span class="slider"></span>
        </label>

        <div class="toggle-texts">
          <div class="toggle-title">Área exterior</div>
          <div class="toggle-sub">Exterior: 2€/m² • Interior: 400€ por área</div>
        </div>

        <span class="badge" id="area_badge_${areaCount}">INTERIOR</span>
      </div>


      <label style="margin-top:10px;">Preço por m² (€)</label>
      <div style="display:flex; align-items:center; gap:10px;">
        <input type="number" name="areas[${areaCount}][preco_m2]" id="area_preco_${areaCount}" 
               step="0.01" oninput="atualizarSubtotalManual(${areaCount})" style="flex:1;">
        <input type="hidden" name="areas[${areaCount}][subtotal_manual]" id="area_subtotal_hidden_${areaCount}" value="">
        <input type="hidden" name="areas[${areaCount}][subtotal_manual_ativo]" id="area_subtotal_ativo_${areaCount}" value="0">

        <button type="button" class="btn btn-reset" onclick="redefinirPrecoM2(${areaCount})">
            Redefinir preço M²
        </button>
      </div>

      <button type="button" class="btn btn-remove" 
              onclick="this.parentElement.remove(); areaCount--; renumerarAreas(); atualizarResumo();">
          Remover Área
      </button>

      <br><br>
      <p style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span><strong>Subtotal:</strong> <span id="subtotal_area_${areaCount}" data-eur="0">0.00 €</span></span>

        <button type="button"
                class="btn btn-reset"
                id="btn_subtotal_manual_${areaCount}"
                onclick="toggleSubtotalManual(${areaCount})">
          Editar subtotal
        </button>
      </p>

      <div id="wrap_subtotal_manual_${areaCount}" style="display:none; margin-top:8px;">
        <label>Subtotal manual (s/ IVA) (€):</label>
        <input type="number"
              id="area_subtotal_manual_${areaCount}"
              step="0.01"
              min="0"
              value=""
              oninput="onSubtotalManualInput(${areaCount})"
              style="width:220px;">
        <div class="muted">• Ao definir subtotal manual, o preço/m² ajusta automaticamente (subtotal ÷ m²).</div>
      </div>

      


  `;
    areasContainer.appendChild(areaDiv);
    renumerarAreas(); // garante que os IDs e índices estão certos
    syncToggleUI(areaCount - 1);


});

function toggleSubtotalManual(i){
  const wrap = document.getElementById(`wrap_subtotal_manual_${i}`);
  const btn  = document.getElementById(`btn_subtotal_manual_${i}`);
  const inp  = document.getElementById(`area_subtotal_manual_${i}`);
  const precoInput = document.getElementById(`area_preco_${i}`);

  if (!wrap || !btn || !inp || !precoInput) return;

  const ativo = wrap.style.display !== "none";

  if (ativo) {
    wrap.style.display = "none";
    btn.textContent = "Editar subtotal";
    delete inp.dataset.manualSubtotal;

    // sair do modo manual
    document.getElementById(`area_subtotal_ativo_${i}`).value = "0";
    document.getElementById(`area_subtotal_hidden_${i}`).value = "";

    // preço/m² volta a ser editável/normal (igual ao renegociar)
    precoInput.readOnly = false;
    precoInput.style.opacity = "";
    precoInput.style.cursor = "";
    // se quiseres que volte a auto, remove o manual:
    // delete precoInput.dataset.manual;

    atualizarArea(i);
    atualizarResumo();
    return;

  }

  // ATIVAR modo subtotal manual
  wrap.style.display = "block";
  btn.textContent = "Subtotal automático";
  inp.dataset.manualSubtotal = "true";     // flag ON

  // se input estiver vazio, inicializa com o subtotal atual calculado
  if ((inp.value || "").trim() === "") {
    const m2 = hNum(document.getElementById(`area_m2_${i}`)?.value);
    const pm2 = hNum(precoInput.value);
    const subtotalAtual = m2 * pm2;
    precoInput.readOnly = false;
    precoInput.style.opacity = "";
    precoInput.style.cursor = "";
    document.getElementById(`area_subtotal_ativo_${i}`).value = "1";
    document.getElementById(`area_subtotal_hidden_${i}`).value = "";


    inp.value = (Number(subtotalAtual || 0)).toFixed(2);
  }

  // força atualização para aplicar já
  onSubtotalManualInput(i);
}

function onSubtotalManualInput(i){
  const inp  = document.getElementById(`area_subtotal_manual_${i}`);
  const m2El = document.getElementById(`area_m2_${i}`);
  const precoInput = document.getElementById(`area_preco_${i}`);
  const subtotalSpan = document.getElementById(`subtotal_area_${i}`);

  if (!inp || !m2El || !precoInput || !subtotalSpan) return;
  if (inp.dataset.manualSubtotal !== "true") return;

  const subtotalManual = hNum(inp.value);
  const m2 = hNum(m2El.value);

  // ✅ 1) subtotal fica EXACTO
  setMoney(subtotalSpan, subtotalManual);

  // ✅ 2) €/m² é só "derivado" (para mostrar/guardar), mas não manda no subtotal
  if (m2 > 0) {
    const pm2 = subtotalManual / m2;

    // guarda alta precisão internamente (para não perderes a matemática)
    precoInput.dataset.pm2_exact = String(pm2);

    // mostra 2 casas (UI)
    precoInput.value = pm2.toFixed(2);

    // marca como manual (para não ser atropelado pelo global)
    precoInput.dataset.manual = "true";
  } else {
    precoInput.dataset.pm2_exact = "0";
    precoInput.value = "0.00";
  }
  const hid = document.getElementById(`area_subtotal_hidden_${i}`);
  const hidAtivo = document.getElementById(`area_subtotal_ativo_${i}`);
  if (hid) hid.value = subtotalManual.toFixed(2);
  if (hidAtivo) hidAtivo.value = "1";

  atualizarResumo();
}


// === Atualizar uma área (preço e subtotal) ===
// === Função automática (calcula fórmula com mínimo 600 €) ===
function atualizarArea(index) {
  const inpSubManual = document.getElementById(`area_subtotal_manual_${index}`);
  const modoSubManual = inpSubManual?.dataset?.manualSubtotal === "true";

  // ✅ Se subtotal manual está ativo, não recalcules por m2*pm2
  if (modoSubManual) {
    onSubtotalManualInput(index);
    return;
  }

  const m2Input = document.getElementById(`area_m2_${index}`);
  const precoInput = document.getElementById(`area_preco_${index}`);
  const subtotalSpan = document.getElementById(`subtotal_area_${index}`);
  const m2 = parseFloat(m2Input?.value) || 0;

  if (!precoInput.dataset.manual) {
    const precoM2_raw = calcularPrecoPorMetro(m2);
    const precoM2 = round2(precoM2_raw);
    precoInput.value = precoM2.toFixed(2);

    const subtotal = m2 * precoM2;
    subtotalSpan.innerText = subtotal.toFixed(2) + " €";
  } else {
    const precoM2 = parseFloat(precoInput.value) || 0;
    const subtotal = m2 * precoM2;
    subtotalSpan.innerText = subtotal.toFixed(2) + " €";
  }

  atualizarResumo();
}


// === Função manual (quando o utilizador escreve no preço/m²) ===
function atualizarSubtotalManual(index) {
  const m2Input = document.getElementById(`area_m2_${index}`);
  const precoInput = document.getElementById(`area_preco_${index}`);
  const subtotalSpan = document.getElementById(`subtotal_area_${index}`);

  const m2 = parseFloat(m2Input?.value) || 0;
  const precoM2 = parseFloat(precoInput?.value) || 0;

  // Marca como “manual” e muda visualmente
  precoInput.dataset.manual = "true";

  const subtotal = m2 * precoM2; // sem mínimo
  subtotalSpan.innerText = subtotal.toFixed(2) + " €";

  atualizarResumo();
}

function redefinirPrecoM2(index) {
  const m2Input = document.getElementById(`area_m2_${index}`);
  const precoInput = document.getElementById(`area_preco_${index}`);
  const subtotalSpan = document.getElementById(`subtotal_area_${index}`);

  const m2 = parseFloat(m2Input?.value) || 0;

  // volta a cálculo automático com base na fórmula
  const precoM2_raw = calcularPrecoPorMetro(m2);
  const precoM2 = round2(precoM2_raw);

  precoInput.value = precoM2.toFixed(2);
  delete precoInput.dataset.manual;

  const subtotal = m2 * precoM2;                  // ✅ usa o arredondado
  subtotalSpan.innerText = subtotal.toFixed(2) + " €";


  atualizarResumo();
}


function calcularDeslocacao() {
  const distancia = parseFloat(document.getElementById("distancia")?.value) || 0;
  const areas = document.querySelectorAll(".area-item");
  const totalAreas = areas.length;

  // somar m² totais
  let totalM2 = 0;
  for (let i = 0; i < totalAreas; i++) {
    totalM2 += parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
  }

  // nº de dias (igual ao teu)
  let dias = 1;
  if (totalM2 > 0) {
    dias = Math.min(Math.round((totalM2 / 4000) + 0.5), 100);
  }

  // componentes
  const tecnicoAlimFixo = 130;                // ✅ fixo
  const tecnicoAlimFixototal = 130 * dias;
  const distanciaTotal  = distancia * 0.4 * 2 * dias; // ✅ distância por dias

  // checkboxes
  const incTec  = !!document.getElementById("chk_tecnico_alim")?.checked;
  const incDist = !!document.getElementById("chk_distancia")?.checked;

  const totalDeslocacao = (incTec ? tecnicoAlimFixototal : 0) + (incDist ? distanciaTotal : 0);

  // distribuição por área (mantém lógica antiga)
  const qtdAreas = Math.max(totalAreas, 1);
  const deslocacaoPorArea = totalDeslocacao / qtdAreas;

  return {
    totalDeslocacao,
    deslocacaoPorArea,
    dias,
    tecnicoAlimDia: tecnicoAlimFixo,
    tecnicoAlimTotal: tecnicoAlimFixototal,
    distanciaTotal,
    incTec,
    incDist
  };

}



// === Atualizar total geral ===
function atualizarResumo() {
  let total = 0;
  for (let i = 0; i < areaCount; i++) {
    const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
    const precoM2 = parseFloat(document.getElementById(`area_preco_${i}`)?.value) || 0;
    total += m2 * precoM2;
  }
  document.getElementById('preco_total_laser').innerText = total.toFixed(2) + " €";
}

// === Ajustes de percentagem por opção de nível de detalhe ===
const ajustesNivelDetalhe = { "1:200": -10, "1:100": 0, "1:50": 60, "1:20": 130, "1:1": 300 };
const ajustesModelo3D    = { "1:200": 0,   "1:100": 60, "1:50": 130, "1:20": 300};
const ajustesBIM         = { "Bricscad": 20, "Archicad": 20, "Revit": 20 };

function calcularExtraOpcoesTotalEUR(totalBaseEUR){
  let extra = 0;

  document.querySelectorAll('select.select-opcao').forEach(sel => {
    const tipo = (sel.dataset.tipo || "").toLowerCase();
    const val  = sel.value;
    if (!val) return;

    if ((tipo === 'lod' || tipo.includes('niveldedetalhe')) && ajustesNivelDetalhe[val] !== undefined) {
      extra += totalBaseEUR * (ajustesNivelDetalhe[val] / 100);
    }

    if ((tipo === 'modelo3d' || tipo.includes('modelo3d')) && ajustesModelo3D[val] !== undefined) {
      extra += totalBaseEUR * (ajustesModelo3D[val] / 100);
    }

    if (tipo === 'bim' && ajustesBIM[val] !== undefined) {
      extra += totalBaseEUR * (ajustesBIM[val] / 100);
    }
  });

  return extra;
}

function round2(n) {
  n = Number(n || 0);
  return Math.round(n * 100) / 100;
}

// ======================
// MOEDAS (UI) + CONVERSÃO
// ======================
window.__CUR = {
  code: "EUR",
  rateToEUR: 1, // 1 [code] = X EUR
};

const CURRENCY_META = {
  EUR: { label: "Euro", symbolFallback: "€", decimals: 2, defaultRateToEUR: 1 },
  USD: { label: "Dólar", symbolFallback: "$", decimals: 2, defaultRateToEUR: 0.85 },   // 1 USD ≈ 0.85 EUR (editável)
  GBP: { label: "Libra", symbolFallback: "£", decimals: 2, defaultRateToEUR: 1.15 },  // 1 GBP ≈ 1.15 EUR (editável)
  JPY: { label: "Iene", symbolFallback: "¥", decimals: 0, defaultRateToEUR: 0.0055 }, // 1 JPY ≈ 0.0055 EUR (editável)
};

function formatCurrency(amount, code) {
  const meta = CURRENCY_META[code] || CURRENCY_META.EUR;
  const n = Number(amount || 0);

  try {
    // pt-PT para formatos com vírgula e símbolo correto
    return new Intl.NumberFormat("pt-PT", {
      style: "currency",
      currency: code,
      minimumFractionDigits: meta.decimals,
      maximumFractionDigits: meta.decimals,
    }).format(n);
  } catch (e) {
    // fallback se algum browser não suportar a moeda
    return n.toFixed(meta.decimals).replace(".", ",") + " " + (meta.symbolFallback || "");
  }
}

function eurToSelectedCurrency(eurAmount) {
  const eur = Number(eurAmount || 0);
  const code = window.__CUR.code;
  const rateToEUR = Number(window.__CUR.rateToEUR || 1);

  // Se 1 [code] = rateToEUR EUR
  // então EUR -> [code] é dividir por rateToEUR
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

  // Limpa conteúdo anterior e desenha de forma consistente
  // Mantém sublinha como elemento filho controlado por nós
  el.innerHTML = "";

  const main = document.createElement("div");
  main.className = "money-main";
  main.style.fontWeight = "inherit";

  const shown = eurToSelectedCurrency(eur);
  main.textContent = formatCurrency(shown, window.__CUR.code);

  el.appendChild(main);

  // Linha em EUR por baixo (só se moeda != EUR)
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

  // Atualiza labels UI
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
  sel.addEventListener("change", () => {
    const code = sel.value || "EUR";
    window.__CUR.code = code;
    window.__CUR.rateToEUR = (CURRENCY_META[code]?.defaultRateToEUR || 1);

    atualizarAjustesTotais();  // recalcula previews e setMoney/datasets
    refreshAllMoney();
  });

  // Estado inicial
  window.__CUR.code = sel.value || "EUR";
  window.__CUR.rateToEUR = (CURRENCY_META[window.__CUR.code]?.defaultRateToEUR || 1);

  refreshAllMoney();

  

  if (taxaInput) {
    taxaInput.addEventListener("input", () => {
      const v = parseFloat(taxaInput.value);
      window.__CUR.rateToEUR = (isNaN(v) || v <= 0) ? window.__CUR.rateToEUR : v;

      // só muda visualização (não muda os valores base em EUR)
      refreshAllMoney();
    });
  }
}


// ==== Estado global dos totais (sempre atualizado pelo cálculo principal) ====
window.__TOT__ = { laser: 0, topo: 0 };

// ===============================
// STATE (custos extra + deslocação)
// ===============================
const state = {
  custosExtraManual: false,
  totals: { deslocacao: 0 }
};

function hNum(v){
  const n = parseFloat(String(v ?? "").replace(",", "."));
  return isNaN(n) ? 0 : n;
}

function getCustosExtra(){
  const el = document.getElementById('custos_extra');
  return el ? hNum(el.value) : 0;
}

function setCustosExtra(v){
  const el = document.getElementById('custos_extra');
  if (!el) return;
  el.value = (Number(v || 0)).toFixed(2);
}

function onCustosExtraManual(){
  state.custosExtraManual = true;
  atualizarTotaisFinais(); // recalcula previews
}

window.RN = window.RN || {};
window.RN.onCustosExtraManual = onCustosExtraManual;

// ==================== CÁLCULO PRINCIPAL ====================
function atualizarAjustesTotais() {
  // 1) TOTAL m² e preço/m² GLOBAL (modo automático)
  const totalM2 = getTotalM2Areas();
  const precoM2Global_raw = calcularPrecoPorMetroGlobal(totalM2);
  const precoM2Global = round2(precoM2Global_raw);   // ✅ arredonda aqui


  // Atualiza UI do texto "Total áreas: X m²"
  const infoM2 = document.getElementById("laser_total_m2_info");
  if (infoM2) infoM2.textContent = `Total áreas: ${Math.round(totalM2)} m²`;

  // 2) Base: soma de todas as áreas (com preço global em modo automático)
  let totalBase = 0;

  for (let i = 0; i < areaCount; i++) {
    const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;

    const precoInput = document.getElementById(`area_preco_${i}`);
    const subtotalSpan = document.getElementById(`subtotal_area_${i}`);

    let precoM2 = 0;

    if (precoInput) {
      if (!precoInput.dataset.manual) {
        precoM2 = precoM2Global;                      // ✅ já arredondado
        precoInput.value = precoM2Global.toFixed(2);
      } else {
        precoM2 = parseFloat(precoInput.value) || 0;
      }
    }

    const inpSubManual = document.getElementById(`area_subtotal_manual_${i}`);
    const modoSubManual = inpSubManual?.dataset?.manualSubtotal === "true";

    let subtotal = 0;

    if (modoSubManual) {
      // ✅ subtotal vem SEMPRE do manual
      subtotal = hNum(inpSubManual.value);
      if (subtotalSpan) setMoney(subtotalSpan, subtotal);

      // opcional: manter €/m² sincronizado se houver m²
      if (m2 > 0 && precoInput) {
        const pm2 = subtotal / m2;
        precoInput.dataset.pm2_exact = String(pm2);
        precoInput.value = pm2.toFixed(2);
        precoInput.dataset.manual = "true";
      }
    } else {
      subtotal = m2 * precoM2;
      if (subtotalSpan) setMoney(subtotalSpan, subtotal);
    }

    totalBase += subtotal;


  }
  // ✅ guarda base das áreas para previews baterem com o PDF
  window.__TOT__ = window.__TOT__ || {};
  window.__TOT__.baseAreas = totalBase;

  let totalFinalSemTopo = totalBase; // Laser + ajustes (SEM topo)

 

  // 2) Ajustes percentuais (LOD, Modelo 3D, BIM)
  // 2) Ajustes percentuais (LOD, Modelo 3D, BIM)
  document.querySelectorAll('select.select-opcao').forEach(sel => {
    const tipo = sel.dataset.tipo?.toLowerCase() || "";
    const val  = sel.value;
    if (!val) return;

    // Nível de detalhe (LOD)
    if ((tipo === 'lod' || tipo.includes('niveldedetalhe')) && ajustesNivelDetalhe[val] !== undefined)
      totalFinalSemTopo += totalBase * (ajustesNivelDetalhe[val] / 100);

    // Modelo 3D (com ou sem “nível de detalhe” no nome)
    if ((tipo === 'modelo3d' || tipo.includes('modelo3d')) && ajustesModelo3D[val] !== undefined)
      totalFinalSemTopo += totalBase * (ajustesModelo3D[val] / 100);

    // BIM
    if (tipo === 'bim' && ajustesBIM[val] !== undefined)
      totalFinalSemTopo += totalBase * (ajustesBIM[val] / 100);

    sel.addEventListener("change", () => {
        if (tipo === "lod") {
            document.getElementById("input_opcao_nivel_detalhe").value = sel.value;
        }
        if (tipo === "modelo3d") {
            document.getElementById("input_opcao_modelo3d_nivel").value = sel.value;
        }
        if (tipo === "bim") {
            document.getElementById("input_opcao_bim").value = sel.value;
        }
        atualizarAjustesTotais();
    });


   


  });


  // 3) Georreferenciação
  const temGeo = Array.from(document.querySelectorAll('.servico-card strong'))
    .some(el => normalizarTexto(el.textContent.trim()) === 'georreferenciacao');

  if (temGeo) {
    if (totalBase <= 1000) totalFinalSemTopo += 200;
    else totalFinalSemTopo += 200 + (Math.floor(totalBase / 1000) * 50);
  }
  
  
  // 4) Topográfico (se existir): calcula mas NÃO soma no Laser
  const precoTopograficoInput = document.getElementById('preco_topografico');
  let totalTopo = 0;
  if (precoTopograficoInput) {
    const precoTopo = parseFloat(precoTopograficoInput.value) || 0;

    const chk10 = document.getElementById("chk_topo_mais10");
    const aplicar10 = chk10 ? chk10.checked : true; // default ON

    totalTopo = precoTopo * (aplicar10 ? 1.10 : 1.00);

    const linhaTopo = document.getElementById('linha_topografico');
    const valorTopoCell = document.getElementById('preco_total_topo');

    if (linhaTopo && valorTopoCell) {
      linhaTopo.style.display = '';

      // (opcional) muda o texto da linha
      const td = linhaTopo.querySelector("td:first-child");
      if (td) td.textContent = aplicar10 ? "Levantamento Topográfico (+10%)" : "Levantamento Topográfico (sem +10%)";

      setMoney(valorTopoCell, totalTopo);
    }

    document.getElementById("input_preco_topografico").value = totalTopo;

  } else {
    const lt = document.getElementById('linha_topografico');
    if (lt) lt.style.display = 'none';
  }


  // --- DRONE ---
  let totalDrone = 0;

  const existeDroneAgora = Array.from(document.querySelectorAll(".servico-card strong"))
    .some(el => el.textContent.toLowerCase().includes("drone"));

  if (existeDroneAgora) {

    const precoBaseDrone = atualizarDronePrecoBase();

    let extrasDrone = 0;
    document.querySelectorAll(".chk-drone:checked").forEach(chk => {
      extrasDrone += parseFloat(chk.dataset.preco || 0);
    });

    // ✅ Altimétrico soma ao Drone
    let totalAltimetrico = 0;
    if (existeAltimetrico()) {
      const m2 = totalM2Areas();
      totalAltimetrico = calcularPrecoAltimetrico(m2);

      const outAlt = document.getElementById("preco_altimetrico_auto");
      if (outAlt) setMoney(outAlt, totalAltimetrico);

    }

    totalDrone = precoBaseDrone + extrasDrone + totalAltimetrico;

    const linhaDrone = document.getElementById("linha_drone");
    if (linhaDrone) {
      linhaDrone.style.display = '';
      setMoney(document.getElementById("preco_total_drone"), totalDrone);

    }
    document.getElementById("input_preco_total_drone").value = totalDrone;

  } else {
    const linhaDrone = document.getElementById("linha_drone");
    if (linhaDrone) linhaDrone.style.display = 'none';
    document.getElementById("input_preco_total_drone").value = 0;
  }


  



  const d = calcularDeslocacao();
  totalFinalSemTopo += d.totalDeslocacao;
  state.totals.deslocacao = d.totalDeslocacao;

  // UI do bloco (valores separados)
  setMoney(document.getElementById("valor_tecnico_alim"), d.tecnicoAlimTotal);
  setMoney(document.getElementById("valor_distancia"), d.distanciaTotal);

  


  const diasLbl = document.getElementById("deslocacao_dias_lbl");
  if (diasLbl) diasLbl.textContent = String(d.dias);

  // hiddens para o PHP
  document.getElementById("inclui_tecnico_alimentacao").value = d.incTec ? "1" : "0";
  document.getElementById("inclui_distancia").value = d.incDist ? "1" : "0";
  document.getElementById("preco_tecnico_alimentacao").value = String(round2(d.tecnicoAlimTotal));
  document.getElementById("preco_distancia").value = String(round2(d.distanciaTotal));
  document.getElementById("preco_deslocacao_total").value = String(round2(d.totalDeslocacao));

  // =============================
  // RENDERIZAÇÕES (SERVIÇO ID 16)
  // =============================
  let totalRender = 0;
  const temRender = Array.from(document.querySelectorAll('.servico-card[data-id="16"]')).length > 0;

  if (temRender) {
      for (let i = 0; i < areaCount; i++) {
          const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
          const isExterior = document.getElementById(`area_ext_${i}`)?.checked;

          if (isExterior) {
              totalRender += m2 * 2;  // 2€/m2
          } else {
              totalRender += 400;     // 400€ por área interior
          }
      }
  }
  totalFinalSemTopo += totalRender;
  // Guardar no hidden para o PHP
  document.getElementById("input_total_render").value = totalRender;

  // ========================================
  // EXIBIR LINHA DE RENDERIZAÇÕES NO RESUMO
  // ========================================
  const linhaRender = document.getElementById("linha_render");
  if (linhaRender) {
    if (temRender && totalRender > 0) {
      linhaRender.style.display = "";
      const outRender = document.getElementById("preco_total_render");
      if (outRender) setMoney(outRender, totalRender);
    } else {
      linhaRender.style.display = "none";
      const outRender = document.getElementById("preco_total_render");
      if (outRender) setMoney(outRender, 0);
    }
  }


  ensureLinhaDeslocacao();

  const cellDesloc = document.getElementById("preco_total_deslocacao");
  if (cellDesloc) setMoney(cellDesloc, d.totalDeslocacao);

  const linhaDesloc = document.getElementById("linha_deslocacao");
  if (linhaDesloc) {
    const td = linhaDesloc.querySelector("td:first-child");
    if (td) td.textContent = `Deslocação (${d.dias} dia${d.dias > 1 ? "s" : ""})`;
  }

  // 6) Atualiza o “Laser Scan” SEM topográfico
  const out = document.getElementById('preco_total_laser');
  if (out) setMoney(out, totalFinalSemTopo);

  const extraOpcoesTotal = calcularExtraOpcoesTotalEUR(totalBase);

  // guarda para previews
  window.__TOT__.extraOpcoesTotal = extraOpcoesTotal;

  // 7) Guarda os totais atuais no estado global
  window.__TOT__.laser = totalFinalSemTopo;
  window.__TOT__.topo  = totalTopo;
  window.__TOT__.drone = totalDrone;

  // 8) Atualiza os totais finais (sem passar args — usa estado global)
  atualizarTotaisFinais();
  syncAltimetricoDisponibilidade();
  atualizarPreviewsPDFPorArea();

}
function syncTopo10Hidden(){
  const chk = document.getElementById("chk_topo_mais10");
  const hid = document.getElementById("topo_aplicar_mais10");
  if (!chk || !hid) return;
  hid.value = chk.checked ? "1" : "0";
}


function calcularPrecoDrone(m2) {
  m2 = Number(m2 || 0);
  if (m2 <= 0) return 0;
  if (m2 <= 1000) return 250;      // até 1000 m² → 250 €
  if (m2 >= 50000) return 3000;    // 50 000 m² → 3000 €
  // interpolação linear entre 1000 e 50000 m²
  const ratio = (m2 - 1000) / (50000 - 1000);
  return 250 + ratio * (3000 - 250);
}

// Atualiza visualmente o preço base no cartão
function atualizarDronePrecoBase() {
  let totalM2 = 0;

  for (let i = 0; i < areaCount; i++) {
    totalM2 += parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;
  }

  const precoBase = calcularPrecoDrone(totalM2);

  const baseDrone = document.getElementById("preco_drone_base");
  if (baseDrone) setMoney(baseDrone, precoBase);
  
  return precoBase;
}

function toCents(eur){ return Math.round((Number(eur || 0)) * 100); }
function fromCents(c){ return (Number(c || 0)) / 100; }

// soma percentagens ativas (LOD + Modelo3D + BIM)
// devolve ex: 60 (para 60%)
function getPercentOpcoesAtivas(){
  let p = 0;

  document.querySelectorAll('select.select-opcao').forEach(sel => {
    const tipo = (sel.dataset.tipo || "").toLowerCase();
    const val  = sel.value;
    if (!val) return;

    if ((tipo === 'lod' || tipo.includes('niveldedetalhe')) && ajustesNivelDetalhe[val] !== undefined) {
      p += Number(ajustesNivelDetalhe[val]);
    }
    if ((tipo === 'modelo3d' || tipo.includes('modelo3d')) && ajustesModelo3D[val] !== undefined) {
      p += Number(ajustesModelo3D[val]);
    }
    if (tipo === 'bim' && ajustesBIM[val] !== undefined) {
      p += Number(ajustesBIM[val]);
    }
  });

  return p; // percent (pode ser negativo)
}


// ==================== TOTAIS FINAIS (NUNCA MAIS ZERAM) ====================
function atualizarTotaisFinais(_laser, _topo, _drone) {
  // Usa os parâmetros se vierem; caso contrário, usa o último estado calculado
  const laser = (typeof _laser === 'number') ? _laser : (window.__TOT__?.laser || 0);
  const topo  = (typeof _topo  === 'number') ? _topo  : (window.__TOT__?.topo  || 0);
  const drone = (typeof _drone === 'number') ? _drone : (window.__TOT__?.drone || 0);



  const descontoPercent = parseFloat(document.getElementById('desconto_global')?.value) || 0;

  const totalBruto = laser + topo + drone;


  // Desconto e IVA
  const valorDesconto     = totalBruto * (descontoPercent / 100);
  const totalComDesconto  = totalBruto - valorDesconto;
  const totalIVA          = totalComDesconto * 0.23;
  const totalFinal        = totalComDesconto + totalIVA;

  // ===============================
  // PREVIEW: Sub custos / Tempo / Margem
  // ===============================
  const totalSemIVA = totalComDesconto;                 // "total sem IVA" = já com desconto
  const deslocacao = Number(state?.totals?.deslocacao || 0);
  const custosExtra = getCustosExtra();

  // base para sub-custos: totalSemIVA - custosExtra - deslocacao
  let baseSub = totalSemIVA - custosExtra - deslocacao;
  if (baseSub < 0) baseSub = 0;

  const subCustos = (baseSub * 0.25) + 80;

  // tempo em dias inteiro (arredondamento normal)
  const tempoDias = Math.round(subCustos / 40);

  // margem liberta
  const margem = totalSemIVA - (subCustos + custosExtra) - deslocacao;

  // escrever nos spans
  const pvSub   = document.getElementById('pv_sub_custos');
  const pvTempo = document.getElementById('pv_tempo_dias');
  const pvMarg  = document.getElementById('pv_margem');

  if (pvSub)   pvSub.textContent = formatCurrency(eurToSelectedCurrency(subCustos), window.__CUR.code) + (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(subCustos, "EUR")})` : "");
  if (pvTempo) pvTempo.textContent = String(tempoDias);
  if (pvMarg)  pvMarg.textContent = formatCurrency(eurToSelectedCurrency(margem), window.__CUR.code) + (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(margem, "EUR")})` : "");


  // Atualiza DOM
  setMoney(document.getElementById('total_bruto'), totalBruto);
  setMoney(document.getElementById('total_com_desconto'), totalComDesconto);
  setMoney(document.getElementById('total_iva'), totalIVA);
  setMoney(document.getElementById('total_final'), totalFinal);

}

// 🔁 Garante que mexer no desconto recalcula SEM zerar
document.getElementById('desconto_global')?.addEventListener('input', () => {
  atualizarTotaisFinais(); // usa os valores guardados
});

function atualizarPreviewsPDFPorArea() {
  const IVA_PCT = 23;

  const d = calcularDeslocacao();
  const n = Math.max(areaCount, 1);

  // deslocação em cêntimos, distribuída com resto (igual ao típico no PDF)
  const totalDeslocC = toCents(d.totalDeslocacao);
  const baseShare = Math.floor(totalDeslocC / n);
  let resto = totalDeslocC - (baseShare * n);

  // percentagem total das opções (LOD/3D/BIM)
  const pctOpcoes = getPercentOpcoesAtivas(); // ex: 60
  // ✅ EXTRA TOTAL (LOD/3D/BIM) igual ao PDF:
  // percentagem aplica-se ao TOTAL BASE (soma de áreas) e depois é dividido por nº áreas
  const totalBaseEUR = window.__TOT__?.baseAreas || 0; // vamos guardar isto no passo 2
  const extraTotalC  = Math.round(toCents(totalBaseEUR) * (pctOpcoes / 100));

  // divide o extra total por N áreas com resto (igual ao PDF faz para deslocação)
  const extraShare = Math.floor(extraTotalC / n);
  let extraResto   = extraTotalC - (extraShare * n);

  for (let i = 0; i < areaCount; i++) {
    // 1) subtotal da área (exacto)
    const m2 = parseFloat(document.getElementById(`area_m2_${i}`)?.value) || 0;

    const inpSubManual = document.getElementById(`area_subtotal_manual_${i}`);
    const modoSubManual = inpSubManual?.dataset?.manualSubtotal === "true";

    let subtotalEUR = 0;
    if (modoSubManual) {
      subtotalEUR = hNum(inpSubManual.value);
    } else {
      const precoM2 = parseFloat(document.getElementById(`area_preco_${i}`)?.value) || 0;
      subtotalEUR = m2 * precoM2;
    }

    const subtotalC = toCents(subtotalEUR);

    // 2) extra por opções = percentagem sobre o subtotal DA ÁREA (não é dividido a meias)
    // ✅ extra por área (igual ao PDF): divide o extra total por nº áreas com resto
    const extraOpcoesC = extraShare + (extraResto > 0 ? 1 : 0);
    if (extraResto > 0) extraResto--;


    // 3) deslocação por área com resto
    const deslocC = baseShare + (resto > 0 ? 1 : 0);
    if (resto > 0) resto--;

    // 4) total s/ IVA e IVA em cêntimos
    const semIvaC = subtotalC + extraOpcoesC + deslocC;
    const ivaC = Math.round(semIvaC * (IVA_PCT / 100));
    const totalC = semIvaC + ivaC;

    const totalEUR = fromCents(totalC);

    const elPrev = document.getElementById(`pdf_prev_area_${i}`);
    if (elPrev) {
      elPrev.textContent =
        "PDF (c/ desloc. + IVA): " +
        formatCurrency(eurToSelectedCurrency(totalEUR), window.__CUR.code) +
        (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(totalEUR, "EUR")})` : "");

      elPrev.dataset.eur = String(round2(totalEUR));
    }
  }



    // preview topográfico (se existir)
    const topoInput = document.getElementById("preco_topografico");
    const topoPrev  = document.getElementById("pdf_prev_topo");

    if (topoInput && topoPrev) {
      const topoBase = hNum(topoInput.value);

      const chk10 = document.getElementById("chk_topo_mais10");
      const aplicar10 = chk10 ? chk10.checked : true;

      const topoFinal = topoBase * (aplicar10 ? 1.10 : 1.00);

      // ✅ em cêntimos (igual ao que queres no sistema todo)
      const topoSemIvaC = toCents(topoFinal);
      const topoIvaC    = Math.round(topoSemIvaC * 23 / 100);
      const topoTotalC  = topoSemIvaC + topoIvaC;

      const previewTopoEUR = fromCents(topoTotalC);

      topoPrev.textContent =
        "PDF (c/ desloc. + IVA): " +
        formatCurrency(eurToSelectedCurrency(previewTopoEUR), window.__CUR.code) +
        (window.__CUR.code !== "EUR" ? ` (≈ ${formatCurrency(previewTopoEUR, "EUR")})` : "");

      topoPrev.dataset.eur = String(round2(previewTopoEUR));
    } else if (topoPrev) {
      topoPrev.textContent = "PDF (c/ desloc. + IVA): —";
      topoPrev.dataset.eur = "0";
    }

}




// === ligação automática: cada vez que muda um select de opção ===
// sempre que muda um select de opções, recalcule
document.addEventListener('change', (e) => {
  if (e.target.classList.contains('select-opcao')) {
    if (!HYDRATING) aplicarRegraBIM();   // ✅ só depois de carregar
    atualizarAjustesTotais();
  }
});


// substituir a chamada do resumo geral
function atualizarResumo() {
  atualizarAjustesTotais();
}


function renumerarAreas() {
  const areaDivs = document.querySelectorAll('.area-item');
  areaCount = 0;

  areaDivs.forEach((div, i) => {

    // --- título e input nome ---
    const title = div.querySelector(`[id^="area_title_"]`);
    const nomeInput = div.querySelector(`[id^="area_nome_"]`);

    const novoDefault = `Área ${i + 1}`;

    if (title) {
      title.id = `area_title_${i}`;
    }

    // atualizar input de nome
    if (nomeInput) {
      const valorAtual = (nomeInput.value || "").trim();
      const eraManual = nomeInput.dataset.manual === "true";

      // reindexar id e name
      nomeInput.id = `area_nome_${i}`;
      nomeInput.name = `areas[${i}][nome]`;
      nomeInput.setAttribute('oninput', `onNomeAreaChange(${i})`);

      // se está vazio -> repõe default
      if (!valorAtual) {
        nomeInput.value = novoDefault;
        if (title) title.textContent = novoDefault;
        // continua não-manual
        delete nomeInput.dataset.manual;
      } else {
        // se não é manual e parece ser default antigo, atualiza para o novo default
        if (!eraManual && valorAtual.toLowerCase().startsWith("área")) {
          nomeInput.value = novoDefault;
          if (title) title.textContent = novoDefault;
        } else {
          // manual: mantém e mostra
          if (title) title.textContent = valorAtual;
        }
      }
    } else {
      // fallback: se não existir input, pelo menos mostra default
      if (title) title.textContent = novoDefault;
    }


    // atualizar IDs e eventos corretamente
    const m2Input = div.querySelector(`[id^="area_m2_"]`);
    const precoInput = div.querySelector(`[id^="area_preco_"]`);
    const subtotalSpan = div.querySelector(`[id^="subtotal_area_"]`);
    const ext = div.querySelector(`[id^="area_ext_"]`);
    const wrap = div.querySelector(`[id^="area_toggle_wrap_"]`);
    const badge = div.querySelector(`[id^="area_badge_"]`);

    if (wrap) wrap.id = `area_toggle_wrap_${i}`;
    if (badge) badge.id = `area_badge_${i}`;

    if (ext){
      ext.id = `area_ext_${i}`;
      ext.name = `areas[${i}][exterior]`;
      ext.setAttribute('onchange', `onToggleExterior(${i})`);
    }


    if (m2Input) {
      m2Input.id = `area_m2_${i}`;
      m2Input.setAttribute('oninput', `atualizarArea(${i})`);
    }

    if (precoInput) {
      precoInput.id = `area_preco_${i}`;
      precoInput.setAttribute('oninput', `atualizarSubtotalManual(${i})`);
    }

    if (subtotalSpan) subtotalSpan.id = `subtotal_area_${i}`;

    syncToggleUI(i);

    areaCount++;
  });
}


// === SERVIÇOS DEFAULT ===
const servicosDefault = [
  'Levantamento Laser Scan',
  'Levantamento Arquitetónico',
  'Plantas',
  'Cortes',
  'Alçados',
  'Nível de Detalhe',
  'Modelo 3D - Nível de Detalhe',
  'Nuvem de Pontos',
  'Vista Virtual 3D 360°',
  'Software para Consultar Dados'
];

// === ADICIONAR AUTOMATICAMENTE OS DEFAULT AO ABRIR A PÁGINA ===
window.addEventListener('DOMContentLoaded', () => {
  ensureLinhaDeslocacao();

  const isEdit = window.__EDIT__ && window.__EDIT__.proposta && window.__EDIT__.proposta.id;

  if (!isEdit) {
    // modo novo
    servicosDefault.forEach(nome => {
      const s = servicos.find(x => x.nome === nome);
      if (s) adicionarServico(s);
    });
  } else {
    // modo editar: adicionar serviços gravados
    (window.__EDIT__.servicos || []).forEach(sv => {
      const sid = Number(sv.id_servico || sv.id || 0);
      const s = servicos.find(x => Number(x.id) === sid);
      if (!s) return;

      adicionarServico(s);

      // aplicar opção (LOD/Modelo3D/BIM)
      if (sv.opcao_escolhida) {
        const card = document.querySelector(`.servico-card[data-id="${sid}"]`);
        const sel = card?.querySelector(`select.select-opcao`);
        if (sel) {
          sel.value = sv.opcao_escolhida;
          sel.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }

      // topográfico: preencher preço e checkbox +10 conforme BD
      const nomeNorm = normalizarTexto(s.nome);
      if (nomeNorm === "levantamentotopografico") {
        const inp = document.getElementById("preco_topografico");
        const chk = document.getElementById("chk_topo_mais10");

        const aplicar10DB = Number(window.__EDIT__?.proposta?.topo_aplicar_mais10 ?? 1) === 1;
        if (chk) chk.checked = aplicar10DB;
        if (typeof syncTopo10Hidden === "function") syncTopo10Hidden();

        // se na BD guardas o valor FINAL (com +10), converte para base
        const topoFinalBD = Number(window.__EDIT__?.proposta?.preco_levantamento_topografico ?? 0);
        if (inp && topoFinalBD > 0) {
          const base = aplicar10DB ? (topoFinalBD / 1.10) : topoFinalBD;
          inp.value = base.toFixed(2);
        }
      }
    });


    const AREAS = (window.__EDIT__.areas || []);
    // calcular “preço auto” da proposta antiga para comparar
    const totalM2Old = AREAS.reduce((acc, a) => acc + (Number(a.metros_quadrados) || 0), 0);
    const precoAutoOld = round2(calcularPrecoPorMetroGlobal(totalM2Old));

    AREAS.forEach((a, idx) => {
      document.getElementById('addAreaBtn').click();

      const nome = (a.nome_area || `Área ${idx+1}`);
      const m2 = Number(a.metros_quadrados || 0);
      const exterior = Number(a.exterior || 0) === 1;

      const precoBD = round2(Number(a.preco_m2 || 0));
      const subtotalBD = round2(Number(a.subtotal || 0));
      const subtotalCalc = round2(m2 * precoBD);

      const eraSubtotalManual = (m2 > 0 && Math.abs(subtotalBD - subtotalCalc) > 0.05);
      const eraPrecoManual = (!eraSubtotalManual && precoBD > 0 && Math.abs(precoBD - precoAutoOld) > 0.01);

      const inpNome = document.getElementById(`area_nome_${idx}`);
      const title = document.getElementById(`area_title_${idx}`);
      if (inpNome) { inpNome.value = nome; inpNome.dataset.manual = "true"; }
      if (title) title.textContent = nome;

      const inpM2 = document.getElementById(`area_m2_${idx}`);
      if (inpM2) inpM2.value = m2;

      const chkExt = document.getElementById(`area_ext_${idx}`);
      if (chkExt) chkExt.checked = exterior;
      syncToggleUI(idx);

      const inpPreco = document.getElementById(`area_preco_${idx}`);
      if (inpPreco) {
        inpPreco.value = precoBD.toFixed(2);
        if (eraPrecoManual) inpPreco.dataset.manual = "true";
        else delete inpPreco.dataset.manual;
      }

      // subtotal manual só se realmente era
      if (eraSubtotalManual) {
        const btn = document.getElementById(`btn_subtotal_manual_${idx}`);
        if (btn) btn.click(); // ativa modo manual
        const inpSub = document.getElementById(`area_subtotal_manual_${idx}`);
        if (inpSub) inpSub.value = subtotalBD.toFixed(2);
        onSubtotalManualInput(idx);
      } else {
        atualizarArea(idx);
      }
    });


    // drone checkboxes (se existirem)
    setTimeout(() => {
      const arr = window.__EDIT__.opcoes_drone || [];
      document.querySelectorAll('.chk-drone').forEach(chk => {
        chk.checked = arr.includes(chk.value);
      });
      atualizarAjustesTotais();
    }, 50);
  }

  setOpcaoDropdown(ID_ALTIMETRICO, true);
  syncAltimetricoDisponibilidade();

  initCurrencyUI();
  atualizarAjustesTotais();
  refreshAllMoney();
});




function calcularTotais() {
  let totalAreas = 0;
  for (let i = 0; i < areaCount; i++) {
    const m2Input = document.getElementById(`area_m2_${i}`);
    const precoInput = document.getElementById(`area_preco_${i}`);
    if (!m2Input || !precoInput) continue;

    const m2 = parseFloat(m2Input.value) || 0;
    const preco = parseFloat(precoInput.value) || 0;
    const subtotal = m2 * preco;
    totalAreas += subtotal;

    const subtotalSpan = document.getElementById(`subtotal_area_${i}`);
    if (subtotalSpan) subtotalSpan.innerText = subtotal.toFixed(2) + " €";
  }
}
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
  atualizarResumo(); // recalcula tudo (renderizações incluídas)
}


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
<script>
(function(){
  const inNome = document.querySelector('input[name="nome_cliente"]');
  const inEmail = document.querySelector('input[name="email_cliente"]');
  const inTel = document.querySelector('input[name="telefone_cliente"]');
  const inEmpresa = document.getElementById('empresa_nome');
  const inNif     = document.getElementById('empresa_nif');
  const hintBox = document.getElementById('clienteHint');
  const hintTxt = document.getElementById('clienteHintTxt');

  let lastEmail = "";

  function showHint(msg){
    if (!hintBox) return;
    hintTxt.textContent = msg;
    hintBox.style.display = "flex";
  }
  function hideHint(){
    if (!hintBox) return;
    hintBox.style.display = "none";
  }

  function setIfEmpty(input, value){
    if (!input) return;
    // ✅ só preenche automaticamente se estiver vazio (para manter “editável” sem chatear)
    if ((input.value || "").trim() === "" && (value || "").trim() !== "") {
      input.value = value;
    }
  }

  async function fetchClienteByEmail(email){
    const r = await fetch(`ajax_cliente.php?email=${encodeURIComponent(email)}`, { method: 'GET' });
    return await r.json();
  }

  function isValidEmail(email){
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  // 🔍 Pesquisa ao sair do campo (blur) + debounce enquanto escreve
  let t = null;
  function scheduleLookup(){
    clearTimeout(t);
    t = setTimeout(async () => {
      const email = (inEmail?.value || "").trim().toLowerCase();
      if (!email || !isValidEmail(email)) return;
      if (email === lastEmail) return;

      lastEmail = email;

      try {
        const res = await fetchClienteByEmail(email);
        if (!res.ok) return;

        if (res.found) {
          hideHint();
          const d = res.data || {};

          // ✅ Preenche apenas se estiver vazio (mantém editável)
          setIfEmpty(inNome, d.nome || "");
          setIfEmpty(inTel, d.telefone || "");
          setIfEmpty(inEmpresa, d.empresa_nome || "");
          setIfEmpty(inNif, d.empresa_nif || "");

          // (opcional) Se quiseres, podes dar um “ok” visual
          // showHint("Cliente encontrado. Podes editar os campos se precisares.");
          // setTimeout(hideHint, 1800);
        } else {
          showHint("Cliente não encontrado. Preenche Empresa e NIF para guardar na ficha.");
          // podes tornar obrigatório quando não existe:
          if (inEmpresa) inEmpresa.required = true;
          if (inNif) inNif.required = true;
        }
      } catch (e) {
        // silêncio (ou mostra msg se quiseres)
      }
    }, 450);
  }

  if (inEmail) {
    inEmail.addEventListener('input', scheduleLookup);
    inEmail.addEventListener('blur', scheduleLookup);
  }

  // se o user começar a escrever empresa/nif manualmente, não forces required/avisos
  [inEmpresa, inNif].forEach(el => {
    if (!el) return;
    el.addEventListener('input', () => {
      // deixa na boa
    });
  });
})();
</script>

</body>
</html>
