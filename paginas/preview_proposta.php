<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '/../conexao/conexao.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// 1) validar ID do URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID inválido.");

// (opcional) força para qualquer include que use $_GET['id']
$_GET['id'] = $id;
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

// 2) buscar proposta + áreas + serviços
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$proposta) die("Proposta não encontrada.");

$stmt2 = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
$stmt2->execute([$id]);
$areas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$stmt3 = $pdo->prepare("
  SELECT sp.*, COALESCE(s.nome, sp.nome_servico) AS nome_base
  FROM servicos_proposta sp
  LEFT JOIN servicos_produtos s ON s.id = sp.id_servico
  WHERE sp.id_proposta = ?
  ORDER BY sp.id_servico ASC
");
$stmt3->execute([$id]);
$servicosSelecionados = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 3) globals para o teste_pdf.php
$GLOBALS['proposta'] = $proposta;
$GLOBALS['areas'] = $areas;
$GLOBALS['servicosSelecionados'] = $servicosSelecionados;
$GLOBALS['servicos'] = $servicosSelecionados;

// 4) gerar preview (ficheiro)
$previewDir = __DIR__ . '/../uploads/previews/';
if (!is_dir($previewDir)) mkdir($previewDir, 0777, true);

$previewPdfPath = $previewDir . "preview_{$id}.pdf";
if (file_exists($previewPdfPath)) unlink($previewPdfPath);

$GLOBALS['PDF_PREVIEW_MODE'] = true;
$GLOBALS['PDF_PREVIEW_PATH'] = $previewPdfPath;

ob_start();
include __DIR__ . '/teste_pdf.php';
ob_end_clean();

if (!file_exists($previewPdfPath) || filesize($previewPdfPath) === 0) {
  die("PDF não foi criado em: " . $previewPdfPath);
}

// 5) url do stream (sem cache)
$previewUrl = "preview_pdf_stream.php?id=" . $id . "&t=" . time();

// defaults ON (se as colunas ainda não existirem, isto continua a funcionar por fallback)
$pref_all         = (int)($proposta['email_send_all'] ?? 1);
$pref_proposta    = (int)($proposta['email_send_proposta'] ?? 1);
$pref_credenciais = (int)($proposta['email_send_credenciais'] ?? 1);
$pref_func        = (int)($proposta['email_send_funcionarios'] ?? 1);
$pref_news        = (int)($proposta['email_send_newsletter'] ?? 1);

// se "all" estiver ligado, força UI ligada (só para consistência visual)
if ($pref_all === 1) {
  $pref_proposta = $pref_credenciais = $pref_func = $pref_news = 1;
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Preview Proposta</title>
<link rel="icon" type="image/png" href="../img/icon.png">

<style>
body{margin:0;font-family:Arial;background:#f5f5f5;}
.top{position:sticky;top:0;display:flex;gap:10px;padding:12px;background:#fff;border-bottom:1px solid #eee;z-index:5;}
.btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer;font-weight:700;text-decoration:none;display:inline-block;}
.back{background:#eee;color:#111;}
.ok{background:#a30101;color:#fff;}
iframe{width:100%;height:calc(100vh - 62px);border:0;background:#fff;}
.small{font-size:12px;color:#666;margin-left:auto;align-self:center;}

.mailopts{
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
  padding:8px 10px;
  border:1px solid #eee;
  border-radius:12px;
  background:#fafafa;
}
.mailopts label{
  display:flex;
  gap:6px;
  align-items:center;
  font-size:13px;
  color:#111;
  user-select:none;
}
.mailopts .sep{width:1px;height:18px;background:#e7e7e7;margin:0 4px;}
</style>
</head>
<body>
  <div class="top">
    <?php
    $backUrl = ($parent_id > 0)
      ? "renegociar.php?id=".(int)$parent_id
      : "criar_proposta.php?id=".(int)$id;
    ?>
    <a class="btn back" href="<?= htmlspecialchars($backUrl) ?>">← Voltar a editar</a>
    


    <form method="post" action="salvar_proposta.php" style="margin:0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
      <input type="hidden" name="confirmar_preview" value="1">
      <input type="hidden" name="id_proposta" value="<?= (int)$id ?>">
      <?php if ($parent_id > 0): ?>
        <input type="hidden" name="parent_id" value="<?= (int)$parent_id ?>">
      <?php endif; ?>

      <!-- ✅ Preferências de email -->
      <div class="mailopts">
        <label title="Ativa/desativa todos os emails automáticos">
          <input type="checkbox" id="mail_all" name="email_send_all" value="1" <?= $pref_all ? 'checked' : '' ?>>
          <b>Enviar todos</b>
        </label>

        <span class="sep"></span>

        <label>
          <input type="checkbox" class="mail_child" name="email_send_proposta" value="1" <?= $pref_proposta ? 'checked' : '' ?>>
          Proposta
        </label>

        <label>
          <input type="checkbox" class="mail_child" name="email_send_credenciais" value="1" <?= $pref_credenciais ? 'checked' : '' ?>>
          Credenciais login
        </label>

        <label>
          <input type="checkbox" class="mail_child" name="email_send_funcionarios" value="1" <?= $pref_func ? 'checked' : '' ?>>
          Funcionários
        </label>

        <label>
          <input type="checkbox" class="mail_child" name="email_send_newsletter" value="1" <?= $pref_news ? 'checked' : '' ?>>
          Newsletter no fim
        </label>
      </div>

      <button class="btn ok" type="submit">✅ Confirmar e enviar ao cliente</button>
    </form>

    <?php if ($parent_id > 0): ?>
      <div class="small">Renegociação da proposta #<?= (int)$parent_id ?></div>
    <?php endif; ?>

    <div class="small">
      <?= htmlspecialchars($proposta['codigo'] ?? ('ID ' . $id)) ?>
    </div>
  </div>

  <iframe src="<?= htmlspecialchars($previewUrl) ?>"></iframe>
  <script>
    (function(){
      const all = document.getElementById('mail_all');
      const kids = Array.from(document.querySelectorAll('.mail_child'));
      if (!all || !kids.length) return;

      function refreshAllState(){
        const checkedCount = kids.filter(x => x.checked).length;
        if (checkedCount === kids.length) {
          all.indeterminate = false;
          all.checked = true;
        } else if (checkedCount === 0) {
          all.indeterminate = false;
          all.checked = false;
        } else {
          all.indeterminate = true;
          all.checked = false; // indeterminate visual
        }
      }

      all.addEventListener('change', () => {
        kids.forEach(k => k.checked = all.checked);
        all.indeterminate = false;
      });

      kids.forEach(k => k.addEventListener('change', refreshAllState));

      refreshAllState();
    })();
</script>

</body>
</html>
