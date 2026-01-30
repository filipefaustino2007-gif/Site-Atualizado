<?php
session_start();
include 'protecao.php';
include '../conexao/conexao.php';
include 'cabecalho.php';
require_once '../vendor/autoload.php'; // PHPMailer
// $config = require '../paginas/config_email.php';  // vamos carregar dentro da função

function projetoAreaPretty(string $a): string {
  return match ($a) {
    'Levantamento' => 'Levantamento no Terreno',
    '3D' => 'Área 3D',
    '2D' => 'Área 2D',
    'BIM' => 'Área BIM',
    default => $a,
  };
}

function buildAssignEmailHtml(array $projeto, string $nomePessoa, string $papel, array $areas, string $projectUrl = ''): string {
  $nomeProjeto = htmlspecialchars((string)($projeto['nome_projeto'] ?? 'Projeto'));
  $nomePessoa  = htmlspecialchars($nomePessoa);
  $papel       = htmlspecialchars($papel);

  $lis = '';
  foreach ($areas as $a) $lis .= '<li>' . htmlspecialchars($a) . '</li>';

  $linkHtml = '';
  if ($projectUrl) {
    $safe = htmlspecialchars($projectUrl);
    $linkHtml = "<p style='margin-top:14px;'><a href='{$safe}'>Abrir projeto</a></p>";
  }

  return "
  <div style='font-family:Poppins, Arial, sans-serif; background:#fafafa; padding:18px; border-radius:10px;'>
    <h2 style='color:#a30101; margin:0 0 10px;'>Novo trabalho atribuído</h2>
    <p>Olá, <b>{$nomePessoa}</b>,</p>
    <p>Foste atribuído ao projeto <b>{$nomeProjeto}</b> como <b>{$papel}</b>.</p>
    <p><b>Áreas atribuídas:</b></p>
    <ul style='margin-top:6px;'>{$lis}</ul>
    {$linkHtml}
    <p style='color:#666; font-size:12px; margin-top:18px;'>
      Email automático — não respondas a este endereço.
    </p>
  </div>";
}

function sendSmtpEmail(string $toEmail, string $toName, string $subject, string $html): bool {
  $config = require '../paginas/config_email.php';

  $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $mail->SMTPSecure = ($config['smtp_secure'] === 'tls')
      ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
      : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int)$config['smtp_port'];
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($toEmail, $toName);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->AltBody = "Foi-te atribuído um projeto na plataforma SupremeXpansion.";
    $mail->msgHTML($html);

    $mail->send();
    return true;
  } catch (\Throwable $e) {
    // loga para veres o erro real
    error_log("Erro email atribuição projeto: " . $mail->ErrorInfo);
    return false;
  }
}

function projectUrl(int $projetoId): string {
  // Ajusta para o teu domínio real (IMPORTANTE)
  // Se o admin estiver noutra pasta, ajusta o path
  return "http://localhost:3000/Supremexpansion/paginas/configurar_projeto.php?id=" . $projetoId;
}


$isCliente = ($_SESSION['nivel_acesso'] ?? 0) == 6;

$projeto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$projeto_id) die("Projeto inválido.");

