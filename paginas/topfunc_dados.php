<?php
include '../conexao/conexao.php';

$range = $_GET['range'] ?? 'sempre';

switch ($range) {
    case 'dia':
        $where = "AND p.data_termino >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)";
        break;
    case 'semana':
        $where = "AND p.data_termino >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'mes':
        $where = "AND p.data_termino >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'ano':
        $where = "AND p.data_termino >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $where = ""; // SEMPRE
}

$sql = "
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
  $where
  GROUP BY u.id
  ORDER BY total_m2 DESC
  LIMIT 5
";

$dados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($dados);
