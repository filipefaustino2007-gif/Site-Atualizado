<?php
include 'protecao.php';
include '../conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$email = trim($_GET['email'] ?? '');
$email = strtolower($email);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'email_invalido']);
  exit;
}

/*
  ⚠️ AJUSTA AQUI os campos/tabela onde guardas empresa/nif
  Vou assumir que está em `utilizadores`:
  - empresa
  - nif

  Se estiver noutra tabela (ex: clientes), troca a query.
*/

$stmt = $pdo->prepare("
  SELECT nome, email, telefone, empresa_nome, empresa_nif
    FROM utilizadores
    WHERE LOWER(email)=?
    LIMIT 1

");
$stmt->execute([$email]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
  echo json_encode(['ok' => true, 'found' => false]);
  exit;
}

echo json_encode([
  'ok' => true,
  'found' => true,
  'data' => [
    'nome' => $u['nome'] ?? '',
    'email' => $u['email'] ?? $email,
    'telefone' => $u['telefone'] ?? '',
    'empresa_nome' => $u['empresa_nome'] ?? '',
    'empresa_nif'  => $u['empresa_nif'] ?? '',

  ]
]);
