<?php
include '../conexao/conexao.php';

$id = $_GET['id'] ?? 0;
$acao = $_GET['acao'] ?? '';

if (!$id || !in_array($acao, ['adjudicar', 'cancelar'])) {
  die("Ação inválida.");
}

$novoEstado = $acao === 'adjudicar' ? 'Adjudicada' : 'Cancelada';
$stmt = $pdo->prepare("UPDATE propostas SET estado = ? WHERE id = ?");
$stmt->execute([$novoEstado, $id]);

header("Location: ver_proposta.php?id=$id");
exit;
?>
