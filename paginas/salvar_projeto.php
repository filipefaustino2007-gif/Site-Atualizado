<?php
// paginas/guardar_config_projeto.php
session_start();
require '../conexao/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$projeto_id = (int)($_POST['projeto_id'] ?? 0);
$equipa = $_POST['equipa'] ?? []; // ['3D'=>[1,2], '2D'=>[...], 'BIM'=>[...]]

if ($projeto_id <= 0) exit('Projeto inválido.');

try {
  $pdo->beginTransaction();

  // limpa
  $del = $pdo->prepare("DELETE FROM projetos_funcionarios WHERE projeto_id = ?");
  $del->execute([$projeto_id]);

  // volta a inserir
  $ins = $pdo->prepare("INSERT INTO projetos_funcionarios (projeto_id, area, funcionario_id) VALUES (?, ?, ?)");
  foreach (['3D','2D','BIM'] as $area) {
    if (!empty($equipa[$area]) && is_array($equipa[$area])) {
      foreach ($equipa[$area] as $fid) {
        $fid = (int)$fid;
        if ($fid > 0) { $ins->execute([$projeto_id, $area, $fid]); }
      }
    }
  }

  $pdo->commit();
  header("Location: ver_projeto.php?id=".$projeto_id);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('Erro: '.$e->getMessage());
}
