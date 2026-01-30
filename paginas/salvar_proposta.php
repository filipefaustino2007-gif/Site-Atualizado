<?php
include '../conexao/conexao.php';
include '../conexao/funcoes.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Ativar erros só para log (evita lixo no output)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "FATAL: {$e['message']}\n{$e['file']}:{$e['line']}\n";
  }
});

// ================================
// Helpers de cêntimos (EUR base)
// ================================
if (!function_exists('toCents')) {
  function toCents($v): int {
    return (int) round(((float)$v) * 100);
  }
}

if (!function_exists('fromCents')) {
  function fromCents(int $c): float {
    return $c / 100.0;
  }
}

function fmtPT_EUR(float $v): string {

    return '€' . number_format($v, 2, ',', '.');

}

// para inglês: 1,234.56 USD / 1,234.56 GBP / 1,234 JPY

function fmtEN_CCY(float $v, string $code): string {

    $code = strtoupper($code);

    $decimals = ($code === 'JPY') ? 0 : 2;

    return number_format($v, $decimals, '.', ',') . ' ' . $code;

}


if (!function_exists('distribuirEmCents')) {
  function distribuirEmCents(int $totalCents, int $n): array {
    if ($n <= 0) return [];
    $base = intdiv($totalCents, $n);
    $out  = array_fill(0, $n, $base);
    $rem  = $totalCents - ($base * $n);
    for ($i = 0; $i < $rem; $i++) $out[$i] += 1;
    return $out;
  }
}

