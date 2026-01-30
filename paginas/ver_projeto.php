<?php
include 'protecao.php';
include '../conexao/conexao.php';
include 'cabecalho.php';

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config_email = require '../paginas/config_email.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("Projeto inválido.");

// === Buscar projeto + cliente ===
$stmt = $pdo->prepare("
  SELECT p.*,
         prop.nome_cliente,
         prop.email_cliente,
         prop.total_final,
         prop.pagamento_inicial_valor,
         prop.pagamento_inicial_pago,
         prop.codigo_pais,

         -- flags de email (vindas do preview)
         prop.email_send_all,
         prop.email_send_funcionarios,
         prop.email_send_newsletter
  FROM projetos p
  LEFT JOIN propostas prop ON prop.id = p.proposta_id
  WHERE p.id = ?
");


$stmt->execute([$id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$projeto) die("Projeto não encontrado.");

// ===== flags de email (fallback = 1) =====
$email_send_all         = (int)($projeto['email_send_all'] ?? 1);
$email_send_funcionarios= (int)($projeto['email_send_funcionarios'] ?? 1);
$email_send_newsletter  = (int)($projeto['email_send_newsletter'] ?? 1);

$podeEnviarEmailsFuncionarios = ($email_send_all === 1 && $email_send_funcionarios === 1);
$podeEnviarNewsletter         = ($email_send_all === 1 && $email_send_newsletter === 1);


$isCliente = ($_SESSION['nivel_acesso'] ?? 0) == 6;
$isFuncionario = ($_SESSION['nivel_acesso'] ?? 0) == 5;
$isComercial = ($_SESSION['nivel_acesso'] ?? 0) == 4;



$isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;
$isPropriatário = ($_SESSION['nivel_acesso'] ?? 0) == 2;
$isAdmin = ($_SESSION['nivel_acesso'] ?? 0) == 1;
$userId = $_SESSION['user_id'] ?? 0;


// === Atualizar estado global ou das áreas ===
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // ✅ Marcar restante como pago
    if (isset($_POST['marcar_pago'])) {

        // (opcional) bloquear cliente/funcionário/comercial de marcar como pago
        if ($isCliente || $isFuncionario || $isComercial) {
            header("Location: ver_projeto.php?id=$id");
            exit;
        }

        $updPago = $pdo->prepare("UPDATE projetos SET pago = 1 WHERE id = ?");
        $updPago->execute([$id]);

        // Se já estiver concluído, então fica entregue também
        $updEnt = $pdo->prepare("UPDATE projetos SET entregue = 1 WHERE id = ? AND estado = 'Concluído'");
        $updEnt->execute([$id]);

        header("Location: ver_projeto.php?id=$id");
        exit;
    }
    // ✅ Guardar comentário
    if (isset($_POST['guardar_comentario'])) {

        // (opcional) bloquear clientes de editar comentários
        if ($isCliente) {
            header("Location: ver_projeto.php?id=$id");
            exit;
        }

        $novoComentario = trim($_POST['comentarios'] ?? '');

        $updC = $pdo->prepare("UPDATE projetos SET comentarios = ? WHERE id = ?");
        $updC->execute([$novoComentario, $id]);

        header("Location: ver_projeto.php?id=$id");
        exit;
    }


    if (isset($_POST['novo_estado_projeto'])) {
      $novo_estado = $_POST['novo_estado_projeto'];
    

      // Atualiza estado do projeto
      if ($novo_estado === 'Concluído') {

        // 0) Ler estado anterior + visibilidade + flag (antes do update)
        $stmtPrevP = $pdo->prepare("
            SELECT estado, visibilidade_site, portfolio_newsletter_enviada
            FROM projetos
            WHERE id = ?
            LIMIT 1
        ");
        $stmtPrevP->execute([$id]);
        $prevP = $stmtPrevP->fetch(PDO::FETCH_ASSOC);

        $estadoAnterior = $prevP['estado'] ?? '';
        $visibilidade   = trim((string)($prevP['visibilidade_site'] ?? 'Nenhum'));
        $jaEnviada      = (int)($prevP['portfolio_newsletter_enviada'] ?? 0);

        // 1) Atualizar estado e data de término + marcar entregue
        $upd = $pdo->prepare("UPDATE projetos SET estado=?, data_termino=NOW(), entregue=1 WHERE id=?");
        $upd->execute([$novo_estado, $id]);

        // 2) Detectar transição REAL para Concluído
        $transicaoParaConcluido = ($estadoAnterior !== 'Concluído');

        // 3) Condição da newsletter:
        // - transição para concluído
        // - visibilidade diferente de Nenhum
        // - ainda não enviou
        $deveEnviarNewsletter = (
            $transicaoParaConcluido &&
            $jaEnviada === 0 &&
            $visibilidade !== '' &&
            strcasecmp($visibilidade, 'Nenhum') !== 0
        );

        if ($deveEnviarNewsletter && $podeEnviarNewsletter) {

            // ============================
            // CAPA do projeto (projetos_imagens.tipo='capa')
            // ============================
            $stmtCapa = $pdo->prepare("
                SELECT ficheiro
                FROM projeto_imagens
                WHERE projeto_id = ?
                  AND tipo = 'capa'
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtCapa->execute([$id]);
            $capaFicheiro = (string)($stmtCapa->fetchColumn() ?: '');
            $capaFicheiro = trim($capaFicheiro);


            // ============================
            // Gerar URL absoluto da capa (para email)
            // ============================
            $capaUrl = '';
            if ($capaFicheiro !== '') {

                // 1) limpar ../ e barras iniciais
                $capaFicheiroLimpo = ltrim(str_replace(['..\\', '../'], '', $capaFicheiro), "/\\");

                // 2) Se for só o nome do ficheiro (não tem "/"), constrói pelo teu padrão
                if (strpos($capaFicheiroLimpo, '/') === false && strpos($capaFicheiroLimpo, '\\') === false) {
                    $capaRel = "uploads/projetos/" . (int)$id . "/capa/" . $capaFicheiroLimpo;
                } else {

                    // normalizar separadores para /
                    $capaFicheiroLimpo = str_replace('\\', '/', $capaFicheiroLimpo);

                    // 3) Se já começar por uploads/, mantém
                    if (stripos($capaFicheiroLimpo, 'uploads/') === 0) {
                        $capaRel = $capaFicheiroLimpo;
                    }
                    // 4) Se começar por projetos/, mete uploads/ antes
                    elseif (stripos($capaFicheiroLimpo, 'projetos/') === 0) {
                        $capaRel = "uploads/" . $capaFicheiroLimpo;
                    }
                    // 5) Caso estranho: força o padrão do teu sistema
                    else {
                        // tenta pelo padrão
                        $capaRel = "uploads/projetos/" . (int)$id . "/capa/" . basename($capaFicheiroLimpo);
                    }
                }

                // 6) Construir URL absoluto (para email)
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:3000';

                // Se o teu projeto está em /Supremexpansion/
                $basePath = '/Supremexpansion/';

                $capaUrl = $scheme . '://' . $host . $basePath . $capaRel;
            }



            // A) buscar todos os emails (clientes + funcionários + tudo)
            $stmtAll = $pdo->prepare("
                SELECT DISTINCT email, nome
                FROM utilizadores
                WHERE email IS NOT NULL AND email <> ''
            ");
            $stmtAll->execute();
            $todos = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            // B) deduplicar
            $seen = [];
            $lista = [];
            foreach ($todos as $t) {
                $em = strtolower(trim($t['email'] ?? ''));
                if (!$em) continue;
                if (isset($seen[$em])) continue;
                $seen[$em] = true;
                $lista[] = ['email' => $em, 'nome' => ($t['nome'] ?? $em)];
            }

            // C) link para o portfolio
            $linkPortfolio = "http://localhost:3000/Supremexpansion/paginas/ver_projeto_portfolio.php?id=" . (int)$projeto['id'];
            $nomeProj = htmlspecialchars((string)$projeto['nome_projeto'], ENT_QUOTES, 'UTF-8');

            // D) enviar em lotes (BCC)
            $chunkSize = 60;
            $chunks = array_chunk($lista, $chunkSize);

            try {
                foreach ($chunks as $chunk) {

                    $mailN = new PHPMailer(true);

                    $mailN->isSMTP();
                    $mailN->Host = $config_email['smtp_host'];
                    $mailN->SMTPAuth = true;
                    $mailN->Username = $config_email['smtp_user'];
                    $mailN->Password = $config_email['smtp_pass'];
                    $mailN->SMTPSecure = $config_email['smtp_secure'] === 'tls'
                        ? PHPMailer::ENCRYPTION_STARTTLS
                        : PHPMailer::ENCRYPTION_SMTPS;
                    $mailN->Port = $config_email['smtp_port'];
                    $mailN->CharSet = 'UTF-8';

                    $mailN->setFrom($config_email['from_email'], $config_email['from_name']);

                    // To: o teu email (para não ficar vazio)
                    $mailN->addAddress($config_email['from_email'], $config_email['from_name']);

                    // BCC: toda a gente
                    foreach ($chunk as $p) {
                        $mailN->addBCC($p['email'], $p['nome']);
                    }

                    $mailN->Subject = "Novo projeto concluído no portfólio — {$nomeProj} | SupremeXpansion";
                    $mailN->isHTML(true);

                    $mailN->AltBody = "Publicámos um novo projeto no portfólio: {$projeto['nome_projeto']}. Vê aqui: $linkPortfolio";


                    // bloco da imagem (se existir)
                    $imgHtml = '';
                    if ($capaUrl !== '') {
                        $capaSafe = htmlspecialchars($capaUrl, ENT_QUOTES, 'UTF-8');
                        $imgHtml = "
                          <div style='margin-top:18px;'>
                            <a href='{$linkPortfolio}' style='text-decoration:none;'>
                              <img src='{$capaSafe}'
                                  alt='Capa do projeto'
                                  style='width:100%; max-width:820px; height:auto; display:block; border-radius:16px; border:1px solid #eee;'>
                            </a>
                          </div>
                        ";
                    }

                    $htmlN = "
                      <div style='font-family:Poppins,Arial,sans-serif;background:#fafafa;padding:22px;border-radius:12px;'>
                        <h2 style='color:#a30101;margin:0 0 10px;'>Novo projeto no portfólio</h2>

                        <p style='margin:0 0 10px;'>
                          Um novo projeto foi concluído e publicado no portfólio: <b>{$nomeProj}</b>.
                        </p>

                        <p style='margin:0 0 16px;'>
                          Clica no botão para veres o projeto:
                        </p>

                        <a href='{$linkPortfolio}'
                          style='display:inline-block;background:#a30101;color:#fff;padding:12px 16px;border-radius:10px;
                                text-decoration:none;font-weight:700;'>
                          Ver projeto no portfólio
                        </a>

                        {$imgHtml}

                        <p style='margin-top:18px;font-size:12px;color:#666;'>
                          (Mensagem automática)
                          <!-- DONO: d.pinto@supremexpansion.com (remover facilmente depois) -->
                        </p>
                      </div>
                    ";


                    $mailN->msgHTML($htmlN);
                    $mailN->send();
                }

                // E) marcar como enviada (para nunca repetir)
                $updFlag = $pdo->prepare("UPDATE projetos SET portfolio_newsletter_enviada=1, newsletter_portfolio_data=NOW() WHERE id=?");
                $updFlag->execute([$id]);

            } catch (Exception $e) {
                error_log("Erro ao enviar newsletter portfolio: " . $mailN->ErrorInfo);
            }
        } elseif ($deveEnviarNewsletter && !$podeEnviarNewsletter) {
          error_log("Newsletter portfolio bloqueada (preferências). Projeto ID: " . $id);
        }

        // ===========================
        // 1️⃣ OBTER UTILIZADOR + FLAG (o teu email ao cliente)
        // ===========================
      

        // Atualizar estado e data de término + marcar entregue
        $upd = $pdo->prepare("UPDATE projetos SET estado=?, data_termino=NOW(), entregue=1 WHERE id=?");
        $upd->execute([$novo_estado, $id]);


        // ===========================
        // 1️⃣ OBTER UTILIZADOR + FLAG
        // ===========================
        $stmtU = $pdo->prepare("SELECT tem_primeiro_projeto FROM utilizadores WHERE email = ?");
        $stmtU->execute([$projeto['email_cliente']]);
        $flag_primeiro = $stmtU->fetchColumn();

        $cliente_novo = ($flag_primeiro == 0 || $flag_primeiro === null);

        // Assim que concluir 1 projeto → marcar como antigo
        $u = $pdo->prepare("UPDATE utilizadores SET tem_primeiro_projeto = 1 WHERE email = ?");
        $u->execute([$projeto['email_cliente']]);

        // ===========================
        // 2️⃣ PREPARAR EMAIL
        // ===========================
        
        try {

            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $config_email['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config_email['smtp_user'];
            $mail->Password = $config_email['smtp_pass'];
            $mail->SMTPSecure = $config_email['smtp_secure'] === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $config_email['smtp_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config_email['from_email'], $config_email['from_name']);
            $mail->addAddress($projeto['email_cliente'], $projeto['nome_cliente']);

            // moeda da proposta
            $currency = strtoupper(trim((string)($projeto['codigo_pais'] ?? 'EUR')));
            if ($currency === '') $currency = 'EUR';
            $isEuro = ($currency === 'EUR');

            $link = "http://localhost:3000/Supremexpansion/paginas/ver_projeto.php?id=" . $projeto['id'];

            $mail->isHTML(true);

            // AltBody (texto simples)
            if ($isEuro) {
                $mail->AltBody = "O seu projeto foi concluído. Consulte através do link: $link";
            } else {
                $mail->AltBody = "Your project has been completed. View it here: $link";
            }

            if ($cliente_novo) {

                if ($isEuro) {
                    // 🎉 PRIMEIRO PROJETO CONCLUÍDO (PT)
                    $mail->Subject = "O seu primeiro projeto foi concluído! | SupremeXpansion";

                    $html = "
                    <div style='font-family:Poppins,sans-serif;background:#fafafa;padding:25px;border-radius:12px;'>
                        <h2 style='color:#a30101;'>Olá {$projeto['nome_cliente']},</h2>

                        <p>Temos excelentes notícias!</p>
                        <p>O seu <b>primeiro projeto</b> connosco foi <b>concluído com sucesso</b>.</p>

                        <p>Pode consultar todos os detalhes através do link abaixo:</p>

                        <a href='$link'
                            style='display:inline-block;margin-top:12px;background:#a30101;color:white;
                                    padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:bold;'>
                            Ver Projeto Concluído
                        </a>

                        <br><br>
                        <p>Obrigado pela confiança e por iniciar esta jornada connosco.</p>
                        <p style='font-size:13px;color:#555;'>SupremeXpansion</p>
                    </div>";
                } else {
                    // 🎉 FIRST COMPLETED PROJECT (EN)
                    $mail->Subject = "Your first project has been completed! | SupremeXpansion";

                    $html = "
                    <div style='font-family:Poppins,sans-serif;background:#fafafa;padding:25px;border-radius:12px;'>
                        <h2 style='color:#a30101;'>Hello {$projeto['nome_cliente']},</h2>

                        <p>We have great news!</p>
                        <p>Your <b>first project</b> with us has been <b>successfully completed</b>.</p>

                        <p>You can view all the details using the link below:</p>

                        <a href='$link'
                            style='display:inline-block;margin-top:12px;background:#a30101;color:white;
                                    padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:bold;'>
                            View Completed Project
                        </a>

                        <br><br>
                        <p>Thank you for your trust and for starting this journey with us.</p>
                        <p style='font-size:13px;color:#555;'>SupremeXpansion</p>
                    </div>";
                }

            } else {

                if ($isEuro) {
                    // ⭐ CLIENTE ANTIGO (PT)
                    $mail->Subject = "O seu projeto foi concluído | SupremeXpansion";

                    $html = "
                    <div style='font-family:Poppins,sans-serif;background:#fafafa;padding:25px;border-radius:12px;'>
                        <h2 style='color:#a30101;'>Olá {$projeto['nome_cliente']},</h2>

                        <p>O seu projeto <b>{$projeto['nome_projeto']}</b> foi concluído com sucesso.</p>

                        <p>Consulte todos os detalhes através do link:</p>

                        <a href='$link'
                            style='display:inline-block;margin-top:12px;background:#a30101;color:white;
                                    padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:bold;'>
                            Ver Projeto Concluído
                        </a>

                        <br><br>
                        <p>Agradecemos a sua confiança contínua na SupremeXpansion.</p>
                    </div>";
                } else {
                    // ⭐ RETURNING CLIENT (EN)
                    $mail->Subject = "Your project has been completed | SupremeXpansion";

                    $html = "
                    <div style='font-family:Poppins,sans-serif;background:#fafafa;padding:25px;border-radius:12px;'>
                        <h2 style='color:#a30101;'>Hello {$projeto['nome_cliente']},</h2>

                        <p>Your project <b>{$projeto['nome_projeto']}</b> has been successfully completed.</p>

                        <p>You can view all the details here:</p>

                        <a href='$link'
                            style='display:inline-block;margin-top:12px;background:#a30101;color:white;
                                    padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:bold;'>
                            View Completed Project
                        </a>

                        <br><br>
                        <p>Thank you for your continued trust in SupremeXpansion.</p>
                    </div>";
                }
            }


            // 🔥 HTML FORMATADO CORRETAMENTE
            $mail->msgHTML($html);

            // Enviar email
            $mail->send();

        } catch (Exception $e) {
            error_log("Erro ao enviar email de conclusão: " . $mail->ErrorInfo);
        }

    

      } else {
          // outros estados
          $upd = $pdo->prepare("UPDATE projetos SET estado=?, data_termino=NULL WHERE id=?");
          $upd->execute([$novo_estado, $id]);
      }

      
      

      header("Location: ver_projeto.php?id=$id");
      exit;
    }



    if (isset($_POST['novo_estado_area'], $_POST['area'])) {

      // (opcional) bloquear cliente de alterar áreas
      if ($isCliente) {
          header("Location: ver_projeto.php?id=$id");
          exit;
      }

      $area = $_POST['area'];
      $novo_estado = $_POST['novo_estado_area'];

      // 1) Buscar estado anterior (para saber se houve transição)
      $stmtPrev = $pdo->prepare("SELECT estado FROM projetos_areas WHERE projeto_id=? AND area=? LIMIT 1");
      $stmtPrev->execute([$id, $area]);
      $estadoAnterior = $stmtPrev->fetchColumn();

      // 2) Atualizar estado
      $upd = $pdo->prepare("UPDATE projetos_areas SET estado=? WHERE projeto_id=? AND area=?");
      $upd->execute([$novo_estado, $id, $area]);

      // 3) Só dispara email se mudou para Concluído (e antes não era Concluído)
      $transicaoParaConcluido = ($novo_estado === 'Concluído' && $estadoAnterior !== 'Concluído');

      if ($transicaoParaConcluido && $podeEnviarEmailsFuncionarios) {

          // ============================
          // DESTINATÁRIOS (equipa + gestor + dono)
          // ============================

          // A) Todos os funcionários atribuídos a QUALQUER área do projeto
          $stmtEmails = $pdo->prepare("
              SELECT DISTINCT u.email, u.nome
              FROM projetos_funcionarios pf
              JOIN utilizadores u ON u.id = pf.funcionario_id
              WHERE pf.projeto_id = ?
                AND u.email IS NOT NULL
                AND u.email <> ''
          ");
          $stmtEmails->execute([$id]);
          $destinatarios = $stmtEmails->fetchAll(PDO::FETCH_ASSOC);

          $stmtGestor = $pdo->prepare("SELECT u.email, u.nome FROM projetos p JOIN utilizadores u ON u.id = p.gestor_projeto_id WHERE p.id=? LIMIT 1");
          $stmtGestor->execute([$id]);
          $gestor = $stmtGestor->fetch(PDO::FETCH_ASSOC);

          $ownerEmail = 'd.pinto@supremexpansion.com';

          // Junta tudo (sem duplicados)
          $emails = [];
          $listaFinal = [];

          foreach ($destinatarios as $d) {
              $em = strtolower(trim($d['email'] ?? ''));
              if ($em && !isset($emails[$em])) {
                  $emails[$em] = true;
                  $listaFinal[] = $d;
              }
          }

          if (!empty($gestor['email'])) {
            $em = strtolower(trim($gestor['email']));
            if ($em && !isset($emails[$em])) { $emails[$em]=true; $listaFinal[]=$gestor; }
          }

          if (!empty($ownerEmail)) {
            $em = strtolower(trim($ownerEmail));
            if ($em && !isset($emails[$em])) {
              $emails[$em] = true;
              $listaFinal[] = ['email' => $ownerEmail, 'nome' => 'SupremeXpansion'];
            }
          }

          // ============================
          // ENVIAR EMAIL (PT para todos)
          // ============================
          if (!empty($listaFinal)) {
            try {
                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host = $config_email['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config_email['smtp_user'];
                $mail->Password = $config_email['smtp_pass'];
                $mail->SMTPSecure = $config_email['smtp_secure'] === 'tls'
                    ? PHPMailer::ENCRYPTION_STARTTLS
                    : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = $config_email['smtp_port'];
                $mail->CharSet = 'UTF-8';

                $mail->setFrom($config_email['from_email'], $config_email['from_name']);

                foreach ($listaFinal as $p) {
                    $mail->addAddress($p['email'], $p['nome'] ?? $p['email']);
                }

                $link = "http://localhost:3000/Supremexpansion/paginas/ver_projeto.php?id=" . (int)$projeto['id'];

                $assuntoArea = htmlspecialchars((string)$area, ENT_QUOTES, 'UTF-8');
                $nomeProj = htmlspecialchars((string)$projeto['nome_projeto'], ENT_QUOTES, 'UTF-8');

                $mail->Subject = "Área concluída — $nomeProj ($assuntoArea) | SupremeXpansion";
                $mail->isHTML(true);

                $mail->AltBody = "A área '$area' do projeto '$nomeProj' foi marcada como Concluída. Link: $link";

                $html = "
                  <div style='font-family:Poppins,Arial,sans-serif;background:#fafafa;padding:22px;border-radius:12px;'>
                    <h2 style='color:#a30101;margin:0 0 10px;'>Área concluída</h2>

                    <p style='margin:0 0 10px;'>
                      A área <b>{$assuntoArea}</b> do projeto <b>{$nomeProj}</b> foi marcada como <b>Concluída</b>.
                    </p>

                    <p style='margin:0 0 16px;'>Podes consultar o projeto aqui:</p>

                    <a href='{$link}'
                      style='display:inline-block;background:#a30101;color:#fff;padding:12px 16px;border-radius:10px;
                            text-decoration:none;font-weight:700;'>
                      Ver projeto
                    </a>

                    <p style='margin-top:18px;font-size:12px;color:#666;'>
                      (Mensagem automática — enviada a todos os atribuídos ao projeto)
                    </p>
                  </div>
                ";

                $mail->msgHTML($html);
                $mail->send();

            } catch (Exception $e) {
                error_log("Erro ao enviar email de área concluída: " . $mail->ErrorInfo);
            }
          }
      } elseif ($transicaoParaConcluido && !$podeEnviarEmailsFuncionarios) {
        error_log("Email de área concluída bloqueado (preferências). Projeto ID: " . $id . " | Área: " . $area);
      }

      header("Location: ver_projeto.php?id=$id");
      exit;
  }

}

// === Estados das áreas ===
$areasRaw = $pdo->prepare("SELECT area, estado FROM projetos_areas WHERE projeto_id = ? ORDER BY area");
$areasRaw->execute([$id]);
$areas = $areasRaw->fetchAll(PDO::FETCH_ASSOC);

// === Funcionários ===
$atr = $pdo->prepare("
  SELECT u.id, pf.area, u.nome, u.email
  FROM projetos_funcionarios pf
  JOIN utilizadores u ON u.id = pf.funcionario_id
  WHERE pf.projeto_id = ?
  ORDER BY pf.area, u.nome
");
$atr->execute([$id]);
$equipa = [
    'LEVANTAMENTO' => [],  // 🔥 NOVA ÁREA (fica em primeiro)
    '3D' => [],
    '2D' => [],
    'BIM' => []
];

foreach ($atr->fetchAll(PDO::FETCH_ASSOC) as $f) $equipa[$f['area']][] = $f;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="../img/icon.png">
<title><?= htmlspecialchars($projeto['nome_projeto']) ?> | Projeto</title>
<link rel="stylesheet" href="../css/ver_projeto.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>
<body>
    <br>
<main>

<h1>
  Projeto <?= htmlspecialchars($projeto['nome_projeto']) ?>
  
</h1>
<br>
<a href="javascript:history.back()" class="btn-voltar"><i class="bi bi-arrow-left"></i> Voltar</a>
<br>
<?php if (!$isFuncionario && !$isComercial): ?>

<a href="ver_proposta.php?id=<?= $projeto['proposta_id'] ?>" class="btn-voltar" style="float:left;"><i class="bi bi-arrow-left"></i> Ver Proposta</a>

<?php endif; ?>

<?php $moeda = strtoupper(trim((string)($projeto['codigo_pais'] ?? 'EUR'))); ?>

<br><br>

<div id="fxBox" style="margin:12px 0; padding:12px; border:1px solid #eee; border-radius:12px; background:#fafafa;">
  <div style="font-weight:700; margin-bottom:8px;">Conversões (editável)</div>

  <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
    <div style="display:flex; gap:8px; align-items:center;">
      <span style="min-width:70px;">1 USD =</span>
      <input id="fx_USD" type="number" step="0.000001" min="0" value="0.85"
            style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
      <span>EUR</span>
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
      <span style="min-width:70px;">1 GBP =</span>
      <input id="fx_GBP" type="number" step="0.000001" min="0" value="1.15"
            style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
      <span>EUR</span>
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
      <span style="min-width:70px;">1 JPY =</span>
      <input id="fx_JPY" type="number" step="0.000001" min="0" value="0.0055"
            style="width:120px; padding:8px; border:1px solid #ddd; border-radius:10px;">
      <span>EUR</span>
    </div>
  </div>

  <div style="font-size:12px; color:#666; margin-top:8px;">
    * EUR é a base (1 EUR = 1 EUR). Ajusta os valores se precisares.
  </div>
</div>
<section class="info">
  <p><b>Cliente:</b> <?= htmlspecialchars($projeto['nome_cliente']) ?> (<?= htmlspecialchars($projeto['email_cliente']) ?>)</p>
  <div class="kpi"><b>Obra:</b> <?= htmlspecialchars($projeto['nome_obra']) ?></div>
    <div class="kpi"><b>Total m² do Projeto:</b> 
        <?php
            $stmt_m2 = $pdo->prepare("SELECT SUM(metros_quadrados) FROM areas_proposta WHERE id_proposta = ?");
            $stmt_m2->execute([$projeto['proposta_id']]);
            $total_m2 = $stmt_m2->fetchColumn() ?? 0;
            echo number_format($total_m2, 2, ',', '.') . ' m²';
        ?>
    </div>
  <?php if (!$isFuncionario && !$isComercial): ?>

  <div class="kpi"><b>Valor Total:</b>
    <span class="money"
          data-eur="<?= htmlspecialchars((string)((float)($projeto['total_final'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
          data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
      <?= number_format((float)($projeto['total_final'] ?? 0), 2, ',', '.') ?>
    </span>
  </div>

  <div class="kpi"><b>Pago Inicial:</b>
    <span class="money"
          data-eur="<?= htmlspecialchars((string)((float)($projeto['pagamento_inicial_valor'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
          data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
      <?= number_format((float)($projeto['pagamento_inicial_valor'] ?? 0), 2, ',', '.') ?>
    </span>
  </div>

  <?php
    // Restante: se não pagou inicial, falta tudo
    $pagoInicial = !empty($projeto['pagamento_inicial_pago']) ? (float)($projeto['pagamento_inicial_valor'] ?? 0) : 0;
    $totalFinal  = (float)($projeto['total_final'] ?? 0);
    $restante    = max(0, $totalFinal - $pagoInicial);

    $estaPago = !empty($projeto['pago']) ? 1 : 0;
  ?>
  <div class="kpi" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <div><b>Restante:</b>
      <span class="money"
            data-eur="<?= htmlspecialchars((string)((float)$restante), ENT_QUOTES, 'UTF-8') ?>"
            data-currency="<?= htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') ?>">
        <?= number_format((float)$restante, 2, ',', '.') ?>
      </span>
    </div>


    <span class="badge <?= $estaPago ? 'adjudicada' : 'pendente' ?>">
      <i class="bi <?= $estaPago ? 'bi-check-circle-fill' : 'bi-hourglass-split' ?>"></i>
      <?= $estaPago ? 'Pago' : 'Não Pago' ?>
    </span>


    <?php if (!$isCliente && !$isFuncionario && !$isComercial && !$estaPago): ?>
      <form method="POST" class="inline" id="formMarcarPago">
        <input type="hidden" name="marcar_pago" value="1">
        <button type="button" class="btn-confirmar-estado" id="btnMarcarPago">
          <i class="bi bi-cash-coin"></i> Marcar Pago
        </button>

      </form>
    <?php endif; ?>
  </div>

  <?php endif; ?>

  <div class="kpi"><b>Estado Global:</b> 
    <span class="badge <?= str_replace(' ','-',$projeto['estado']) ?>"><?= htmlspecialchars($projeto['estado']) ?></span>
    <?php if (!$isCliente): ?>
      <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>
        <form method="POST" class="inline">
              <select name="novo_estado_projeto">

          <option value="">Alterar estado...</option>
          <?php foreach (['Em processamento','Em produção','Em espera','Concluído','Cancelado'] as $op): ?>
            <option value="<?= $op ?>"><?= $op ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn-confirmar-estado" type="button">Atualizar</button>
      <?php if (!$isFuncionario && !$isContabilista && !$isComercial): ?>
      </form>
      <?php endif; ?>
      
      
      <?php endif; ?>

    <?php endif; ?>

  </div>
   <div class="kpi"><b>Data de Término:</b> 
        <?= $projeto['data_termino'] ? date('d/m/Y H:i', strtotime($projeto['data_termino'])) : '—' ?>
    </div>

</section>

<?php if (!$isContabilista): ?>

  <section class="areas">
    <h2>Áreas e Equipa</h2>
    <?php 
      // lista áreas onde o utilizador pertence
      $stmtPermit = $pdo->prepare("
          SELECT area FROM projetos_funcionarios 
          WHERE projeto_id = ? AND funcionario_id = ?
      ");
      $stmtPermit->execute([$id, $userId]);
      $areasPermitidas = $stmtPermit->fetchAll(PDO::FETCH_COLUMN);
    ?>

    <?php foreach ($areas as $a): ?>

        <div class="area-card">
          <h3><?= htmlspecialchars($a['area']) ?></h3>

          <p><b>Estado:</b> 
            <span class="badge <?= str_replace(' ','-', $a['estado']) ?>">
              <?= htmlspecialchars($a['estado']) ?>
            </span>

            <?php
                $podeEditarArea = (
                    ($isFuncionario || $isComercial)
                    && in_array($a['area'], $areasPermitidas) || $isPropriatário ||$isAdmin
                );
            ?>

            <?php if ($podeEditarArea): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="area" value="<?= htmlspecialchars($a['area']) ?>">
              <br>

              <select name="novo_estado_area">
                <option value="">Alterar...</option>
                <?php foreach (['Em processamento','Em produção','Em espera','Concluído','Cancelado'] as $op): ?>
                  <option value="<?= $op ?>"><?= $op ?></option>
                <?php endforeach; ?>
              </select>

              <button class="btn-confirmar-estado" type="button">Atualizar</button>
            </form>
            <?php endif; ?>

          </p>

          <?php if (!$isCliente): ?>
            <br><p><b>Equipa:</b></p><br>

            <?php if (empty($equipa[$a['area']])): ?>
              <p><i>Sem funcionários atribuídos.</i></p>
            <?php else: ?>
              <ul>
                <?php foreach ($equipa[$a['area']] as $f): ?>
                <li>
                  <?php if ($isFuncionario || $isComercial && (int)$f['id'] !== (int)$userId): ?>
                    <span class="link-funcionario" style="cursor:not-allowed; opacity:.75; text-decoration:none;">
                      <?= htmlspecialchars($f['nome']) ?>
                    </span>
                  <?php else: ?>
                    <a href="ver_funcionario.php?id=<?= (int)$f['id'] ?>" class="link-funcionario">
                      <?= htmlspecialchars($f['nome']) ?>
                    </a>
                  <?php endif; ?>

                  — <?= htmlspecialchars($f['email']) ?>
                </li>
                <br>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
        <?php endif; ?>


        </div>
      <?php endforeach; ?>


    </section>

    <?php if ($projeto['estado'] === 'Concluído'): ?>
    <?php if (!$isFuncionario && !$isComercial): ?>

    <?php
    $labels = [
        1 => "Peças Desenhadas 2D e 3D",
        2 => "Imagens e Visualizações",
        3 => "Nuvens de Pontos",
        4 => "Softwares Gratuitos",
        5 => "Tutoriais"
    ];

    $defaultLinks = [
        4 => "https://drive.google.com/drive/folders/1Ei6LiJTS1IPBjCEei-fCgnZ9o_wBsq0i", 
        5 => "https://drive.google.com/drive/folders/1qVr4f3Qxz0AlDoPPdGBV6Xv3X6c9LioW?usp=sharing"
    ];
    ?>
    <?php if (!$isCliente): ?>
      <div class="kpi" style="width:100%;">
        <b>Comentários:</b>
        <form method="POST" class="inline" id="formGuardarComentario" style="margin-top:8px;">
          <textarea
            name="comentarios"
            rows="3"
            style="width:100%; max-width:900px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:12px; font-family:inherit; font-size:14px; outline:none;"
            placeholder="Escreve aqui um comentário interno…"
          ><?= htmlspecialchars((string)($projeto['comentarios'] ?? '')) ?></textarea>

          <input type="hidden" name="guardar_comentario" value="1">

          <div style="margin-top:10px;">
            <button type="button" class="btn-confirmar-estado" id="btnGuardarComentario">
              <i class="bi bi-save2-fill"></i> Guardar
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <section style="margin-top:40px;">
        <h2 style="text-align:center; margin-bottom:20px;"><i class="bi bi-box-seam"></i> Downloads / Recursos do Projeto</h2>

        <div style="
            display:flex;
            flex-direction:column;
            gap:12px;
            max-width:500px;
            margin:0 auto;
        ">

            <?php for ($i = 1; $i <= 5; $i++): 
                $campo = "link{$i}";
                $link = $projeto[$campo] ?: ($defaultLinks[$i] ?? null);

                if ($link):
            ?>
                <a href="<?= htmlspecialchars($link) ?>" 
                  target="_blank"
                  style="
                    background:#a30101;
                    color:white;
                    padding:12px 18px;
                    border-radius:10px;
                    text-align:center;
                    font-weight:600;
                    text-decoration:none;
                    transition:.3s;
                  "
                >
                    <i class="bi bi-folder-fill"></i> <?= $labels[$i] ?>
                </a>
            <?php endif; ?>
            <?php endfor; ?>

        </div>
    </section>


    <?php endif; ?>

    <?php endif; ?>

  <?php if (!$isCliente): ?>
  <section style="margin-top:25px; text-align:center;">
    <a href="configurar_projeto.php?id=<?= $projeto['id'] ?>" 
      style="background:#a30101; color:#fff; text-decoration:none; padding:10px 16px; border-radius:8px;">
      <i class="bi bi-gear-fill"></i> Reconfigurar Projeto
    </a>
  </section>
  <?php endif; ?>
<?php endif; ?>

</main>
<div id="popupConfirm" class="popup-overlay">
  <div class="popup-box">
    <img src="../img/icon.png" class="popup-logo">
    <h3><i class="bi bi-question-circle-fill"></i> Confirmação</h3>

    <p id="popupConfirmMsg">Tem a certeza?</p>

    <div class="popup-buttons">
      <button id="popupConfirmYes">Sim</button>
      <button id="popupConfirmNo">Não</button>
    </div>
  </div>
</div>
<script>
function popupConfirm(mensagem, onConfirm) {
    const overlay = document.getElementById("popupConfirm");
    const msg = document.getElementById("popupConfirmMsg");
    const yes = document.getElementById("popupConfirmYes");
    const no = document.getElementById("popupConfirmNo");

    msg.textContent = mensagem;
    overlay.style.display = "flex";

    yes.onclick = () => {
        overlay.style.display = "none";
        if (onConfirm) onConfirm();
    };

    no.onclick = () => {
        overlay.style.display = "none";
    };
}

</script>
<script>
document.querySelectorAll(".btn-confirmar-estado").forEach(botao => {
    botao.addEventListener("click", function() {
        const form = botao.closest("form");

        popupConfirm(
            "Tem a certeza que quer alterar o estado deste projeto?",
            () => form.submit()
        );
    });
});
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
<script>
const btnPago = document.getElementById("btnMarcarPago");
if (btnPago) {
  btnPago.addEventListener("click", function () {
    const form = document.getElementById("formMarcarPago");
    popupConfirm(
      "Tem a certeza que o restante foi pago? Se confirmar, o projeto ficará como PAGO.",
      () => form.submit()
    );
  });
}
</script>
<script>
const btnGuardarComentario = document.getElementById("btnGuardarComentario");
if (btnGuardarComentario) {
  btnGuardarComentario.addEventListener("click", function () {
    const form = document.getElementById("formGuardarComentario");
    popupConfirm(
      "Tem a certeza que quer guardar este comentário?",
      () => form.submit()
    );
  });
}
</script>
<script>
(() => {
  'use strict';

  const CURRENCY_META = {
    EUR: { decimals: 2 },
    USD: { decimals: 2 },
    GBP: { decimals: 2 },
    JPY: { decimals: 0 },
  };

  // 1 [moeda] = X EUR (editável)
  const FX = {
    EUR: 1,
    USD: 0.85,
    GBP: 1.15,
    JPY: 0.0055,
  };

  function safeNum(v){
    const n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function formatCurrency(amount, code) {
    const meta = CURRENCY_META[code] || CURRENCY_META.EUR;
    const n = Number(amount || 0);

    try {
      return new Intl.NumberFormat("pt-PT", {
        style: "currency",
        currency: code,
        minimumFractionDigits: meta.decimals,
        maximumFractionDigits: meta.decimals
      }).format(n);
    } catch (e) {
      return n.toFixed(meta.decimals).replace(".", ",") + " " + code;
    }
  }

  // EUR -> moeda (usando taxa 1 [moeda] = X EUR)
  function eurToCurrency(eur, code){
    code = (code || 'EUR').toUpperCase();
    if (code === 'EUR') return eur;
    const rate = FX[code];
    if (!rate || rate <= 0) return 0;
    return eur / rate;
  }

  function ensureSubline(el){
    let sub = el.querySelector(":scope > .money-sub");
    if (!sub) {
      sub = document.createElement("div");
      sub.className = "money-sub";
      sub.style.fontSize = "11px";
      sub.style.color = "#666";
      sub.style.marginTop = "3px";
      sub.style.whiteSpace = "nowrap";
      sub.style.lineHeight = "1.1";
      el.appendChild(sub);
    }
    return sub;
  }

  function renderMoney(el){
    const eur = safeNum(el.dataset.eur);
    const code = (el.dataset.currency || 'EUR').toUpperCase();
    const mainText = formatCurrency(eurToCurrency(eur, code), code);

    el.innerHTML = "";
    const main = document.createElement("div");
    main.className = "money-main";
    main.style.fontWeight = "900";
    main.textContent = mainText;
    el.appendChild(main);

    if (code !== 'EUR') {
      const sub = ensureSubline(el);
      sub.textContent = "≈ " + formatCurrency(eur, "EUR");
    }
  }

  function refreshAll(){
    document.querySelectorAll(".money[data-eur][data-currency]").forEach(renderMoney);
  }

  // inputs
  const inUSD = document.getElementById("fx_USD");
  const inGBP = document.getElementById("fx_GBP");
  const inJPY = document.getElementById("fx_JPY");

  function bindFx(input, code){
    if (!input) return;
    input.addEventListener("input", () => {
      const v = safeNum(input.value);
      if (v > 0) FX[code] = v;
      refreshAll();
    });
    const v0 = safeNum(input.value);
    if (v0 > 0) FX[code] = v0;
  }

  bindFx(inUSD, "USD");
  bindFx(inGBP, "GBP");
  bindFx(inJPY, "JPY");

  refreshAll();
})();
</script>

</body>
</html>