// ——— Buscar projeto
$stmt = $pdo->prepare("
    SELECT 
        p.id, p.nome_projeto, p.tipo_projeto, p.visibilidade_site,
        p.link1, p.link2, p.link3, p.link4, p.link5,
        p.gestor_projeto_id,
        p.proposta_id,

        -- flags (vindas da proposta)
        prop.email_send_all,
        prop.email_send_funcionarios
    FROM projetos p
    LEFT JOIN propostas prop ON prop.id = p.proposta_id
    WHERE p.id = ?
");


$stmt->execute([$projeto_id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$projeto) die("Projeto não encontrado.");

// ===== flags de email (fallback = 1 para projetos antigos) =====
$email_send_all          = (int)($projeto['email_send_all'] ?? 1);
$email_send_funcionarios = (int)($projeto['email_send_funcionarios'] ?? 1);

$podeEnviarEmailsFuncionarios = ($email_send_all === 1 && $email_send_funcionarios === 1);


$msg  = '';
$erro = '';

// ——— Pastas
$baseDir = "../uploads/projetos/{$projeto_id}/";
@mkdir($baseDir, 0777, true);
@mkdir($baseDir . "capa/", 0777, true);
@mkdir($baseDir . "galeria/", 0777, true);
@mkdir($baseDir . "docs/", 0777, true);
$isFuncionario = ($_SESSION['nivel_acesso'] ?? 0) == 5;
$isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;

$isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;

$userId = $_SESSION['user_id'] ?? 0;
// ——— Funções (mantidas iguais)
function salvarUploadImagens(array $files, string $destinoBase, int $projeto_id, PDO $pdo, string $tipo = 'galeria'): void {
    $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if ($files['error'][$i] === UPLOAD_ERR_OK && in_array($files['type'][$i], $permitidos)) {
            $nomeLimpo = basename($files['name'][$i]);
            $novoNome  = time() . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $nomeLimpo);
            $destino   = $destinoBase . $novoNome;
            if (move_uploaded_file($tmp, $destino)) {
                $ins = $pdo->prepare("INSERT INTO projeto_imagens (projeto_id, tipo, ficheiro) VALUES (?, ?, ?)");
                $ins->execute([$projeto_id, $tipo, $novoNome]);
            }
        }
    }
}
function salvarUploadDocs(array $files, string $destinoBase, int $projeto_id, PDO $pdo): void {
    $permitidos = ['application/pdf'];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if ($files['error'][$i] === UPLOAD_ERR_OK && in_array($files['type'][$i], $permitidos)) {
            $nomeLimpo = basename($files['name'][$i]);
            $novoNome  = time() . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $nomeLimpo);
            $destino   = $destinoBase . $novoNome;
            if (move_uploaded_file($tmp, $destino)) {
                $ins = $pdo->prepare("INSERT INTO projeto_docs (projeto_id, ficheiro) VALUES (?, ?)");
                $ins->execute([$projeto_id, $novoNome]);
            }
        }
    }
}

// ——— AÇÕES
$acao = $_POST['acao'] ?? '';


if ($acao === 'guardar_gestor') {
  try {
    $gestorId = (int)($_POST['gestor_projeto_id'] ?? 0);

    // gestor anterior (do SELECT inicial)
    $oldGestorId = (int)($projeto['gestor_projeto_id'] ?? 0);

    $up = $pdo->prepare("UPDATE projetos SET gestor_projeto_id=? WHERE id=?");
    $up->execute([$gestorId ?: null, $projeto_id]);

    // atualiza para uso no template
    $projeto['gestor_projeto_id'] = $gestorId;

    // Só envia se mudou e se é válido
    if ($podeEnviarEmailsFuncionarios && $gestorId > 0 && $gestorId !== $oldGestorId)  {
      $q = $pdo->prepare("SELECT nome, email FROM utilizadores WHERE id=? LIMIT 1");
      $q->execute([$gestorId]);
      $u = $q->fetch(PDO::FETCH_ASSOC);

      if ($u && !empty($u['email'])) {
        $areas = ['Gestão do Projeto']; // podes mudar o texto
        $html  = buildAssignEmailHtml($projeto, (string)$u['nome'], 'Gestor do Projeto', $areas, projectUrl($projeto_id));

        // opcional: podes guardar resultado se quiseres mostrar msg
        sendSmtpEmail((string)$u['email'], (string)$u['nome'], "Projeto atribuído: " . ($projeto['nome_projeto'] ?? ''), $html);
      }
    }

    $msg = "✅ Gestor do projeto atualizado!";
  } catch (Throwable $e) {
    $erro = "Erro ao guardar gestor: " . $e->getMessage();
  }
}