if (!function_exists('ivaCents')) {
  function ivaCents(int $netCents): int {
    return (int) round($netCents * 23 / 100);
  }
}
if (!empty($_POST['confirmar_preview']) && !empty($_POST['id_proposta'])) {

    $id_proposta = (int)$_POST['id_proposta'];
    if ($id_proposta <= 0) die("ID inválido.");

    // ============================
    // Preferências de envio (vêm do preview)
    // ============================
    $email_send_all         = !empty($_POST['email_send_all']) ? 1 : 0;
    $email_send_proposta    = !empty($_POST['email_send_proposta']) ? 1 : 0;
    $email_send_credenciais = !empty($_POST['email_send_credenciais']) ? 1 : 0;
    $email_send_func        = !empty($_POST['email_send_funcionarios']) ? 1 : 0;
    $email_send_newsletter  = !empty($_POST['email_send_newsletter']) ? 1 : 0;

    // Se master está ON, mantém os toggles como vieram (normalmente vêm todos ON).
    // Se master está OFF, não envia nada, mas guardamos na mesma os toggles individuais.
    // (Se preferires: quando all=1 forçar todos=1, diz-me e eu ajusto.)
    $updPrefs = $pdo->prepare("
        UPDATE propostas SET
        email_send_all = ?,
        email_send_proposta = ?,
        email_send_credenciais = ?,
        email_send_funcionarios = ?,
        email_send_newsletter = ?
        WHERE id = ?
        LIMIT 1
    ");
    $updPrefs->execute([
    $email_send_all,
    $email_send_proposta,
    $email_send_credenciais,
    $email_send_func,
    $email_send_newsletter,
    $id_proposta
    ]);


    // buscar dados completos
    $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
    $stmt->execute([$id_proposta]);
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposta) die("Proposta não encontrada.");

    $stmt2 = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
    $stmt2->execute([$id_proposta]);
    $areas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $pdo->prepare("
        SELECT sp.*, COALESCE(s.nome, sp.nome_servico) AS nome_base
        FROM servicos_proposta sp
        LEFT JOIN servicos_produtos s ON s.id = sp.id_servico
        WHERE sp.id_proposta = ?
        ORDER BY sp.id_servico ASC
    ");
    $stmt3->execute([$id_proposta]);
    $servicosSelecionados = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // globals para o PDF
    $GLOBALS['proposta'] = $proposta;
    $GLOBALS['areas'] = $areas;
    $GLOBALS['servicosSelecionados'] = $servicosSelecionados;
    $GLOBALS['servicos'] = $servicosSelecionados;

    // modo NORMAL (pdf final)
    unset($GLOBALS['PDF_PREVIEW_MODE'], $GLOBALS['PDF_PREVIEW_PATH']);

    ob_start();
    $pdf_path = include './teste_pdf.php';
    ob_end_clean();


        // --- email (só se permitido) ---
    $podeEnviarEmailProposta = ($email_send_all == 1 && $email_send_proposta == 1);

    if ($podeEnviarEmailProposta) {
        require '../vendor/autoload.php';
        $config = require '../paginas/config_email.php';

        $email_cliente = (string)($proposta['email_cliente'] ?? '');
        $nome_cliente  = (string)($proposta['nome_cliente'] ?? '');
        $nome_obra     = (string)($proposta['nome_obra'] ?? '');
        $codigo_pais   = strtoupper(trim((string)($proposta['codigo_pais'] ?? 'EUR')));
        if ($codigo_pais === '') $codigo_pais = 'EUR';

        $total_final   = (float)($proposta['total_final'] ?? 0);

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // valores em EUR (base)

        $totalFinalEUR = (float)($total_final ?? 0);

        $totalLiquidoEUR = (float)($total_liquido ?? 0);

        $totalIvaEUR = (float)($total_iva ?? 0);

        

        // convertidos para a moeda da proposta (se não EUR)

        $totalFinalCCY = eurToCurrency($totalFinalEUR, $currency, $FX);

        $totalLiquidoCCY = eurToCurrency($totalLiquidoEUR, $currency, $FX);

        $totalIvaCCY = eurToCurrency($totalIvaEUR, $currency, $FX);
        try {

            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->SMTPSecure = ($config['smtp_secure'] === 'tls')
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $config['smtp_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($email_cliente, $nome_cliente);
            //$mail->addCC('info@supremexpansion.com', 'SupremeXpansion');

            $mail->isHTML(true);  // OBRIGATÓRIO E DEVE VIR ANTES DO BODY
            $mail->AltBody = "A sua proposta está em anexo."; // versão texto

            $isEuro = (isset($currency) && $currency === 'EUR');

            if ($isEuro) {
                $valorLinha = "<p><b>Total:</b> " . (function_exists('fmtPT_EUR') ? fmtPT_EUR($total_final ?? 0) : '') . "</p>";
            } else {
                $valorLinha = "
                    <p><b>Total:</b> " . (function_exists('fmtEN_CCY') ? fmtEN_CCY($totalFinalCCY ?? 0, $currency ?? '') : '') . "</p>
                    <p style='font-size:12px;color:#666;margin-top:-6px;'>
                    (Calculated from EUR base. Exchange rates can be adjusted in the platform.)
                    </p>
                ";
            }

            // ------------------------------
            //  TEXTO DIFERENTE PARA NOVO/ANTIGO CLIENTE
            // ------------------------------
            if (!empty($cliente_novo)) {

                if ($isEuro) {
                    $html = "
                    <div style='font-family:Poppins, sans-serif; background:#fafafa; padding:18px; border-radius:10px;'>
                    <h2 style='color:#a30101;'>Bem-vindo(a), $nome_cliente!</h2>
                    <p>Agradecemos por escolher a <b>SupremeXpansion</b> para o seu primeiro projeto.</p>
                    <p>Em anexo encontra a sua proposta referente à obra: <b>$nome_obra</b>.</p>
                    $valorLinha
                    <p>Qualquer dúvida, estamos totalmente ao dispor.</p>
                    <br>
                    <p>Melhores cumprimentos,<br><b>SupremeXpansion</b></p>
                    </div>";
                    $mail->Subject = "Bem-vindo à SupremeXpansion — A sua proposta está pronta";
                } else {
                    $html = "
                    <div style='font-family:Poppins, sans-serif; background:#fafafa; padding:18px; border-radius:10px;'>
                    <h2 style='color:#a30101;'>Welcome, $nome_cliente!</h2>
                    <p>Thank you for choosing <b>SupremeXpansion</b> for your first project.</p>
                    <p>Please find attached your proposal for: <b>$nome_obra</b>.</p>
                    $valorLinha
                    <p>If you have any questions, we are happy to help.</p>
                    <br>
                    <p>Kind regards,<br><b>SupremeXpansion</b></p>
                    </div>";
                    $mail->Subject = "Welcome to SupremeXpansion — Your proposal is ready";
                }

            } else {

                if ($isEuro) {
                    $html = "
                    <div style='font-family:Poppins, sans-serif; background:#fafafa; padding:18px; border-radius:10px;'>
                    <h2 style='color:#a30101;'>Olá novamente, $nome_cliente!</h2>
                    <p>Aqui está a sua nova proposta.</p>
                    <p>Proposta referente à obra: <b>$nome_obra</b>.</p>
                    $valorLinha
                    <p>Obrigado por continuar a confiar na SupremeXpansion.</p>
                    <br>
                    <p>Melhores cumprimentos,<br><b>SupremeXpansion</b></p>
                    </div>";
                    $mail->Subject = "A sua nova proposta está disponível | SupremeXpansion";
                } else {
                    $html = "
                    <div style='font-family:Poppins, sans-serif; background:#fafafa; padding:18px; border-radius:10px;'>
                    <h2 style='color:#a30101;'>Hello again, $nome_cliente!</h2>
                    <p>Please find attached your Quotation</p>
                    <p>Quotation for: <b>$nome_obra</b>.</p>
                    $valorLinha
                    <p>Thank you for your continued trust in SupremeXpansion.</p>
                    <br>
                    <p>Kind regards,<br><b>SupremeXpansion</b></p>
                    </div>";
                    $mail->Subject = "Your updated proposal is available | SupremeXpansion";
                }
            }

            // HTML em modo correto — evita divs aparecerem
            $mail->msgHTML($html);

            // ANEXAR O PDF
            if (file_exists($pdf_path)) {
                $mail->addAttachment($pdf_path);
            }

            $mail->send();

        } catch (Exception $e) {
            error_log("Erro ao enviar email automático da proposta: " . $mail->ErrorInfo);
        }

    } else {
        // opcional: log para saber que foi bloqueado por opção
        error_log("Email de proposta NÃO enviado (bloqueado pelas preferências). Proposta ID: " . $id_proposta);
    }

    

    header("Location: propostas.php?sucesso=1");
    exit;
}



// =========================================
// CONFIGURAÇÕES INICIAIS
// =========================================

// Ativar erros (para debug — remove em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =========================================
// 1️⃣ RECOLHER DADOS DO FORMULÁRIO
// =========================================

$nome_cliente   = $_POST['nome_cliente'] ?? '';
$email_cliente  = $_POST['email_cliente'] ?? '';
$telefone       = $_POST['telefone_cliente'] ?? '';
$nome_obra      = $_POST['nome_obra'] ?? '';
$localizacao    = $_POST['endereco_obra'] ?? '';
$distancia      = floatval($_POST['distancia'] ?? 0); // só ida
$codigo_pais    = $_POST['codigo_pais'] ?? '';
$preco_topo     = floatval($_POST['preco_topografico'] ?? 0);
$preco_drone    = floatval($_POST['preco_drone'] ?? 0);
$total_render = floatval($_POST['total_render'] ?? 0);
$desconto       = floatval($_POST['desconto_percentagem'] ?? 0);
$observacoes    = $_POST['observacoes'] ?? '';
$custos_extra   = floatval($_POST['custos_extra'] ?? 0);
$areas          = $_POST['areas'] ?? [];
$servicos       = $_POST['servicos'] ?? [];
$opcoes_servico = $_POST['opcao_servico'] ?? [];
$parent_id = $_POST['parent_id'] ?? null;
$opcoes_drone = $_POST['opcoes_drone'] ?? [];
$empresa_nome = trim($_POST['empresa_nome'] ?? '');
$empresa_nif  = trim($_POST['empresa_nif'] ?? '');
$inclui_lod_imgs = !empty($_POST['incluir_lod_imgs']) ? 1 : 0;
$topo_aplicar_mais10 = !empty($_POST['topo_aplicar_mais10']) ? 1 : 0;
$edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;




if ($parent_id) {
  $id_proposta_origem = $parent_id;
} else {
  $id_proposta_origem = null;
}


// =========================================
// 2️⃣ VERIFICAR / INSERIR CLIENTE
// =========================================

// procurar cliente existente
$stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
$stmt->execute([$email_cliente]);
$cliente_existente = $stmt->fetchColumn();

// procurar utilizador existente
$stmt = $pdo->prepare("SELECT id, tem_primeiro_projeto FROM utilizadores WHERE email = ?");
$stmt->execute([$email_cliente]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// se existir utilizador
if ($usuario) {
    $cliente_novo = ($usuario['tem_primeiro_projeto'] == 0);
} else {
    $cliente_novo = true;
}

// se existir cliente na tabela clientes → usa esse
if ($cliente_existente) {
    $id_cliente = $cliente_existente;
} else {
    // senão cria cliente novo
    $ins = $pdo->prepare("INSERT INTO clientes (nome, email, telefone) VALUES (?, ?, ?)");
    $ins->execute([$nome_cliente, $email_cliente, $telefone]);
    $id_cliente = $pdo->lastInsertId();
}

// =========================================
// 3️⃣ CALCULAR TOTAIS DAS ÁREAS
// =========================================

$total_areas = 0;
$total_m2 = 0;

foreach ($areas as $a) {
    $m2 = floatval($a['m2'] ?? 0);
    $preco_m2 = floatval($a['preco_m2'] ?? 0);

    $manualAtivo = !empty($a['subtotal_manual_ativo']);
    if ($manualAtivo && isset($a['subtotal_manual']) && $a['subtotal_manual'] !== '') {
        $subtotal = floatval($a['subtotal_manual']);
    } else {
        $subtotal = $m2 * $preco_m2;
    }

    $total_areas += $subtotal;
    $total_m2 += $m2;
}

$preco_arquitetonico = $total_areas;


// =========================================
// 4️⃣ CALCULAR DESLOCAÇÃO AUTOMÁTICA
// =========================================

// Fórmulas baseadas nas regras que me deste
$dias = $total_m2 > 0 ? min(round(($total_m2 / 4000) + 0.5), 100) : 1;
$deslocacao_dia = $distancia * 0.4 * 2;  // ida e volta
$alimentacao_dia = 30;
$custo_tecnico = 100;

$preco_deslocacao_total = isset($_POST['preco_deslocacao_total'])
  ? (float)$_POST['preco_deslocacao_total']
  : 0.0;


// ===== deslocação vinda do JS (checkboxes + valores) =====
$incluiTec  = !empty($_POST['inclui_tecnico_alimentacao']) ? 1 : 0;
$incluiDist = !empty($_POST['inclui_distancia']) ? 1 : 0;

$precoTecAlim = isset($_POST['preco_tecnico_alimentacao']) ? (float)$_POST['preco_tecnico_alimentacao'] : 130.0;
$precoDist    = isset($_POST['preco_distancia']) ? (float)$_POST['preco_distancia'] : 0.0;


// =========================================
// 5️⃣ CALCULAR AJUSTES SEPARADOS (LOD, 3D, BIM)
// =========================================

// totalBase = soma das áreas (antes de qualquer ajuste)
$totalBase = $preco_arquitetonico;

// criar percentagens SEPARADAS (igual ao JS)
$percent_lod = 0;
$percent_3d  = 0;
$percent_bim = 0;

// tabelas EXATAS como o JS
$ajustesLOD = [
    "1:200" => -10,
    "1:100" => 0,
    "1:50"  => 60,
    "1:20"  => 130,
    "1:1"   => 300
];

$ajustesModelo3D = [
    "1:200" => 0,
    "1:100" => 60,
    "1:50"  => 130,
    "1:20"  => 300
];

$ajustesBIM = [
    "bricscad" => 20,
    "archicad" => 20,
    "revit"    => 20
];

// ids reais
$ID_LOD = 6;
$ID_MODELO3D = 8;
$ID_BIM = 10;

// percorre as opções
foreach ($opcoes_servico as $id_servico => $opcao) {

    // buscar nome real
    $stmt = $pdo->prepare("SELECT nome FROM servicos_produtos WHERE id = ?");
    $stmt->execute([$id_servico]);
    $nome_servico = strtolower($stmt->fetchColumn() ?? '');

    // ---- LOD ----
    if ($id_servico == $ID_LOD) {
        if (isset($ajustesLOD[$opcao])) {
            $percent_lod = $ajustesLOD[$opcao];
        }
    }

    // ---- MODELO 3D ----
    if ($id_servico == $ID_MODELO3D) {
        if (isset($ajustesModelo3D[$opcao])) {
            $percent_3d = $ajustesModelo3D[$opcao];
        }
    }

    // ---- BIM ----
    if ($id_servico == $ID_BIM) {
        $op = strtolower($opcao);
        if (isset($ajustesBIM[$op])) {
            $percent_bim = $ajustesBIM[$op];
        }
    }
}

// ============================
// CALCULAR TOTAL AJUSTADO (igual ao JS)
// ============================

$totalArquitetonicoAjustado = $totalBase;
$totalArquitetonicoAjustado += $totalBase * ($percent_lod / 100);
$totalArquitetonicoAjustado += $totalBase * ($percent_3d  / 100);
$totalArquitetonicoAjustado += $totalBase * ($percent_bim / 100);

// isto substitui o teu $total_servicos antigo
// no JS: Laser = (áreas + ajustes + render) + deslocação
$total_servicos = $totalArquitetonicoAjustado + $total_render + $preco_deslocacao_total;


// =========================================
// 6️⃣ CALCULAR TOTAIS FINAIS
// =========================================
$opcao_nivel_detalhe      = $_POST['opcao_nivel_detalhe']      ?? null;
$opcao_modelo3d_nivel     = $_POST['opcao_modelo3d_nivel']     ?? null;
$opcao_bim                = $_POST['opcao_bim']                ?? null;

// já vem do JS mas vamos garantir
$preco_topo = isset($_POST['preco_topografico']) ? (float)$_POST['preco_topografico'] : 0.0;

// fallback: se estiverem a mandar base (sem +10) por algum motivo
// (não vai acontecer no teu front atual, mas protege)
if (!empty($topo_aplicar_mais10)) {
  // heurística simples: se o valor do POST for exatamente o mesmo que o input manual (sem +10),
// ignora (não temos outro campo), então mantemos como veio.
// Se quiseres 100% certo, cria um campo separado "preco_topografico_base".
}


// drone total enviado no POST
$preco_drone              = $_POST['preco_total_drone']        ?? 0;
$preco_drone              = floatval($preco_drone);

// igual ao front agora: total bruto = laser (já inclui deslocação + render) + topo + drone

// ⚠️ total_bruto em EUR (float), mas vamos fechar em cêntimos
$total_bruto = $total_servicos + $preco_drone + $preco_topo;

$totalBrutoC = toCents($total_bruto);

// desconto global em cêntimos (arredondado 1x)
$valorDescontoC = (int) round($totalBrutoC * ((float)$desconto / 100));

// líquido, iva, final em cêntimos
$totalLiquidoC = $totalBrutoC - $valorDescontoC;
if ($totalLiquidoC < 0) $totalLiquidoC = 0;

$totalIvaC   = ivaCents($totalLiquidoC);
$totalFinalC = $totalLiquidoC + $totalIvaC;

// volta a float só para guardar (mas já “fechado”)
$valor_desconto = fromCents($valorDescontoC);
$total_liquido  = fromCents($totalLiquidoC);
$total_iva      = fromCents($totalIvaC);
$total_final    = fromCents($totalFinalC);

// =========================================
// 6B️⃣ SUB CUSTOS / TEMPO PRODUÇÃO / MARGEM
// =========================================

// total sem IVA = total líquido
$total_sem_iva = $total_liquido;

// base para sub custos (nunca negativa)
$base_subcustos = $total_sem_iva - $custos_extra - $preco_deslocacao_total;
if ($base_subcustos < 0) $base_subcustos = 0;

// sub custos da empresa
$sub_custos_empresa = ($base_subcustos * 0.25) + 80;

// tempo produção em DIAS (inteiro)
// regra de arredondamento: sugiro "round" normal; se quiseres sempre para cima usamos ceil
$tempo_producao_dias = (int) round($sub_custos_empresa / 40);

// margem liberta (como definiste: sem incluir custos viagem extra além do preco_deslocacao)
$margem_liberta = $total_sem_iva - ($sub_custos_empresa + $custos_extra) - $preco_deslocacao_total;

$preco_medio_m2 = $total_m2 > 0 ? $total_final / $total_m2 : 0;

// =========================================
// 7️⃣ GERAR NÚMERO DE PROPOSTA
// =========================================
$ano = date('Y');
$codigo_final = '';
$codigo_base = '';
$vezes = 0;

// ====== Caso seja uma renegociação ======
if (!empty($parent_id)) {

    $stmt = $pdo->prepare("SELECT codigo, vezes_renegociada FROM propostas WHERE id = ?");
    $stmt->execute([$parent_id]);
    $origem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($origem) {

        $codigo_parts = explode('/', $origem['codigo']);

        // base: YYYY/NNN
        $codigo_base = $codigo_parts[0] . '/' . $codigo_parts[1];

        $vezes = intval($origem['vezes_renegociada'] ?? 0) + 1;

        // renegociação: YYYY/NNN/01
        $codigo_final = $codigo_base . '/' . str_pad($vezes, 2, '0', STR_PAD_LEFT);

        $pdo->prepare("UPDATE propostas SET vezes_renegociada = ? WHERE id = ?")
            ->execute([$vezes, $parent_id]);

        $update = $pdo->prepare("
            UPDATE propostas 
            SET estado = 'cancelada'
            WHERE codigo_base = ?
              AND codigo <> ?
        ");
        $update->execute([$codigo_base, $codigo_final]);
    }
}

// ====== Caso seja uma nova proposta ======
else {

    // SE O UTILIZADOR INSERIU UM NÚMERO MANUAL
    $numero_manual = trim($_POST['numero_proposta_manual'] ?? "");

    if (!empty($numero_manual)) {

        $codigo_base  = $numero_manual;
        $codigo_final = $numero_manual;

    } else {

        // 🔥 MAIS SEGURO: buscar o maior NNN do ano atual, a partir do código
        // Só apanha códigos do tipo "YYYY/NNN" (8 chars)
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(codigo, '/', -1) AS UNSIGNED)) AS ultimo_num
            FROM propostas
            WHERE codigo LIKE CONCAT(?, '/%')
              AND CHAR_LENGTH(codigo) = 8
              AND codigo NOT LIKE CONCAT(?, '/999')
        ");
        $stmt->execute([$ano, $ano]);
        $ultimo_num = $stmt->fetchColumn();

        $numero = $ultimo_num ? ((int)$ultimo_num + 1) : 1;

        $numero_formatado = str_pad($numero, 3, '0', STR_PAD_LEFT);
        $codigo_base  = $ano . '/' . $numero_formatado;
        $codigo_final = $codigo_base;
    }
}






// =========================================
// 8️⃣ CONTAR RENEGOCIAÇÕES
// =========================================

$renegociacoes = 0;
if ($parent_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM propostas WHERE id_proposta_origem = ?");
    $stmt->execute([$parent_id]);
    $renegociacoes = $stmt->fetchColumn() + 1;
}

// =========================================
// 9️⃣ INSERIR PROPOSTA COMPLETA
// =========================================
// === Datas (garantir que estão sempre definidas) ===
if (empty($data_emissao)) {
    $data_emissao = date('Y-m-d');
}
if (empty($data_vencimento)) {
    $data_vencimento = date('Y-m-d', strtotime("+60 days"));
}


$imagem_nome = null;

if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = "../uploads/propostas/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
    $tipos_validos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($ext, $tipos_validos)) {
        $imagem_nome = 'proposta_' . uniqid() . '.' . $ext;
        $destino = $uploadDir . $imagem_nome;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)) {
            echo "<script>console.log('✅ Imagem guardada: $destino');</script>";
        } else {
            echo "<script>console.log('❌ Falha ao mover o ficheiro');</script>";
            $imagem_nome = null;
        }
    } else {
        echo "<script>alert('Formato inválido. Apenas JPG, PNG, GIF ou WEBP.');</script>";
    }
} else {
    echo "<script>console.log('⚠️ Nenhuma imagem enviada.');</script>";
}

