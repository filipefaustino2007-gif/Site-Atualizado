<?php
require('../lib/fpdf/fpdf.php');
include '../conexao/conexao.php';

// Buscar a proposta
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
$stmt->execute([$id]);
$prop = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prop) die("Proposta não encontrada");

// Buscar serviços e áreas
$servs = $pdo->prepare("SELECT * FROM proposta_servicos WHERE proposta_id=?");
$servs->execute([$id]);
$servicos = $servs->fetchAll(PDO::FETCH_ASSOC);

$areas = $pdo->prepare("SELECT * FROM proposta_areas WHERE proposta_id=?");
$areas->execute([$id]);
$areas = $areas->fetchAll(PDO::FETCH_ASSOC);

// === GERAR PDF ===
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);

// Cabeçalho
$pdf->Image('../img/logo.png',10,10,60);
$pdf->Ln(30);
$pdf->Cell(0,10,utf8_decode('PROPOSTA DE HONORÁRIOS'),0,1,'C');
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,utf8_decode("Cliente: {$prop['nome_cliente']}"),0,1);
$pdf->Cell(0,10,utf8_decode("Obra: {$prop['nome_obra']}"),0,1);
$pdf->Cell(0,10,utf8_decode("Endereço: {$prop['endereco_obra']}"),0,1);
$pdf->Ln(8);

// Secção Serviços
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,10,'Serviços',0,1);
$pdf->SetFont('Arial','',11);
foreach ($servicos as $s) {
    $pdf->Cell(0,8,"- {$s['nome_servico']}",0,1);
}
$pdf->Ln(8);

// Secção Áreas
if ($areas) {
    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,10,'Áreas',0,1);
    $pdf->SetFont('Arial','',11);
    foreach ($areas as $a) {
        $pdf->Cell(0,8,"{$a['nome_area']} - {$a['m2']} m²",0,1);
    }
}
$pdf->Ln(10);

// Totais
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,10,'Totais',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,"Subtotal: " . number_format($prop['valor_subtotal'], 2, ',', '.') . " €",0,1);
$pdf->Cell(0,8,"IVA (23%): " . number_format($prop['valor_iva'], 2, ',', '.') . " €",0,1);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,10,"Total Final: " . number_format($prop['valor_total'], 2, ',', '.') . " €",0,1);
$pdf->Ln(10);

// Rodapé
$pdf->SetFont('Arial','I',9);
$pdf->Cell(0,10,utf8_decode('SupremeXpansion - Creating Dreams'),0,1,'C');

// === Salvar o ficheiro ===
$caminho = "../uploads/propostas/PROPOSTA_{$id}.pdf";
$pdf->Output('F', $caminho);

// Opcional: redirecionar para download direto
header("Location: $caminho");
exit;
?>
