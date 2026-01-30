<?php
// paginas/atualizar_estado_area.php
session_start();
require '../conexao/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$pa_id = (int)($_POST['projeto_area_id'] ?? 0);
$estado = $_POST['estado'] ?? '';

$valid = ['Em configuração','Em produção','Em espera','Concluído','Cancelado'];
if ($pa_id <= 0 || !in_array($estado, $valid, true)) { exit('Dados inválidos.'); }

try {
  // descobrir a que projeto pertence para voltar à página certa
  $stmt = $pdo->prepare("SELECT projeto_id FROM projetos_areas WHERE id = ?");
  $stmt->execute([$pa_id]);
  $projeto_id = (int)$stmt->fetchColumn();
  if (!$projeto_id) { exit('Área não encontrada.'); }

  $up = $pdo->prepare("UPDATE projetos_areas SET estado = ? WHERE id = ?");
  $up->execute([$estado, $pa_id]);

  header("Location: ver_projeto.php?id=".$projeto_id);
  exit;

} catch (Throwable $e) {
  exit('Erro: '.$e->getMessage());
}