// Guardar equipa
if ($acao === 'guardar_equipa') {
  try {
    $map = [
      'Levantamento' => $_POST['equipa_levantamento'] ?? [],
      '3D' => $_POST['equipa_3d'] ?? [],
      '2D' => $_POST['equipa_2d'] ?? [],
      'BIM'=> $_POST['equipa_bim'] ?? []
    ];

    // 1) buscar equipa atual ANTES de apagar (para comparar)
    $old = []; // [area => [funcionario_id => true]]
    $qOld = $pdo->prepare("SELECT area, funcionario_id FROM projetos_funcionarios WHERE projeto_id=?");
    $qOld->execute([$projeto_id]);
    while ($r = $qOld->fetch(PDO::FETCH_ASSOC)) {
      $a = (string)$r['area'];
      $fid = (int)$r['funcionario_id'];
      if (!isset($old[$a])) $old[$a] = [];
      $old[$a][$fid] = true;
    }

    // 2) guardar nova equipa (o teu fluxo)
    foreach ($map as $area => $ids) {
      $pdo->prepare("DELETE FROM projetos_funcionarios WHERE projeto_id = ? AND area = ?")
          ->execute([$projeto_id, $area]);

      $ids = array_unique(array_filter(array_map('intval', $ids)));
      if ($ids) {
        $ins = $pdo->prepare("INSERT INTO projetos_funcionarios (projeto_id, area, funcionario_id) VALUES (?, ?, ?)");
        foreach ($ids as $fid) $ins->execute([$projeto_id, $area, $fid]);
      }
    }

    // 3) descobrir quem foi ADICIONADO agora (por área)
    $addedByUser = []; // [funcionario_id => ['Levantamento','3D'...]]
    foreach ($map as $area => $ids) {
      $ids = array_unique(array_filter(array_map('intval', $ids)));
      foreach ($ids as $fid) {
        $wasAlready = !empty($old[$area]) && isset($old[$area][$fid]);
        if (!$wasAlready) {
          $addedByUser[$fid][] = $area;
        }
      }
    }

    // 4) enviar 1 email por pessoa com as áreas novas
    if ($podeEnviarEmailsFuncionarios && !empty($addedByUser)) {
      $qUser = $pdo->prepare("SELECT nome, email FROM utilizadores WHERE id=? LIMIT 1");

      foreach ($addedByUser as $fid => $areasNovas) {
        $qUser->execute([(int)$fid]);
        $u = $qUser->fetch(PDO::FETCH_ASSOC);
        if (!$u || empty($u['email'])) continue;

        $prettyAreas = array_map('projetoAreaPretty', $areasNovas);

        $html = buildAssignEmailHtml(
          $projeto,
          (string)$u['nome'],
          'Membro da Equipa',
          $prettyAreas,
          projectUrl($projeto_id)
        );

        sendSmtpEmail(
          (string)$u['email'],
          (string)$u['nome'],
          "Novo trabalho atribuído: " . ($projeto['nome_projeto'] ?? ''),
          $html
        );
      }
    } elseif (!$podeEnviarEmailsFuncionarios && !empty($addedByUser)) {
        error_log("Email atribuição equipa bloqueado (preferências). Projeto ID: ".$projeto_id);
      }

    $msg = "✅ Equipa atualizada com sucesso!";
  } catch (Throwable $e) {
    $erro = "Erro: " . $e->getMessage();
  }
}


// Upload capa
if ($acao === 'upload_capa' && isset($_FILES['capa']) && $_FILES['capa']['error'] === UPLOAD_ERR_OK) {
    try {
        $pdo->prepare("DELETE FROM projeto_imagens WHERE projeto_id=? AND tipo='capa'")->execute([$projeto_id]);
        salvarUploadImagens(
            ['name'=>[$_FILES['capa']['name']], 'type'=>[$_FILES['capa']['type']], 'tmp_name'=>[$_FILES['capa']['tmp_name']], 'error'=>[$_FILES['capa']['error']]],
            $baseDir . "capa/", $projeto_id, $pdo, 'capa'
        );
        $msg = "✅ Capa atualizada com sucesso!";
    } catch (Throwable $e) { $erro = "Erro: " . $e->getMessage(); }
}

