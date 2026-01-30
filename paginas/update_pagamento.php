<?php
// paginas/confirmar_pagamento_inicial.php
session_start();
require '../conexao/conexao.php'; // $pdo (PDO)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); exit('Método inválido');
}

$id_proposta = (int)($_POST['id'] ?? 0);
$valor_inicial = (float)($_POST['pagamento_inicial_valor'] ?? 0);

if ($id_proposta <= 0) { exit('Proposta inválida.'); }
if ($valor_inicial < 0) { $valor_inicial = 0; }

try {
  $pdo->beginTransaction();

  // 1) Validar proposta (tem de estar adjudicada)
  $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ? FOR UPDATE");
  $stmt->execute([$id_proposta]);
  $prop = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$prop) { throw new Exception('Proposta não encontrada.'); }
  if ($prop['estado'] !== 'adjudicada') { throw new Exception('Apenas propostas adjudicadas podem registar pagamento inicial.'); }

  // 2) Atualizar pagamento inicial
  $up = $pdo->prepare("
    UPDATE propostas
       SET pagamento_inicial_valor = ?,
           pagamento_inicial_pago = 1,
           pagamento_data = NOW()
     WHERE id = ?
  ");
  $up->execute([$valor_inicial, $id_proposta]);

  // 3) Criar projeto se ainda não existir
  $ck = $pdo->prepare("SELECT id FROM projetos WHERE proposta_id = ?");
  $ck->execute([$id_proposta]);
  $projeto_id = $ck->fetchColumn();

  if (!$projeto_id) {
    $ins = $pdo->prepare("
      INSERT INTO projetos (proposta_id, cliente_id, nome_projeto, nome_obra, valor_total, data_inicio, estado)
      VALUES (?, NULL, ?, ?, ?, NOW(), 'Em processamento')
    ");
    $nome_proj = $prop['codigo'] . ' - ' . ($prop['nome_obra'] ?: 'Projeto');
    $ins->execute([$id_proposta, $nome_proj, $prop['nome_obra'], $prop['total_final']]);
    $projeto_id = $pdo->lastInsertId();

    // Criar as 3 áreas default
    $areaIns = $pdo->prepare("INSERT INTO projetos_areas (projeto_id, area) VALUES (?, ?)");
    foreach (['Levantamento','3D','2D','BIM'] as $ar) {
      $areaIns->execute([$projeto_id, $ar]);
    }
  }

  $pdo->commit();

  // Redireciona para configuração do projeto (atribuir equipa, etc.)
  header("Location: configurar_projeto.php?id=" . $projeto_id);

  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  exit('Erro: ' . $e->getMessage());
}