$stmtU = $pdo->prepare("SELECT id, empresa_nome, empresa_nif FROM utilizadores WHERE LOWER(email)=LOWER(?) LIMIT 1");
$stmtU->execute([$email_cliente]);
$u = $stmtU->fetch(PDO::FETCH_ASSOC);

if ($u) {
  $empresaAtual = trim((string)($u['empresa_nome'] ?? ''));
  $nifAtual     = trim((string)($u['empresa_nif'] ?? ''));

  if ($empresaAtual === '' && $empresa_nome !== '') {
    $pdo->prepare("UPDATE utilizadores SET empresa_nome=? WHERE id=?")->execute([$empresa_nome, $u['id']]);
  }
  if ($nifAtual === '' && $empresa_nif !== '') {
    $pdo->prepare("UPDATE utilizadores SET empresa_nif=? WHERE id=?")->execute([$empresa_nif, $u['id']]);
  }
}


// ========================================================================
// CORRIGIR CAPTURA DAS OPÇÕES (LOD, MODELO3D, BIM) - NOVO MÉTODO 100% CERTO
// ========================================================================

// IDs REAIS das opções na tabela servicos_produtos
$ID_LOD      = 6;
$ID_MODELO3D = 8;
$ID_BIM      = 10;
$ID_DRONE = 13; // <-- AJUSTAR AO ID REAL

