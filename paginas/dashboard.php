<?php
include 'protecao.php';
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

// === Contadores principais ===
$totalProjetos = $pdo->query("SELECT COUNT(*) FROM projetos")->fetchColumn();
$totalPropostas = $pdo->query("SELECT COUNT(*) FROM propostas")->fetchColumn();
$totalClientes = $pdo->query("SELECT COUNT(DISTINCT email_cliente) FROM propostas")->fetchColumn();
$totalFuncionarios = $pdo->query("SELECT COUNT(*) FROM utilizadores WHERE acesso_id < 6")->fetchColumn();

// === Total Faturado (real) ===
$sqlFat = "
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
";
$totalFaturado = (float)($pdo->query($sqlFat)->fetchColumn() ?? 0);

// === Faturação dos últimos 12 meses ===
$sqlGrafico = "
  SELECT 
    DATE_FORMAT(pj.data_inicio, '%Y-%m') AS periodo,
    SUM(
      CASE 
        WHEN pj.estado = 'Concluído' THEN pj.valor_total
        WHEN pr.pagamento_inicial_pago = 1 THEN 
          COALESCE(pr.pagamento_inicial_valor, pr.total_final * 0.5)
        ELSE 0
      END
    ) AS valor
  FROM projetos pj
  JOIN propostas pr ON pr.id = pj.proposta_id
  WHERE pj.data_inicio >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY DATE_FORMAT(pj.data_inicio, '%Y-%m')
  ORDER BY periodo
";
$dados = $pdo->query($sqlGrafico)->fetchAll(PDO::FETCH_ASSOC);
$labels = json_encode(array_column($dados, 'periodo'));
$valores = json_encode(array_map('floatval', array_column($dados, 'valor')));

// === Top 5 Funcionários (por m² concluídos) ===
$sqlTopFunc = "
  SELECT 
    u.id,
    u.nome,
    SUM(ap.metros_quadrados) AS total_m2
  FROM projetos_funcionarios pf
  JOIN utilizadores u ON u.id = pf.funcionario_id
  JOIN projetos p ON p.id = pf.projeto_id
  LEFT JOIN propostas prop ON prop.id = p.proposta_id
  LEFT JOIN areas_proposta ap ON ap.id_proposta = prop.id
  WHERE p.estado = 'Concluído'
  GROUP BY u.id
  ORDER BY total_m2 DESC
  LIMIT 5
";
 
$topFunc = $pdo->query($sqlTopFunc)->fetchAll(PDO::FETCH_ASSOC);

// === Últimas 5 Propostas Adjudicadas ===
$sqlUltimas = "
  SELECT id, codigo, nome_cliente, total_final, data_emissao
  FROM propostas
  WHERE estado='Adjudicada'
  ORDER BY data_emissao DESC
  LIMIT 5
";

$ultimas = $pdo->query($sqlUltimas)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Dashboard | SupremeXpansion</title>
<link rel="stylesheet" href="../css/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">


</head>
<body>
    <br><br>
<main>
  <h1>Dashboard Geral</h1>
  <br>
  <div class="filtro-cards">
    <button class="fc-btn" data-range="dia">Dia</button>
    <button class="fc-btn ativo" data-range="semana">Semana</button>
    <button class="fc-btn" data-range="mes">Mês</button>
    <button class="fc-btn" data-range="ano">Ano</button>
    <button class="fc-btn" data-range="sempre">Sempre</button>
  </div>

  <!--  Cards principais -->
  <div class="cards">
    <div class="card"><h3>Projetos</h3><p id="cardProjetos"><?= $totalProjetos ?></p></div>
    <div class="card"><h3>Propostas</h3><p id="cardPropostas"><?= $totalPropostas ?></p></div>
    <div class="card"><h3>Clientes</h3><p id="cardClientes"><?= $totalClientes ?></p></div>
    <div class="card"><h3>Funcionários</h3><p id="cardFuncionarios"><?= $totalFuncionarios ?></p></div>
    <div class="card"><h3>Total Faturado</h3><p id="cardFaturado">€<?= number_format($totalFaturado, 2, ',', '.') ?></p></div>
  </div>

  <div class="chart-box">
  <h3>Faturação</h3>
  <br>
  <div class="filtros">
    <button class="filtro ativo" data-tipo="dia">Dia</button>
    <button class="filtro" data-tipo="semana">Semana</button>
    <button class="filtro" data-tipo="mes">Mês</button>
    <button class="filtro" data-tipo="ano">Ano</button>
  </div>
  <!-- 📈 Gráfico Faturação -->
  <div class="chart-box">
    <h3>Faturação dos últimos 12 meses</h3>
    <canvas id="graficoFaturacao"></canvas>
  </div>
<script>
let grafico;
const ctx = document.getElementById('graficoFaturacao');

function carregarGrafico(tipo = 'mes') {
  fetch(`faturacao_dados.php?tipo=${tipo}`)
    .then(r => r.json())
    .then(res => {
      const data = res.dados;
      const media = res.media;

      const labels = data.map(d => d.periodo);
      const valores = data.map(d => parseFloat(d.valor));

      if (grafico) grafico.destroy();

      grafico = new Chart(ctx, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: 'Faturação (€)',
            data: valores,
            backgroundColor: '#a30101'
          }]
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true } }
        }
      });

      // Atualizar média
      const mediaDiv = document.getElementById('mediaValor');
      mediaDiv.innerHTML = `<i class="bi bi-graph-up-arrow"></i> <b>Média de ${tipo}:</b> €${media.toLocaleString('pt-PT', { minimumFractionDigits: 2 })}`;
    });
}

