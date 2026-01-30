<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  header('Content-Type: text/plain; charset=utf-8');
  exit("ID inválido.");
}

$previewPath = __DIR__ . '/../uploads/previews/preview_' . $id . '.pdf';

if (!file_exists($previewPath) || filesize($previewPath) === 0) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Preview não encontrado para ID {$id}.\nEsperado: {$previewPath}\n");
}

// debug headers (para o Network tab)
header('X-Preview-ID: ' . $id);
header('X-Preview-Size: ' . filesize($previewPath));
header('X-Preview-MTime: ' . filemtime($previewPath));

// anti-cache + pdf
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview_'.$id.'.pdf"');
header('Content-Length: ' . filesize($previewPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($previewPath);
exit;
