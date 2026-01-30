<?php
include 'protecao.php';
// Se for cliente (ID 6) → vistas simplificadas
$isCliente = ($_SESSION['nivel_acesso'] == 6);
$isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;
require_once __DIR__ . '/../conexao/conexao.php';
require_once __DIR__ . '/../conexao/funcoes.php';
include 'cabecalho.php';
$id = $_GET['id'] ?? 0;

// Buscar proposta principal
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
$stmt->execute([$id]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);



$sqlProj = $pdo->prepare("SELECT id FROM projetos WHERE proposta_id = ?");
$sqlProj->execute([$proposta['id']]);
$projeto = $sqlProj->fetch(PDO::FETCH_ASSOC);

// Buscar versões anteriores (propostas com o mesmo id_proposta_origem)
$versoesAnteriores = [];

// ===== HISTÓRICO COMPLETO =====

// Descobre o ID da proposta original:
$origem = $proposta['numero_projeto'] ?: $proposta['id'];

// Busca TODAS as versões (incluindo a própria)
$stmt = $pdo->prepare("
    SELECT id, codigo, data_emissao, estado, total_final, criado_em
    FROM propostas
    WHERE numero_projeto = :origem and id != :id
    ORDER BY id ASC
");
$stmt->execute(['origem' => $origem, 'id' => $id]);
$versoesAnteriores = $stmt->fetchAll(PDO::FETCH_ASSOC);


$moeda = $proposta['codigo_pais'] ?: 'EUR';

function money_span($eurValue, $tag = 'span', $extraClass = '') {
  $v = is_numeric($eurValue) ? (float)$eurValue : 0;
  $cls = trim("money $extraClass");
  return "<$tag class=\"$cls\" data-eur=\"" . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . "\">" 
        . number_format($v, 2, ',', '.') . " €</$tag>";
}



// ---- Datas seguras (sem warnings) ----
$emissaoDT = !empty($proposta['data_emissao'])
  ? new DateTime($proposta['data_emissao'])
  : (!empty($proposta['criado_em']) ? new DateTime($proposta['criado_em']) : new DateTime('now'));

$vencimentoDT = !empty($proposta['data_vencimento'])
  ? new DateTime($proposta['data_vencimento'])
  : (clone $emissaoDT)->modify('+60 days');

$emissao_fmt = $emissaoDT->format('d/m/Y');
$vencimento_fmt = $vencimentoDT->format('d/m/Y');


if (!$proposta) {
    die("Proposta não encontrada.");
}

// Buscar áreas e serviços relacionados
$stmt = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ?");
$stmt->execute([$id]);
$areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT sp.*, s.nome AS nome_base FROM servicos_proposta sp 
                       LEFT JOIN servicos_produtos s ON sp.id_servico = s.id
                       WHERE sp.id_proposta = ?");
$stmt->execute([$id]);
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Soma dos subtotais das áreas (base usada para ajustes)
$preco_base_areas = 0;
foreach ($areas as $a) {
    $preco_base_areas += floatval($a['subtotal']);
}


// Função de formatação
function euro($valor) {
    return number_format($valor, 2, ',', '.') . ' €';
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../img/icon.png">
  <title>Proposta <?=htmlspecialchars($proposta['codigo'])?> | SupremExpansion</title>
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/ver_proposta.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<br><br><br><br><br>

<main class="container">
  <h1>
  Proposta <?= htmlspecialchars($proposta['codigo']) ?>
  <a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>
</h1>




<div class="acoes-proposta">

<?php if (!$isCliente && !$isContabilista): ?>   <!-- 🔒 CLIENTE NÃO VÊ AÇÕES -->

    <?php if ($proposta['estado'] === 'pendente' || $proposta['estado'] === 'arquivada'): ?>
        <a href="#" class="btn-acao verde"
          onclick="abrirPopupEstado('adjudicar', <?= (int)$proposta['id'] ?>); return false;">
          <i class="bi bi-check-circle-fill"></i> Adjudicar
        </a>

        <a href="#" class="btn-acao vermelho"
          onclick="abrirPopupEstado('cancelar', <?= (int)$proposta['id'] ?>); return false;">
          <i class="bi bi-x-circle-fill"></i> Cancelar
        </a>


    <?php elseif ($proposta['estado'] === 'Adjudicada' && !$projeto): ?>
        <p style="color:#777;"><i class="bi bi-lightbulb"></i> Proposta adjudicada — ainda sem projeto criado.</p>

    <?php elseif ($projeto): ?>
        <a href="ver_projeto.php?id=<?= $projeto['id'] ?>" class="btn-acao"><i class="bi bi-folder2-open"></i> Ver Projeto</a>
    <?php endif; ?>

<?php else: ?>

    <!-- 👁 O CLIENTE APENAS VÊ ISTO -->
    <?php if ($projeto): ?>
        <a href="ver_projeto.php?id=<?= $projeto['id'] ?>" class="btn-acao"><i class="bi bi-folder2-open"></i> Ver Projeto</a>
    <?php endif; ?>

<?php endif; ?>

</div>



  <br><br>
  <?php if (!empty($proposta['imagem'])): ?>
    <div class="imagem-proposta">
      <img src="../uploads/propostas/<?= htmlspecialchars($proposta['imagem']) ?>" 
          alt="Imagem da Proposta">
    </div>
  <?php endif; ?>


  <!-- 🧍 DADOS DO CLIENTE -->
  <section>
    <h2>Dados do Cliente</h2>
    <p><strong>Nome:</strong> <?=htmlspecialchars($proposta['nome_cliente'])?></p>
    <p><strong>Email:</strong> <?=htmlspecialchars($proposta['email_cliente'])?></p>
    <p><strong>Telefone:</strong> <?=htmlspecialchars($proposta['telefone_cliente'])?></p>
  </section>

  <!-- 🏗️ DETALHES DA OBRA -->
  <section>
    <h2>Detalhes da Obra</h2>
    <p><strong>Nome da Obra:</strong> <?=htmlspecialchars($proposta['nome_obra'])?></p>
    <p><strong>Endereço:</strong> <?=htmlspecialchars($proposta['endereco_obra'])?></p>
    <p><strong>Moeda:</strong> <span id="moeda_code"><?=htmlspecialchars($moeda)?></span></p>
    <p><strong>Distância:</strong> <?=number_format($proposta['distancia_km'], 2, ',', '.') ?> km</p>
    <p><strong>Data de Emissão:</strong> <?= $emissao_fmt ?></p>
    <p><strong>Data de Vencimento:</strong> <?= $vencimento_fmt ?></p>
  </section>
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

  <!-- 🧾 ÁREAS -->
  <section>
    <h2>Áreas</h2>

    <?php if (empty($areas)): ?>
        <p>Sem áreas atribuídas a esta proposta.</p>

    <?php else: ?>

        <?php if (!$isCliente): ?>
            <!-- VERSÃO INTERNA (todas as colunas) -->
            <table>
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>m²</th>
                        <th>Preço/m²</th>
                        <th>Subtotal</th>
                        <th>Deslocação</th>
                        <th>Total (s/ IVA)</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($areas as $a): 
                    $total_area = $a['subtotal'] + $a['valor_deslocacao'];
                ?>
                    <tr>
                        <td><?=htmlspecialchars($a['nome_area'])?></td>
                        <td><?=number_format($a['metros_quadrados'], 2, ',', '.')?></td>
                        <td><?= money_span($a['preco_m2']) ?></td>

                        <td><?= money_span($a['subtotal']) ?></td>
                        <td><?= money_span($a['valor_deslocacao']) ?></td>
                        <td><strong><?= money_span($total_area, 'span', 'money-strong') ?></strong></td>

                        <?php $isExt = !empty($a['exterior']); ?>
                        <td>
                          <span class="badge-area <?= $isExt ? 'exterior' : '' ?>">
                            <?= $isExt ? 'Exterior' : 'Interior' ?>
                          </span>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- VERSÃO CLIENTE (muito simplificada) -->
            <table>
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>m²</th>
                        <th>Subtotal (s/ IVA)</th>
                        <th>Total c/ IVA</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($areas as $a):
                    $subtotal = $a['subtotal'] + $a['valor_deslocacao'];
                    $total = $subtotal * 1.23;
                ?>
                    <tr>
                        <td><?=htmlspecialchars($a['nome_area'])?></td>
                        <td><?=number_format($a['metros_quadrados'])?></td>
                        <td><?= money_span($subtotal) ?></td>
                        <td><strong><?= money_span($total, 'span', 'money-strong') ?></strong></td>

                        <?php $isExt = !empty($a['exterior']); ?>
                        <td>
                          <span class="badge-area <?= $isExt ? 'exterior' : '' ?>">
                            <?= $isExt ? 'Exterior' : 'Interior' ?>
                          </span>
                        </td>

                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    <?php endif; ?>

  </section>

  <section>
    <h2>Serviços Incluídos</h2>

    <?php if (empty($servicos)): ?>
        <p>Sem serviços registados.</p>
    <?php else: ?>

        <?php
        // Base real (preço do levantamento arquitetónico)
        $baseAreas = floatval($proposta['preco_levantamento_arquitetonico']);
        ?>
        

        <table>
            <thead>
                <tr>
                    <th>Serviço</th>
                    <th>Opção</th>

                    <?php if (!$isCliente): ?>
                        <th>Ajuste (%)</th>
                        <th>Acrescentou (€)</th>
                    <?php endif; ?>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($servicos as $s): ?>

                <?php
                $idServico = intval($s['id_servico']);
                $nome = strtolower($s['nome_base']);

                // Converter opções do drone se forem JSON
                if ($idServico == 13 && !empty($s['opcao_escolhida'])) {
                    $json = json_decode($s['opcao_escolhida'], true);
                    $opcao = is_array($json) ? implode(", ", $json) : $s['opcao_escolhida'];
                } else {
                    $opcao = $s['opcao_escolhida'] ?: "—";
                }

                // Se o utilizador for cliente → não calcular ajustes
                if ($isCliente) {
                    $percentagem = null;
                    $valor = null;

                } else {

                    // =============================
                    // Cálculos internos (originais)
                    // =============================

                    $percentagem = 0;
                    $valor = 0;

                    if (strpos($nome, "arqu") !== false) {
                        $percentagem = 0;
                        $valor = floatval($proposta['preco_levantamento_arquitetonico']);
                    }
                    elseif (strpos($nome, "topo") !== false) {
                        $percentagem = 10;
                        $valor = floatval($proposta['preco_levantamento_topografico']);
                    }
                    elseif (strpos($nome, "drone") !== false) {
                        $percentagem = 0;
                        $valor = floatval($proposta['preco_drone']);
                    }
                    elseif (strpos($nome, "geo") !== false) {
                        $percentagem = 0;
                        $valor = floatval($s['preco_servico']);
                    }
                    elseif ($idServico == 16) {
                        $percentagem = 0;
                        $valor = floatval($s['preco_servico']); // vem da tabela servicos_proposta
                    }

                    elseif ($idServico == 6) {
                        if ($opcao == "1:200") $percentagem = -10;
                        if ($opcao == "1:100") $percentagem = 0;
                        if ($opcao == "1:50")  $percentagem = 60;
                        if ($opcao == "1:20")  $percentagem = 130;
                        if ($opcao == "1:1")   $percentagem = 300;
                        $valor = $baseAreas * ($percentagem / 100);
                    }
                    elseif ($idServico == 8) {
                        if ($opcao == "1:200") $percentagem = 0;
                        if ($opcao == "1:100") $percentagem = 60;
                        if ($opcao == "1:50")  $percentagem = 130;
                        if ($opcao == "1:20")  $percentagem = 300;
                        $valor = $baseAreas * ($percentagem / 100);
                    }
                    elseif ($idServico == 10) {
                        if ($opcao == "Bricscad") $percentagem = 20;
                        if ($opcao == "Archicad") $percentagem = 20;
                        if ($opcao == "Revit")    $percentagem = 20;
                        $valor = $baseAreas * ($percentagem / 100);
                    }
                }
                ?>

                <tr>
                    <td><?= htmlspecialchars($s['nome_base'] ?: $s['nome_servico']) ?></td>
                    <td><?= htmlspecialchars($opcao) ?></td>

                    <?php if (!$isCliente): ?>
                        <td>
                            <?= $percentagem === null 
                                ? "—" 
                                : number_format($percentagem, 2, ',', '.') . "%" ?>
                        </td>
                        <td><strong><?= money_span($valor, 'span', 'money-strong') ?></strong></td>

                    <?php endif; ?>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</section>



<?php
// =====================================================
//   CALCULAR EXTRA DOS SERVIÇOS COM OPÇÕES (LOD/3D/BIM)
// =====================================================

$valorExtraOpcoes = 0;
$valorDeslocacao = floatval($proposta['preco_deslocacao_total'] ?? 0);
$baseAreas = floatval($proposta['preco_levantamento_arquitetonico']);

// percentagens tal como no sistema
$ajustesLOD = [
    "1:200" => -10,
    "1:100" => 0,
    "1:50"  => 60,
    "1:20"  => 130,
    "1:1"   => 300
];

$ajustes3D = [
    "1:200" => 0,
    "1:100" => 60,
    "1:50"  => 130,
    "1:20"  => 300
];

$ajustesBIM = [
    "Bricscad" => 20,
    "Archicad" => 20,
    "Revit"    => 20
]; 


foreach ($servicos as $s) {

    $id   = (int)$s['id_servico'];
    $opc  = $s['opcao_escolhida'] ?? null;

    // LOD
    if ($id === 6 && $opc && isset($ajustesLOD[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustesLOD[$opc] / 100);
    }

    // Modelo 3D
    if ($id === 8 && $opc && isset($ajustes3D[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustes3D[$opc] / 100);
    }

    // BIM
    if ($id === 10 && $opc && isset($ajustesBIM[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustesBIM[$opc] / 100);
    }
}
?>


  <!-- 📌 LEVANTAMENTOS E PREÇOS -->
  <section>
      <h2>Levantamentos e Preços</h2>

      <table>
          <thead>
              <tr>
                  <th>Tipo de Levantamento</th>
                  <th>Preço (€)</th>
              </tr>
          </thead>
          <tbody>
              <tr>
                <td>Levantamento Laser Scan</td>
                <td><?= money_span($proposta['preco_levantamento_arquitetonico'] + $valorExtraOpcoes + $valorDeslocacao) ?></td>

              </tr>

              <tr>
                  <td>Levantamento Topográfico</td>
                  <td><?= money_span($proposta['preco_levantamento_topografico']) ?></td>
              </tr>
              <tr>
                <td>
                    Levantamento Drone

                    <?php
                      $opcoesDrone = [];

                      foreach ($servicos as $s) {
                          $nome = strtolower($s['nome_base'] ?? '');

                          if (strpos($nome, "drone") !== false) {

                              if (!empty($s['opcao_escolhida'])) {

                                  // tentar decodificar JSON
                                  $decoded = json_decode($s['opcao_escolhida'], true);

                                  if (is_array($decoded)) {
                                      // é JSON → adicionar cada opção
                                      foreach ($decoded as $opt) {
                                          $opcoesDrone[] = $opt;
                                      }
                                  } else {
                                      // fallback caso não seja JSON
                                      $opcoesDrone[] = $s['opcao_escolhida'];
                                  }
                              }
                          }
                      }

                      if (!empty($opcoesDrone)) {
                          echo "<br><small style='color:#555;'>Opções: <b>" . implode(", ", $opcoesDrone) . "</b></small>";
                      } else {
                          echo "<br><small style='color:#888;'>Sem opções selecionadas</small>";
                      }
                    ?>

                </td>

                <td><?= money_span($proposta['preco_drone']) ?></td>
              </tr>

          </tbody>
      </table>
  </section>


  <!-- 💰 TOTAIS -->
  <section>
    <h2>Totais da Proposta</h2>
    <table>
      <tr><th>Total Áreas:</th><td><?= money_span($proposta['preco_levantamento_arquitetonico']) ?></td></tr>
      <tr><th>Topografia:</th><td><?= money_span($proposta['preco_levantamento_topografico']) ?></td></tr>
      <tr><th>Drone:</th><td><?= money_span($proposta['preco_drone']) ?></td></tr>

      <?php if (!$isCliente): ?>
      <tr><th>Deslocação:</th><td><?= money_span($proposta['preco_deslocacao_total']) ?></td></tr>
      <?php endif; ?>

      <tr><th>Total Bruto:</th><td><?= money_span($proposta['total_bruto']) ?></td></tr>
      <tr>
        <th>
          Desconto 
          (<?= rtrim(rtrim(number_format((float)$proposta['desconto_percentagem'], 2, ',', ''), '0'), ',') ?>%)
        </th>
        <td>-<?= money_span($proposta['valor_desconto'] ?? 0) ?></td>
      </tr>

      
      <tr><th>Total Líquido:</th><td><?= money_span($proposta['total_liquido']) ?></td></tr>
      <tr><th>IVA (23%)</th><td><?= money_span($proposta['total_iva']) ?></td></tr>
      <tr><th><strong>Total Final</strong></th><td><strong><?= money_span($proposta['total_final'], 'span', 'money-strong') ?></strong></td></tr>
    </table>
  </section>

  <!-- 📋 ESTADO -->
  <section>
    <h2>Estado da Proposta</h2>
    <p><span class="badge <?=htmlspecialchars($proposta['estado'])?>"><?=htmlspecialchars($proposta['estado'])?></span></p>
    <br>
    <p><strong>Renegociações:</strong> <?=htmlspecialchars($proposta['vezes_renegociada'])?></p>
  </section>
<?php if (!empty($versoesAnteriores)): ?>
  <hr>
  <h2><i class="bi bi-journal-text"></i> Histórico de Propostas Anteriores</h2>

  <div class="table-scroll">
    <table class="tabela-historico">
      <thead>
        <tr>
          <th>Código</th>
          <th>Data</th>
          <th>Estado</th>
          <th>Total (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($versoesAnteriores as $antiga): ?>
          <tr class="clickable-row" data-href="ver_proposta.php?id=<?= $antiga['id'] ?>">
            <td><?= htmlspecialchars($antiga['codigo']) ?></td>
            <td><?= date('d/m/Y', strtotime($antiga['criado_em'])) ?></td>
            <td>Cancelado</td>
            <td><?= money_span($antiga['total_final'] ?? 0) ?></td>

          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>





  <!-- 🗒️ OBSERVAÇÕES -->
  <?php if (!empty($proposta['observacoes'])): ?>
  <section>
    <h2>Observações</h2>
    <p><?=nl2br(htmlspecialchars($proposta['observacoes']))?></p>
  </section>
  <?php endif; ?>
  <hr>
<section class="pagamento-inicial">
  <h2><i class="bi bi-cash-stack"></i> Pagamento Inicial</h2>

  <?php 
    $total = floatval($proposta['total_final']);
    $estado = strtolower(trim($proposta['estado']));
    $valor_padrao = $total * 0.5;
    $valor_guardado = floatval($proposta['pagamento_inicial_valor'] ?? 0);
    $valor_inicial = $valor_guardado > 0 ? $valor_guardado : $valor_padrao;
    $pago = intval($proposta['pagamento_inicial_pago'] ?? 0);
  ?>

  <?php if ($estado !== 'adjudicada'): ?>
    <p style="color:#a30101; font-weight:600;"><i class="bi bi-exclamation-triangle-fill"></i> O pagamento inicial só estará disponível após a proposta ser adjudicada.</p>

  <?php elseif ($pago): ?>
    <!-- ✅ Pagamento já efetuado -->
     <br>
    <div class="pagamento-box">
      <p><b>Valor inicial pago:</b> <?= money_span($valor_inicial) ?></p>

      <br>
      <p><b>Valor restante a pagar:</b> <?= number_format($total - $valor_inicial, 2, ',', '.') ?> €</p>
      <br>
      <p style="color:green; font-weight:600;"><i class="bi bi-check-circle-fill"></i> Pagamento confirmado em <?= date('d/m/Y', strtotime($proposta['pagamento_data'])) ?></p>
    </div>

  <?php else: ?>
    <!-- 💸 Opção para definir e confirmar o pagamento -->
    <form action="update_pagamento.php" method="POST">
    <input type="hidden" name="id" value="<?= $proposta['id'] ?>">

    <div class="pagamento-box">
      <label>Valor do Pagamento Inicial (€)</label>
      <div class="campo-pagamento">
        <input 
          type="number" 
          name="pagamento_inicial_valor" 
          id="pagamento_inicial_valor"
          step="0.01" 
          value="<?= number_format($valor_inicial, 2, '.', '') ?>"
          oninput="atualizarRestante(<?= $total ?>)">
        <button type="button" class="btn-reset" onclick="redefinirPagamentoPadrao(<?= $valor_padrao ?>)">Redefinir</button>
      </div>
      <br>

      <p><b>Valor Total da Proposta:</b> <span id="valor_total"><?= number_format($total, 2, ',', '.') ?></span> €</p>
      <br>
      <p><b>Restante a pagar:</b> <span id="valor_restante"><?= number_format($total - $valor_inicial, 2, ',', '.') ?></span> €</p>
      <br>
      <?php if (!$isContabilista): ?>
      <button type="button" class="btn-pagamento-confirmar" onclick="abrirPopupPagamento()">
        <span class="btn-ico"><i class="bi bi-shield-check"></i></span>
        <span class="btn-texto">
          <span class="btn-titulo">Confirmar Pagamento Inicial</span>
          <span class="btn-sub">Marca como pago e guarda a data</span>
        </span>
      </button>

      <?php endif; ?>

    </div>
  </form>

  <?php endif; ?>
</section>



<script>

function popupMensagem(texto, callback = null) {
  document.getElementById("popup-message").innerText = texto;
  document.getElementById("popup-overlay").style.display = "flex";

  const okBtn = document.getElementById("popup-ok");
  okBtn.onclick = () => {
    document.getElementById("popup-overlay").style.display = "none";
    if (callback) callback();
  };
}

document.querySelectorAll('.clickable-row').forEach(row => {
  row.addEventListener('click', e => {
    if (!e.target.closest('button') && !e.target.closest('a')) {
      window.location.href = row.dataset.href;
    }
  });
});
function redefinirPagamentoPadrao(valor) {
  const campo = document.getElementById('pagamento_inicial_valor');
  campo.value = valor.toFixed(2);
  atualizarRestante(<?= $total ?>);
  popupMensagem("Valor redefinido para 50% do total da proposta.");
}

function atualizarRestante(total) {
  const campoValor = document.getElementById('pagamento_inicial_valor');
  const spanRestante = document.getElementById('valor_restante');
  const valor = parseFloat(campoValor.value) || 0;

  const restante = Math.max(total - valor, 0);
  spanRestante.textContent = restante.toFixed(2).replace('.', ',');
}

</script>


</main>
<?php
include 'rodape.php';
?>
<!-- Pop-up Customizado -->
<div id="popup-overlay" class="popup-overlay" style="display:none;">
  <div class="popup-box">
    <img src="../img/logo.png" class="popup-logo" alt="Logo">
    <p id="popup-message"></p>
    <button id="popup-ok">OK</button>
  </div>
</div>
<!-- 🔥 POPUP CUSTOMIZADO DE CONFIRMAÇÃO DE PAGAMENTO -->
<div id="popupPagamento" class="popup-overlay">
  <div class="popup-box">
      <img src="../img/logo.png" class="popup-logo">

      <h3>Confirmar Pagamento Inicial</h3>
      <p>Tem a certeza que o pagamento inicial foi efetuado?</p>

      <div class="popup-buttons">
          <button class="btn-confirmar" onclick="confirmarPagamento()">Confirmar</button>
          <button class="btn-cancelar" onclick="fecharPopupPagamento()">Cancelar</button>
      </div>
  </div>
</div>
<!-- 🔥 POPUP CONFIRMAÇÃO ADJUDICAR/CANCELAR -->
<div id="popupEstado" class="popup-overlay" style="display:none;">
  <div class="popup-box">
    <img src="../img/logo.png" class="popup-logo" alt="Logo">

    <h3 id="popupEstadoTitulo">Confirmar ação</h3>
    <p id="popupEstadoTexto">Tem a certeza?</p>

    <div class="popup-buttons">
      <button class="btn-confirmar" id="popupEstadoConfirmar">Confirmar</button>
      <button class="btn-cancelar" onclick="fecharPopupEstado()">Cancelar</button>
    </div>
  </div>
</div>
<script>
function abrirPopupEstado(acao, id) {
  const overlay = document.getElementById('popupEstado');
  const titulo  = document.getElementById('popupEstadoTitulo');
  const texto   = document.getElementById('popupEstadoTexto');
  const btnOk   = document.getElementById('popupEstadoConfirmar');

  if (!overlay || !titulo || !texto || !btnOk) return;

  const isCancel = (acao === 'cancelar');

  titulo.textContent = isCancel ? "Confirmar Cancelamento" : "Confirmar Adjudicação";
  texto.textContent  = isCancel
    ? "Tem a certeza que quer cancelar esta proposta? Esta ação pode afetar o histórico."
    : "Tem a certeza que quer adjudicar esta proposta?";

  // Troca o estilo do botão confirmar conforme a ação
  btnOk.classList.toggle('danger', isCancel);

  btnOk.onclick = () => {
    window.location.href = `atualizar_estado_proposta.php?id=${id}&acao=${acao}`;
  };

  overlay.style.display = 'flex';
}

function fecharPopupEstado() {
  const overlay = document.getElementById('popupEstado');
  if (overlay) overlay.style.display = 'none';

  // opcional: limpar estado do botão
  const btnOk = document.getElementById('popupEstadoConfirmar');
  if (btnOk) btnOk.classList.remove('danger');
}

// fecha ao clicar fora da caixa (opcional mas fica nice)
document.addEventListener('click', (e) => {
  const overlay = document.getElementById('popupEstado');
  if (!overlay || overlay.style.display !== 'flex') return;
  if (e.target === overlay) fecharPopupEstado();
});
</script>

<script>
function abrirPopupPagamento() {
    document.getElementById('popupPagamento').style.display = 'flex';
}

function fecharPopupPagamento() {
    document.getElementById('popupPagamento').style.display = 'none';
}

function confirmarPagamento() {
    const form = document.querySelector('form[action="update_pagamento.php"]');

    let input = document.createElement("input");
    input.type = "hidden";
    input.name = "pago";
    input.value = "1";
    form.appendChild(input);

    form.submit();
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
(() => {
  'use strict';

  const moedaCode = (document.getElementById('moeda_code')?.textContent || 'EUR').trim().toUpperCase();

  const CURRENCY_META = {
    EUR: { symbolFallback: "€", decimals: 2, defaultRateToEUR: 1 },
    USD: { symbolFallback: "$", decimals: 2, defaultRateToEUR: 0.85 },
    GBP: { symbolFallback: "£", decimals: 2, defaultRateToEUR: 1.15 },
    JPY: { symbolFallback: "¥", decimals: 0, defaultRateToEUR: 0.0055 },
  };

  const CUR = {
    code: CURRENCY_META[moedaCode] ? moedaCode : 'EUR',
    rateToEUR: (CURRENCY_META[moedaCode]?.defaultRateToEUR || 1), // 1 [code] = X EUR
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

  function eurToSelected(eurAmount) {
    const eur = Number(eurAmount || 0);
    if (CUR.code === "EUR") return eur;
    if (CUR.rateToEUR <= 0) return 0;
    return eur / CUR.rateToEUR;
  }

  function ensureSubline(el) {
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

  function renderOne(el) {
    const eur = round2(parseFloat(el.dataset.eur || "0") || 0);

    // limpar e desenhar "principal"
    el.innerHTML = "";
    const main = document.createElement("div");
    main.className = "money-main";
    main.textContent = formatCurrency(eurToSelected(eur), CUR.code);
    el.appendChild(main);

    // sublinha "≈ EUR"
    if (CUR.code !== "EUR") {
      const sub = ensureSubline(el);
      sub.textContent = "≈ " + formatCurrency(eur, "EUR");
    }
  }

  function refreshAll() {
    document.querySelectorAll(".money[data-eur]").forEach(renderOne);

    // UI taxa
    const taxaBox = document.getElementById("taxaBox");
    const taxaLabel = document.getElementById("taxaMoedaLabel");
    const taxaInput = document.getElementById("taxaParaEUR");

    if (CUR.code === "EUR") {
      if (taxaBox) taxaBox.style.display = "none";
    } else {
      if (taxaBox) taxaBox.style.display = "block";
      if (taxaLabel) taxaLabel.textContent = CUR.code;
      if (taxaInput) taxaInput.value = String(CUR.rateToEUR);
    }
  }

  // listeners taxa
  const taxaInput = document.getElementById("taxaParaEUR");
  if (taxaInput) {
    taxaInput.addEventListener("input", () => {
      const v = parseFloat(taxaInput.value);
      if (!isNaN(v) && v > 0) CUR.rateToEUR = v;
      refreshAll();
    });
  }

  refreshAll();
})();
</script>

</body>
</html>
