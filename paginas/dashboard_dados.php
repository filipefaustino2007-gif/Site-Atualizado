<?php
include '../conexao/conexao.php';

$range = $_GET['range'] ?? 'semana';

// Construir WHERE dinâmico
switch ($range) {
    case 'dia':
        $where = "WHERE data_inicio >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)";
        $whereProp = "WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)";

        break;
    case 'semana':
        $where = "WHERE data_inicio >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $whereProp = "WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";

        break;
    case 'mes':
        $where = "WHERE data_inicio >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        $whereProp = "WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";

        break;
    case 'ano':
        $where = "WHERE data_inicio >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        $whereProp = "WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";

        break;
    default:
        $where = ""; // SEMPRE
        $whereProp = "";
}

// Contadores
$totalProjetos = $pdo->query("SELECT COUNT(*) FROM projetos $where")->fetchColumn();
$totalPropostas = $pdo->query("SELECT COUNT(*) FROM propostas $whereProp")->fetchColumn();
$totalClientes = $pdo->query("SELECT COUNT(DISTINCT email_cliente) FROM propostas")->fetchColumn();
$totalFuncionarios = $pdo->query("SELECT COUNT(*) FROM utilizadores WHERE acesso_id < 6")->fetchColumn();

// Faturação
$sqlFat = "
    SELECT SUM(
        CASE 
            WHEN pj.estado = 'Concluído' THEN pj.valor_total
            WHEN pr.pagamento_inicial_pago = 1 THEN COALESCE(pr.pagamento_inicial_valor, pr.total_final * 0.5)
            ELSE 0
        END
    )
    FROM projetos pj
    JOIN propostas pr ON pr.id = pj.proposta_id
    $where
";

$totalFaturado = (float)($pdo->query($sqlFat)->fetchColumn() ?? 0);

echo json_encode([
    "projetos" => $totalProjetos,
    "propostas" => $totalPropostas,
    "clientes" => $totalClientes,
    "funcionarios" => $totalFuncionarios,
    "faturado" => $totalFaturado
]);
