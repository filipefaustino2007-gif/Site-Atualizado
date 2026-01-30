<?php
include '../conexao/conexao.php';

$range = $_GET['range'] ?? 'sempre';

switch ($range) {
    case 'dia':
        $whereProp = "AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 0 DAY)";

        break;
    case 'semana':
        $whereProp = "AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'mes':
        $whereProp = "AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        break;
    case 'ano':
        $whereProp = "AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        break;
    default:
        $whereProp = "";
}

$sql = "
    SELECT id, codigo, nome_cliente, total_final, data_emissao
    FROM propostas
    WHERE estado = 'adjudicada'
    $whereProp
    ORDER BY criado_em DESC
    LIMIT 5
";

$dados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($dados);