// Upload galeria
if ($acao === 'upload_galeria' && !empty($_FILES['galeria']['name'][0])) {
    try {
        salvarUploadImagens($_FILES['galeria'], $baseDir . "galeria/", $projeto_id, $pdo, 'galeria');
        $msg = "🖼️ Imagens adicionadas à galeria!";
    } catch (Throwable $e) { $erro = "Erro: " . $e->getMessage(); }
}

// Upload docs
if ($acao === 'upload_docs' && !empty($_FILES['docs']['name'][0])) {
    try {
        salvarUploadDocs($_FILES['docs'], $baseDir . "docs/", $projeto_id, $pdo);
        $msg = "📑 PDFs anexados com sucesso!";
    } catch (Throwable $e) { $erro = "Erro: " . $e->getMessage(); }
}

// Guardar tipo + visibilidade
if ($acao === 'guardar_tipo_visibilidade') {
    $tipo = $_POST['tipo_projeto'] ?? '';
    $vis  = $_POST['visibilidade_site'] ?? 'nenhum';
    try {
        $up = $pdo->prepare("UPDATE projetos SET tipo_projeto=?, visibilidade_site=? WHERE id=?");
        $up->execute([$tipo, $vis, $projeto_id]);
        $msg = "✅ Configuração de tipo e visibilidade guardada!";
        $projeto['tipo_projeto'] = $tipo;
        $projeto['visibilidade_site'] = $vis;
    } catch (Throwable $e) {
        $erro = "Erro ao guardar configuração: " . $e->getMessage();
    }
}
if ($acao === 'guardar_links') {
    try {
        $sql = "UPDATE projetos SET 
                link1=?, link2=?, link3=?, link4=?, link5=?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['link1'] ?? null,
            $_POST['link2'] ?? null,
            $_POST['link3'] ?? null,
            $_POST['link4'] ?? null,
            $_POST['link5'] ?? null,
            $projeto_id
        ]);

        $msg = "🔗 Links guardados com sucesso!";
    } catch (Throwable $e) {
        $erro = "Erro ao guardar links: " . $e->getMessage();
    }
}

// --- resto do teu código inalterado (busca equipa, funcs, imagens, etc)


// ——— Buscar equipa atual
$equipa = [
    'Levantamento' => [], // 🔥 NOVA ÁREA
    '3D' => [],
    '2D' => [],
    'BIM' => []
];