$opcao_drone = $opcoes_servico[$ID_DRONE] ?? null;


// Lê diretamente do POST das opções enviadas pelo formulário
$opcao_nivel_detalhe  = $opcoes_servico[$ID_LOD]      ?? null;
$opcao_modelo3d_nivel = $opcoes_servico[$ID_MODELO3D] ?? null;
$opcao_bim            = $opcoes_servico[$ID_BIM]      ?? null;






if ($edit_id) {

    // UPDATE proposta
    $sql = "UPDATE propostas SET
        id_cliente=?,
        nome_cliente=?,
        email_cliente=?,
        telefone_cliente=?,
        empresa_nome=?,
        empresa_nif=?,
        nome_obra=?,
        endereco_obra=?,
        distancia_km=?,
        codigo_pais=?,

        preco_levantamento_arquitetonico=?,
        preco_levantamento_topografico=?,
        preco_drone=?,
        preco_render=?,
        preco_deslocacao_total=?,

        custos_extra=?,
        sub_custos_empresa=?,
        margem_liberta=?,
        tempo_producao_dias=?,

        desconto_percentagem=?,
        valor_desconto=?,
        total_bruto=?,
        total_iva=?,
        total_liquido=?,
        total_final=?,

        opcao_bim=?,
        opcao_nivel_detalhe=?,
        opcao_modelo3d_nivel=?,

        inclui_tecnico_alimentacao=?,
        inclui_distancia=?,
        preco_tecnico_alimentacao=?,
        preco_distancia=?,
        inclui_lod_imgs=?,
        topo_aplicar_mais10=?,

        atualizado_em=NOW()
      WHERE id=?";

    $st = $pdo->prepare($sql);
    $st->execute([
      $id_cliente, $nome_cliente, $email_cliente, $telefone,
      $empresa_nome, $empresa_nif, $nome_obra, $localizacao,
      $distancia * 2, $codigo_pais,

      $preco_arquitetonico, $preco_topo, $preco_drone, $total_render, $preco_deslocacao_total,

      $custos_extra, $sub_custos_empresa, $margem_liberta, $tempo_producao_dias,

      $desconto, $valor_desconto, $total_bruto, $total_iva, $total_liquido, $total_final,

      $opcao_bim, $opcao_nivel_detalhe, $opcao_modelo3d_nivel,

      $incluiTec, $incluiDist, $precoTecAlim, $precoDist,
      $inclui_lod_imgs, $topo_aplicar_mais10,

      $edit_id
    ]);

    $id_proposta = $edit_id;

    // limpar áreas/serviços antigos
    $pdo->prepare("DELETE FROM areas_proposta WHERE id_proposta=?")->execute([$id_proposta]);
    $pdo->prepare("DELETE FROM servicos_proposta WHERE id_proposta=?")->execute([$id_proposta]);

} else{

    $sql = "INSERT INTO propostas (
        id_cliente, nome_cliente, email_cliente, telefone_cliente, nome_obra, endereco_obra, distancia_km, codigo_pais,
        preco_levantamento_arquitetonico, preco_levantamento_topografico, preco_drone, preco_render, preco_deslocacao_total,
        custos_extra, sub_custos_empresa, margem_liberta, tempo_producao_dias,
        desconto_percentagem, valor_desconto, total_bruto, total_iva, total_liquido, total_final,
        opcao_bim, opcao_nivel_detalhe, opcao_modelo3d_nivel,
        data_emissao, data_vencimento, numero_projeto,
        id_proposta_origem, vezes_renegociada, observacoes, ano, codigo, codigo_base, imagem, empresa_nome, empresa_nif, inclui_tecnico_alimentacao, inclui_distancia, preco_tecnico_alimentacao, preco_distancia, inclui_lod_imgs, topo_aplicar_mais10

    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";


    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_cliente, $nome_cliente, $email_cliente, $telefone, $nome_obra, $localizacao, $distancia * 2, $codigo_pais,
        $preco_arquitetonico, $preco_topo, $preco_drone, $total_render, $preco_deslocacao_total, $custos_extra, $sub_custos_empresa, $margem_liberta, $tempo_producao_dias,
        $desconto, $valor_desconto, $total_bruto, $total_iva, $total_liquido, $total_final,
        $opcao_bim, $opcao_nivel_detalhe, $opcao_modelo3d_nivel,
        $data_emissao, $data_vencimento, $codigo_base,
        $parent_id, $vezes ?? 0, $observacoes, $ano, $codigo_final, $codigo_base, $imagem_nome, $empresa_nome, $empresa_nif, $incluiTec, $incluiDist, $precoTecAlim, $precoDist, $inclui_lod_imgs, $topo_aplicar_mais10
    ]);
    $id_proposta = $pdo->lastInsertId();

}




