<?php
// update_projeto_comentarios.php
include 'protecao.php';
include '../conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'Sessão inválida.']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$updates = $data['updates'] ?? null;
if (!is_array($updates)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'Payload inválido.']);
  exit;
}

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("
    UPDATE projetos
    SET comentarios = ?,
        comentarios_ultimo_editor_id = ?,
        comentarios_ultima_edicao = NOW()
    WHERE id = ?
  ");

  foreach ($updates as $u) {
    $id = (int)($u['id'] ?? 0);
    $comentarios = trim((string)($u['comentarios'] ?? ''));

    if ($id <= 0) continue;

    $stmt->execute([$comentarios, $userId, $id]);
  }

  $pdo->commit();
  echo json_encode(['ok' => true]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'msg' => 'Erro ao guardar.']);
}