$qEquipa = $pdo->prepare("
    SELECT pf.area, u.id, u.nome, u.email
    FROM projetos_funcionarios pf
    JOIN utilizadores u ON u.id = pf.funcionario_id
    WHERE pf.projeto_id = ?
    ORDER BY u.nome
");
$qEquipa->execute([$projeto_id]);
while ($r = $qEquipa->fetch(PDO::FETCH_ASSOC)) {
    $equipa[$r['area']][] = $r;
}

// ——— Funcionários disponíveis (<6)
$funcs = $pdo->query("SELECT id, nome, email FROM utilizadores WHERE acesso_id < 6 AND ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$defaultEmailGestor = 'd.pinto@gmail.com';

// se já existir gestor no projeto, usa-o
$gestorSelecionado = (int)($projeto['gestor_projeto_id'] ?? 0);

// se não existir, tenta apanhar o ID do d.pinto@gmail.com
if ($gestorSelecionado <= 0) {
    foreach ($funcs as $f) {
        if (strtolower(trim($f['email'])) === strtolower($defaultEmailGestor)) {
            $gestorSelecionado = (int)$f['id'];
            break;
        }
    }
}

// ——— Itens existentes (capa/galeria/docs)
$capa = $pdo->prepare("SELECT ficheiro FROM projeto_imagens WHERE projeto_id=? AND tipo='capa'");
$capa->execute([$projeto_id]);
$capa = $capa->fetchColumn();

$galeria = $pdo->prepare("SELECT ficheiro FROM projeto_imagens WHERE projeto_id=? AND tipo='galeria' ORDER BY id DESC");
$galeria->execute([$projeto_id]);
$galeria = $galeria->fetchAll(PDO::FETCH_COLUMN);

$pdfs = $pdo->prepare("SELECT ficheiro FROM projeto_docs WHERE projeto_id=? ORDER BY id DESC");
$pdfs->execute([$projeto_id]);
$pdfs = $pdfs->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title>Configurar Projeto | SupremeXpansion</title>
<link rel="stylesheet" href="../css/configurar_projeto.css">
<script src="../js/configurar_projeto.js" defer></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
<br><br><br><br>
<main>
  <h1><i class="bi bi-gear-fill"></i> Configurar Projeto: <?= htmlspecialchars($projeto['nome_projeto']) ?></h1>

  <?php if ($msg):  ?><div class="msg sucesso"><?= htmlspecialchars($msg)  ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="msg erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
  <?php if (!$isCliente): ?>
  <div class="section">
    <h2><i class="bi bi-person-badge-fill"></i> Gestor do Projeto</h2>

    <form method="POST">
      <input type="hidden" name="acao" value="guardar_gestor">

      <label><b>Responsável pelo projeto:</b></label><br><br>

      <select name="gestor_projeto_id" required style="width:100%; padding:14px; border-radius:10px; border:1px solid #ccc; font-size:16px;">
        <option value="">— Selecionar gestor —</option>
        <?php foreach ($funcs as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id'] === (int)$gestorSelecionado) ? 'selected' : '' ?>>
            <?= htmlspecialchars($f['nome']) ?> — <?= htmlspecialchars($f['email']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>
        <br><br>
        <button type="submit">Guardar Gestor</button>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <?php if (!$isCliente): ?>
  <!-- =================== ATRIBUIÇÃO DE FUNCIONÁRIOS =================== -->
  <div class="section">
    <h2><i class="bi bi-people-fill"></i> Equipa do Projeto</h2>
    <form method="POST" id="form-equipa">
      <input type="hidden" name="acao" value="guardar_equipa">

      <?php
      // helper para renderizar bloco de uma área
      function blocoArea($rotulo, $campo, $lista, $funcs) {
          $isFuncionario = ($_SESSION['nivel_acesso'] ?? 0) == 5;
          $isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;

          $isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;

          ?>
          <div style="margin:12px 0;">
            <br><h3><?= $rotulo ?></h3><br>
            <div class="inline">
              <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>

              <select id="sel_<?= $campo ?>">
                <option value="">— Selecionar funcionário —</option>
                <?php foreach ($funcs as $f): ?>
                  <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?> — <?= htmlspecialchars($f['email']) ?></option>
                <?php endforeach; ?>
              </select>
              
              <button type="button" onclick="addFunc('<?= $campo ?>')">Atribuir</button>
              
              <span class="small">Pode adicionar vários por área.</span>
              <?php endif; ?>

            </div>
            <br>
            <div id="chips_<?= $campo ?>">
              <?php foreach ($lista as $f): ?>
                <span class="tag" data-id="<?= $f['id'] ?>">
                  <b><?= htmlspecialchars($f['nome']) ?></b> <span><?= htmlspecialchars($f['email']) ?></span>
      
                  <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>

                  <button class="rm" type="button" onclick="removeChip(this)">×</button>

                  <?php endif; ?>

                  <input type="hidden" name="equipa_<?= $campo ?>[]" value="<?= $f['id'] ?>">
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php
      }
      blocoArea('Levantamento no Terreno', 'levantamento', $equipa['Levantamento'], $funcs);

      blocoArea('Área 3D', '3d', $equipa['3D'], $funcs);
      blocoArea('Área 2D', '2d', $equipa['2D'], $funcs);
      blocoArea('Área BIM', 'bim', $equipa['BIM'], $funcs);

      ?>
      
      <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>
      
        <br>
      <div style="margin-top:12px;">
        <button type="submit">Guardar Equipa</button>
      </div>

      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <!-- =================== CAPA =================== -->
  <div class="section">
    <h2><i class="bi bi-image-fill"></i> Imagem de Capa</h2>
    <?php if ($capa): ?>
      <div class="inline" style="align-items:flex-start;">
        <img src="../uploads/projetos/<?= $projeto_id ?>/capa/<?= htmlspecialchars($capa) ?>" alt="Capa" style="max-width:320px;border-radius:12px;">
        <span class="small">Capa atual</span>
      </div>
    <?php else: ?>
      <p class="small">Sem capa definida.</p>
    <?php endif; ?>
    <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>
    
    <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
      <input type="hidden" name="acao" value="upload_capa">
      <input type="file" name="capa" accept="image/*" required>
      <button type="submit">Atualizar Capa</button>
    </form>
    <?php endif; ?>

  </div>

  <!-- =================== GALERIA =================== -->
  <div class="section">
    <h2><i class="bi bi-image-fill"></i> Galeria de Imagens</h2>
    <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="acao" value="upload_galeria">
      <input type="file" name="galeria[]" accept="image/*" multiple required>
      <button type="submit">Adicionar Imagens</button>
    </form>
    <?php endif; ?>


    <?php if ($galeria): ?>
      <div class="gallery" style="margin-top:10px;">
        <?php foreach ($galeria as $img): ?>
          <img src="../uploads/projetos/<?= $projeto_id ?>/galeria/<?= htmlspecialchars($img) ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$isCliente): ?>

  <!-- =================== DOCUMENTOS =================== -->
  <div class="section">
    <h2><i class="bi bi-file-earmark-text-fill"></i> Documentos (PDFs)</h2>
    <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="acao" value="upload_docs">
      <input type="file" name="docs[]" accept="application/pdf" multiple required>
      <button type="submit">Anexar PDFs</button>
    </form>
    <?php endif; ?>


    <?php if ($pdfs): ?>
      <div class="pdf-list" style="margin-top:10px;">
        <?php foreach ($pdfs as $pdf): ?>
          <a href="../uploads/projetos/<?= $projeto_id ?>/docs/<?= htmlspecialchars($pdf) ?>" target="_blank">📄 <?= htmlspecialchars($pdf) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$isCliente): ?>

    <!-- =================== TIPO E VISIBILIDADE =================== -->
  <div class="section">
    <h2><i class="bi bi-globe2"></i> Tipo e Disponibilidade</h2>
    <form method="POST">
      <input type="hidden" name="acao" value="guardar_tipo_visibilidade">
      <br>
      <label><b>Tipo de Projeto:</b></label><br><br>
      <select name="tipo_projeto" required>
        <option value="">— Selecionar tipo —</option>
        <?php
        $tipos = ['residenciais','urbanos','comerciais','industriais'];
        foreach ($tipos as $t):
          $sel = ($projeto['tipo_projeto'] === $t) ? 'selected' : '';
          echo "<option value='$t' $sel>" . ucfirst($t) . "</option>";
        endforeach;
        ?>
      </select>

      <br><br>

      <label><b>Disponibilidade no Website:</b></label><br><br>
      <select name="visibilidade_site" required>
        <option value="nenhum" <?= $projeto['visibilidade_site']==='nenhum'?'selected':'' ?>>Nenhum</option>
        <option value="portugal" <?= $projeto['visibilidade_site']==='portugal'?'selected':'' ?>>Portugal</option>
        <option value="inglaterra" <?= $projeto['visibilidade_site']==='inglaterra'?'selected':'' ?>>Inglaterra</option>
        <option value="ambos" <?= $projeto['visibilidade_site']==='ambos'?'selected':'' ?>>Ambos</option>
      </select>

      <br><br>
      <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>

      <button type="submit">Guardar Configuração</button>

      <?php endif; ?>

    </form>
  </div>
  <?php endif; ?>
  <?php if (!$isFuncionario && !$isCliente && !$isContabilista && !$isComercial): ?>
  <div class="section">
      <h2><i class="bi bi-paperclip"></i> Links de Entrega</h2>
      <br>

      <form method="POST">
          <input type="hidden" name="acao" value="guardar_links">

          <?php
          $labels = [
              1 => "Peças Desenhadas 2D e 3D",
              2 => "Imagens e Visualizações",
              3 => "Nuvens de Pontos",
              4 => "Softwares Gratuitos",
              5 => "Tutoriais"
          ];
          ?>

          <?php for ($i = 1; $i <= 5; $i++): 
              $campo = "link$i"; ?>
              <div style="margin-bottom:12px;">
                  <label><b><?= $labels[$i] ?>:</b></label>
                  <input type="text"
                        name="link<?= $i ?>"
                        placeholder="https://..."
                        style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc;"
                        value="<?= htmlspecialchars($projeto[$campo] ?? '') ?>">
              </div>
          <?php endfor; ?>
            <br>
          <button type="submit">Guardar Links</button>
      </form>
  </div>

  <?php endif; ?>


  <!-- =================== BOTÃO FINAL =================== -->
  <div style="text-align:center; margin-top:40px;">
    <a href="ver_projeto.php?id=<?= $projeto_id ?>" 
       style="background:#a30101;color:white;text-decoration:none;
              padding:14px 28px;border-radius:10px;font-weight:600;
              transition:0.3s;display:inline-block;">
      <i class="bi bi-arrow-left-circle"></i> Ir para Projeto
    </a>
  </div>
</main>

<script>
// adiciona um funcionário a uma área (cria chip + hidden input)
function addFunc(campo) {
  const sel = document.getElementById('sel_' + campo);
  const val = sel.value;
  const txt = sel.options[sel.selectedIndex]?.text || '';
  if (!val) return;

  // impedir duplicados
  const wrap = document.getElementById('chips_' + campo);
  if (wrap.querySelector('span.tag[data-id="'+val+'"]')) return;

  const span = document.createElement('span');
  span.className = 'tag';
  span.dataset.id = val;

  const partes = txt.split(' — ');
  const nome = partes[0] || 'Funcionário';
  const email = partes[1] || '';

  span.innerHTML = `
    <b>${nome}</b> <span>${email}</span>
    <button type="button" class="rm" onclick="removeChip(this)">×</button>
    <input type="hidden" name="equipa_${campo}[]" value="${val}">
  `;

  wrap.appendChild(span);

  // animação suave
  span.style.opacity = "0";
  setTimeout(() => {
      span.style.opacity = "1";
      span.style.transition = "0.25s";
  }, 10);

  sel.value = '';
}


function removeChip(btn) {
  btn.closest('.tag')?.remove();
}
</script>
<button id="btnTopoHeader" class="btn-topo-header" type="button" aria-label="Voltar ao topo" style="position: fixed; right: 18px; bottom: 18px; width: 52px; height: 52px; border: none; border-radius: 14px; cursor: pointer; background: #a30101; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 22px rgba(0,0,0,.18); z-index: 9999; opacity: 0; transform: translateY(10px); pointer-events: none; transition: .25s ease;">
  <i class="bi bi-arrow-up" style="font-size: 20px; line-height: 1;"></i>
</button>

<script>
(function(){
  const btn = document.getElementById("btnTopoHeader");
  if (!btn) return;

  // Tenta detetar o header. Se não existir, usa o topo.
  const header = document.querySelector("header") || document.querySelector(".cabecalho") || document.querySelector("#cabecalho");
  const getHeaderBottom = () => {
    if (!header) return 120; // fallback
    const rect = header.getBoundingClientRect();
    // bottom relativo ao documento (scrollY + bottom do rect)
    return window.scrollY + rect.bottom;
  };

  let headerBottomPx = getHeaderBottom();

  // recalcular em resize (porque o header pode mudar altura)
  window.addEventListener("resize", () => {
    headerBottomPx = getHeaderBottom();
  });

  function onScroll(){
    // mostra só quando já passaste o header (com folga)
    const passou = window.scrollY > (headerBottomPx - 30);
    btn.classList.toggle("show", passou);

    // Estilos no botão diretamente (depois de passar o cabeçalho)
    if (passou) {
      btn.style.opacity = '1';
      btn.style.transform = 'translateY(0)';
      btn.style.pointerEvents = 'auto';
    } else {
      btn.style.opacity = '0';
      btn.style.transform = 'translateY(10px)';
      btn.style.pointerEvents = 'none';
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
})();
</script>

</body>
</html>