// =========================================
// 🔟 INSERIR ÁREAS INDIVIDUAIS
// =========================================

$sqlArea = $pdo->prepare("
  INSERT INTO areas_proposta
  (id_proposta, nome_area, metros_quadrados, preco_m2, subtotal, valor_deslocacao, exterior)
  VALUES (?,?,?,?,?,?,?)
");


$cont = 1;
$qtdAreas = max(count($areas), 1);

$deslocTotalC = toCents($preco_deslocacao_total);
$deslocPartsC = distribuirEmCents($deslocTotalC, $qtdAreas);

foreach ($areas as $idx => $a) {
    $m2 = floatval($a['m2'] ?? 0);
    $preco_m2 = floatval($a['preco_m2'] ?? 0);

    $manualAtivo = !empty($a['subtotal_manual_ativo']);
    if ($manualAtivo && isset($a['subtotal_manual']) && $a['subtotal_manual'] !== '') {
        $subtotal = floatval($a['subtotal_manual']);
    } else {
        $subtotal = $m2 * $preco_m2;
    }

    $exterior = !empty($a['exterior']) ? 1 : 0;

    $nome_area = trim($a['nome'] ?? '');
    if ($nome_area === '') $nome_area = "Área $cont";

    // ✅ deslocação por área com resto distribuído em cêntimos
    $valor_deslocacao_area = fromCents((int)($deslocPartsC[$idx] ?? 0));

    $sqlArea->execute([
        $id_proposta,
        $nome_area,
        $m2,
        $preco_m2,
        $subtotal,
        $valor_deslocacao_area,
        $exterior
    ]);

    $cont++;
}


// =========================================
// 11️⃣ INSERIR SERVIÇOS SELECIONADOS
// =========================================

$sqlServico = $pdo->prepare("
INSERT INTO servicos_proposta (id_proposta, id_servico, nome_servico, opcao_escolhida, preco_servico, ajuste_percentual)
VALUES (?,?,?,?,?,?)
");

// 🔥 RENDERIZAÇÕES (SERVIÇO ID 16)
$ID_RENDER = 16;

if ($total_render > 0) {
    $stmtR = $pdo->prepare("
        INSERT INTO servicos_proposta 
        (id_proposta, id_servico, nome_servico, opcao_escolhida, preco_servico, ajuste_percentual)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmtR->execute([
        $id_proposta,
        $ID_RENDER,
        "Renderizações",
        null,
        $total_render,
        0
    ]);
}


foreach ($servicos as $index => $s) {

    if (empty($s['id'])) continue;
    $id_servico = (int)$s['id'];

    // ⚠️ EVITAR DUPLICAR O SERVIÇO DE RENDERIZAÇÕES (ID 16)
    if ($id_servico == 16) continue;

    // nome oficial do BD
    $stmtNome = $pdo->prepare("SELECT nome FROM servicos_produtos WHERE id = ?");
    $stmtNome->execute([$id_servico]);
    $nome_servico = $stmtNome->fetchColumn() ?: '';

    // opção escolhida
    if ($id_servico == $ID_DRONE) {
        // drone → array vem do POST "opcoes_drone[]"
        $opcao_escolhida = !empty($opcoes_drone)
            ? json_encode($opcoes_drone, JSON_UNESCAPED_UNICODE)
            : null;
    } else {
        // serviços normais (LOD / 3D / BIM / etc)
        $opcao_escolhida = $opcoes_servico[$id_servico] ?? null;
    }



    // preço padrão
    $preco_servico = 0;

    // 🔥 SE FOR O DRONE → SALVAR PREÇO CORRETO + OPÇÃO
    if ($id_servico == $ID_DRONE) {
        $preco_servico = floatval($_POST['preco_total_drone'] ?? 0);
    }

    // ajuste (mantém como zero se não usado)
    $ajuste_percentual = 0;

    $sqlServico->execute([
        $id_proposta,
        $id_servico,
        $nome_servico,
        $opcao_escolhida,
        $preco_servico,
        $ajuste_percentual
    ]);
}


// ==============================
// GERAR PDF PREVIEW (sem email)
// ==============================

// buscar dados completos da BD (para o teste_pdf usar)
$stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
$stmt->execute([$id_proposta]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
$stmt2->execute([$id_proposta]);
$areas_db = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$stmt3 = $pdo->prepare("
  SELECT sp.*, COALESCE(s.nome, sp.nome_servico) AS nome_base
  FROM servicos_proposta sp
  LEFT JOIN servicos_produtos s ON s.id = sp.id_servico
  WHERE sp.id_proposta = ?
  ORDER BY sp.id_servico ASC
");
$stmt3->execute([$id_proposta]);
$servicosSelecionados = $stmt3->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['proposta'] = $proposta;
$GLOBALS['areas'] = $areas_db;
$GLOBALS['servicosSelecionados'] = $servicosSelecionados;
$GLOBALS['servicos'] = $servicosSelecionados;

// modo PREVIEW (cria preview_ID.pdf)
$previewDir = __DIR__ . '/../uploads/previews/';
if (!is_dir($previewDir)) mkdir($previewDir, 0777, true);

$previewPdfPath = $previewDir . "preview_{$id_proposta}.pdf";
$GLOBALS['PDF_PREVIEW_MODE'] = true;
$GLOBALS['PDF_PREVIEW_PATH'] = $previewPdfPath;

// gera o preview
ob_start();
include './teste_pdf.php';
ob_end_clean();

// abre o preview
$parent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

header("Location: preview_proposta.php?id=".(int)$id_proposta.($parent>0 ? "&parent_id=".$parent : ""));
exit;




?>