// Atualizar cards principais
function atualizarCards(range = "semana") {
    fetch("dashboard_dados.php?range=" + range)
        .then(r => r.json())
        .then(res => {
            document.querySelector("#cardProjetos").textContent = res.projetos;
            document.querySelector("#cardPropostas").textContent = res.propostas;
            document.querySelector("#cardClientes").textContent = res.clientes;
            document.querySelector("#cardFuncionarios").textContent = res.funcionarios;
            document.querySelector("#cardFaturado").textContent = "€" + 
                res.faturado.toLocaleString("pt-PT", {minimumFractionDigits:2});
        });
}

// Ligar eventos dos botões
document.querySelectorAll(".fc-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".fc-btn").forEach(b => b.classList.remove("ativo"));
        btn.classList.add("ativo");

        atualizarCards(btn.dataset.range);
    });
});

// Carregamento inicial
atualizarCards("semana");

// eventos dos botões
document.querySelectorAll('.filtro').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filtro').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');
    carregarGrafico(btn.dataset.tipo);
  });
});

// carregar gráfico inicial
carregarGrafico('dia');
</script>

<p id="mediaValor" style="margin-top:15px; color:#444; font-weight:600;">📊 Média: —</p>



  <!-- 🧱 Tabelas secundárias -->
  <div class="tables">
    <div class="table">
      <h3><i class="bi bi-trophy-fill"></i> Top 5 Funcionários</h3>
      <table class="clickable-table">
        <br>
        <div class="filtro-func">
          <button class="ff-btn" data-range="dia">Dia</button>
          <button class="ff-btn ativo" data-range="semana">Semana</button>
          <button class="ff-btn" data-range="mes">Mês</button>
          <button class="ff-btn" data-range="ano">Ano</button>
          <button class="ff-btn" data-range="sempre">Sempre</button>
        </div>
        <br>
        <thead><tr><th>Funcionário</th><th>m² Produzidos</th></tr></thead>
        <tbody id="tbodyTopFunc">
        <?php foreach ($topFunc as $f): ?>
          <tr class="clickable-row" data-href="ver_funcionario.php?id=<?= $f['id'] ?>">
            <td><?= htmlspecialchars($f['nome']) ?></td>
            <td><?= number_format($f['total_m2'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>


    <div class="table">

      <h3><i class="bi bi-file-earmark-text"></i> Últimas Propostas Adjudicadas</h3>
      <br>
      <table class="clickable-table">
        <div class="filtro-propostas">
          <button class="fp-btn" data-range="dia">Dia</button>
          <button class="fp-btn ativo" data-range="semana">Semana</button>
          <button class="fp-btn" data-range="mes">Mês</button>
          <button class="fp-btn" data-range="ano">Ano</button>
          <button class="fp-btn" data-range="sempre">Sempre</button>
        </div>
        <br>
        <thead><tr><th>Proposta</th><th>Cliente</th><th>Total</th><th>Data</th></tr></thead>

        <tbody id="tbodyPropostasAdj">
        <?php foreach ($ultimas as $p): ?>
          <tr class="clickable-row" data-href="ver_proposta.php?id=<?= $p['id'] ?>">
            <td><?= htmlspecialchars($p['codigo']) ?></td>
            <td><?= htmlspecialchars($p['nome_cliente']) ?></td>
            <td>€<?= number_format($p['total_final'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($p['data_emissao'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>
<?php
include 'rodape.php';
?>
<script>
// Atualizar Tabela Top Funcionários
function atualizarTopFunc(range = "sempre") {
    fetch("topfunc_dados.php?range=" + range)
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById("tbodyTopFunc");
            tbody.innerHTML = "";

            res.forEach(f => {
                const tr = document.createElement("tr");
                tr.classList.add("clickable-row");
                tr.dataset.href = "ver_funcionario.php?id=" + f.id;

                tr.innerHTML = `
                    <td>${f.nome}</td>
                    <td>${parseFloat(f.total_m2).toLocaleString('pt-PT', {minimumFractionDigits:2})}</td>
                `;

                tr.addEventListener("click", () => {
                    window.location.href = tr.dataset.href;
                });

                tbody.appendChild(tr);
            });
        });
}

// eventos botões
document.querySelectorAll(".ff-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".ff-btn").forEach(b => b.classList.remove("ativo"));
        btn.classList.add("ativo");
        atualizarTopFunc(btn.dataset.range);
    });
});

// Carregar sempre por defeito
atualizarTopFunc("sempre");

// Atualizar Tabela "Últimas Propostas Adjudicadas"
function atualizarPropostasAdj(range = "sempre") {

    fetch("propostas_adj_dados.php?range=" + range)
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById("tbodyPropostasAdj");
            tbody.innerHTML = "";

            res.forEach(p => {
                const tr = document.createElement("tr");
                tr.classList.add("clickable-row");
                tr.dataset.href = "ver_proposta.php?id=" + p.id;

                tr.innerHTML = `
                    <td>${p.codigo}</td>
                    <td>${p.nome_cliente}</td>
                    <td>€${parseFloat(p.total_final).toLocaleString('pt-PT', { minimumFractionDigits: 2 })}</td>
                    <td>${new Date(p.data_emissao).toLocaleDateString('pt-PT')}</td>
                `;

                tr.addEventListener("click", () => {
                    window.location.href = tr.dataset.href;
                });

                tbody.appendChild(tr);
            });
        });
}

// Eventos dos botões de filtro
document.querySelectorAll(".fp-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".fp-btn").forEach(b => b.classList.remove("ativo"));
        btn.classList.add("ativo");
        atualizarPropostasAdj(btn.dataset.range);
    });
});

// Carregar por defeito
atualizarPropostasAdj("sempre");


document.querySelectorAll('.clickable-row').forEach(row => {
  row.addEventListener('click', () => {
    const url = row.dataset.href;
    if (url) window.location.href = url;
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
