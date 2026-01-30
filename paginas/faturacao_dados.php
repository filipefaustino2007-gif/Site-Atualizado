<?php
include '../conexao/conexao.php';

$tipo = $_GET['tipo'] ?? 'mes';
$dados = [];

switch ($tipo) {
  case 'dia':
    $sql = "
      SELECT DATE(pj.data_inicio) AS periodo,
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
      WHERE pj.data_inicio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      GROUP BY DATE(pj.data_inicio)
      ORDER BY periodo
    ";
    break;

  case 'semana':
    $sql = "
      SELECT YEARWEEK(pj.data_inicio) AS periodo,
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
      WHERE pj.data_inicio >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
      GROUP BY YEARWEEK(pj.data_inicio)
      ORDER BY periodo
    ";
    break;

  case 'ano':
    $sql = "
      SELECT YEAR(pj.data_inicio) AS periodo,
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
      GROUP BY YEAR(pj.data_inicio)
      ORDER BY periodo
    ";
    break;

  default: // mês
    $sql = "
      SELECT DATE_FORMAT(pj.data_inicio, '%Y-%m') AS periodo,
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
}

$stmt = $pdo->query($sql);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular média geral do período selecionado
$total = array_sum(array_column($dados, 'valor'));
$media = count($dados) ? round($total / count($dados), 2) : 0;

header('Content-Type: application/json');
echo json_encode([
  'dados' => $dados,
  'media' => $media
]);
