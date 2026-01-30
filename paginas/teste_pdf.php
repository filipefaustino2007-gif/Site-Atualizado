<?php

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

require __DIR__ . '/../conexao/conexao.php';

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require __DIR__ . '/../conexao/conexao.php';
// ================================
// Helpers (precisam existir ANTES de serem usadas)
// ================================
if (!function_exists('normTxt')) {
  function normTxt(string $s): string {
    $s = strtolower(trim($s));
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) $s = $t;
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s ?: '';
  }
}

if (!function_exists('temServicoPorNome')) {
  function temServicoPorNome(array $servicos, string $nomeAlvo): bool {
    $alvo = normTxt($nomeAlvo);
    foreach ($servicos as $s) {
      if (!is_array($s)) continue;
      $nome = (string)($s['nome_base'] ?? $s['nome_servico'] ?? $s['nome'] ?? '');
      if (normTxt($nome) === $alvo) return true;
    }
    return false;
  }
}

// ================================
//  MODO INJETADO (PREVIEW / SALVAR)
//  Se já existirem globals, NÃO voltar a ir à BD.
//  (TEM DE SER A PRIMEIRA COISA ANTES DE USAR $proposta)
// ================================
$proposta = null;
$areas    = [];
$servicos = [];
if (!is_array($servicos)) $servicos = [];
foreach ($servicos as $k => $s) {
  if (!is_array($s)) { unset($servicos[$k]); continue; }
  if (!isset($s['id_servico']) && isset($s['id'])) $s['id_servico'] = (int)$s['id'];
  if (!isset($s['nome_base'])) {
    if (isset($s['nome_servico'])) $s['nome_base'] = $s['nome_servico'];
    elseif (isset($s['nome'])) $s['nome_base'] = $s['nome'];
    else $s['nome_base'] = '';
  }
  $servicos[$k] = $s;
}


if (!empty($GLOBALS['proposta']) && is_array($GLOBALS['proposta'])) {
    $proposta = $GLOBALS['proposta'];
    $areas    = $GLOBALS['areas'] ?? [];
    $servicos = $GLOBALS['servicos'] ?? ($GLOBALS['servicosSelecionados'] ?? []);
    // Logo depois de obter $proposta (via globals ou BD)
$idProposta = (int)($proposta['id'] ?? 0);

} else {
    // Logo depois de obter $proposta (via globals ou BD)
    $idProposta = (int)($proposta['id'] ?? 0);

    if (isset($id_proposta) && $id_proposta) $id = (int)$id_proposta;
    elseif (!empty($_GET['id'])) $id = (int)$_GET['id'];
    if ($id <= 0) die('ID da proposta não fornecido.');

    $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposta) die('Proposta não encontrada.');

    $stmt = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
    $stmt->execute([$id]);
    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
      SELECT sp.*, COALESCE(s.nome, sp.nome_servico) AS nome_base
      FROM servicos_proposta sp
      LEFT JOIN servicos_produtos s ON s.id = sp.id_servico
      WHERE sp.id_proposta = ?
      ORDER BY sp.id_servico ASC
    ");
    $stmt->execute([$id]);
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// ---------- moeda + idioma ----------
$moeda = strtoupper(trim((string)($proposta['codigo_pais'] ?? 'EUR')));
if (!in_array($moeda, ['EUR','USD','GBP','JPY'], true)) $moeda = 'EUR';
$isEUR = ($moeda === 'EUR');
$lang = $isEUR ? 'pt' : 'en';

// 1 [moeda] = X EUR (igual ao front)
$FX = [
  'EUR' => 1,
  'USD' => 0.85,
  'GBP' => 1.15,
  'JPY' => 0.0055,
];

if (!function_exists('eurToCurrency')) {
  function eurToCurrency($eur, $code, $FX = null) {
    $code = strtoupper(trim($code ?: 'EUR'));
    $eur  = (float)$eur;

    // Se o 3º argumento vier como "en"/"pt" (string), ignora como FX
    if (!is_array($FX)) {
      $FX = $GLOBALS['FX'] ?? [
        'EUR' => 1.0,
        'USD' => 0.85,
        'GBP' => 1.15,
        'JPY' => 0.0055,
      ];
    }

    if ($code === 'EUR') return $eur;

    $rate = isset($FX[$code]) ? (float)$FX[$code] : 0.0;
    if ($rate <= 0) return 0.0;

    // 1 [code] = X EUR  => EUR -> code = EUR / X
    return $eur / $rate;
  }
}



if (!function_exists('moneyPdf')) {
  // recebe SEMPRE o valor em EUR (como está na BD) e devolve string pronta para PDF
  function moneyPdf($valorEur, $code, $FX, $lang){
    $code = strtoupper(trim((string)$code));
    $v = eurToCurrency((float)$valorEur, $code, $FX);

    $decimals = 2;
    if ($code === 'JPY') $decimals = 0;

    if ($lang === 'pt' && $code === 'EUR') {
      // mantém exatamente o teu estilo atual PT + €
      $txt = number_format($v, 2, ',', '.') . " €";
      return iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $txt);
    }

    // inglês: 1,234.56 USD / 1,234 GBP / 1,235 JPY
    $txt = number_format($v, $decimals, '.', ',') . " " . $code;
    return iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $txt);
  }
}

// ---------- helpers ----------
if (!function_exists('cp1252')) {
    function cp1252($s) {
        if ($s === null) return '';
        return @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
    }
}


if (!function_exists('eur')) {
  function eur($valorEur) {
    global $moeda, $FX, $lang;
    return moneyPdf($valorEur, $moeda, $FX, $lang);
  }
}

if (!function_exists('dmy')) {
    function dmy($dateStr) {
        if (empty($dateStr)) return '';
        $ts = strtotime($dateStr);
        return $ts ? date('d/m/Y', $ts) : '';
    }
}

// ---------- moeda + idioma ----------
$currency = strtoupper(trim((string)($proposta['codigo_pais'] ?? 'EUR'))); // EUR|USD|GBP|JPY
if (!in_array($currency, ['EUR','USD','GBP','JPY'], true)) $currency = 'EUR';

$lang = ($currency === 'EUR') ? 'pt' : 'en';

// 1 [moeda] = X EUR
$FX = [
  'EUR' => 1.0,
  'USD' => 0.85,
  'GBP' => 1.15,
  'JPY' => 0.0055,
];

// ================================
//  MODO INJETADO (PREVIEW / SALVAR)
//  Se já existirem globals, NÃO voltar a ir à BD.
// ================================
if (!empty($GLOBALS['proposta']) && is_array($GLOBALS['proposta'])) {
    $proposta = $GLOBALS['proposta'];
    $areas    = $GLOBALS['areas'] ?? [];
    $servicos = $GLOBALS['servicos'] ?? ($GLOBALS['servicosSelecionados'] ?? []);

    // ================================
    // Normalizar $servicos (garante id_servico e nome_base)
    // ================================
    if (!is_array($servicos)) $servicos = [];

    foreach ($servicos as $k => $s) {
        if (!is_array($s)) { unset($servicos[$k]); continue; }

        // id_servico fallback
        if (!isset($s['id_servico'])) {
            if (isset($s['id'])) $s['id_servico'] = (int)$s['id'];            // fallback fraco
            elseif (isset($s['idServico'])) $s['id_servico'] = (int)$s['idServico'];
            else $s['id_servico'] = 0;
        }

        // nome_base fallback
        if (!isset($s['nome_base'])) {
            if (isset($s['nome_servico'])) $s['nome_base'] = $s['nome_servico'];
            elseif (isset($s['nome'])) $s['nome_base'] = $s['nome'];
            else $s['nome_base'] = '';
        }

        $servicos[$k] = $s;
    }


    // garante nomes base (se vierem sem JOIN)
    if (empty($servicos) && !empty($GLOBALS['servicosSelecionados'])) {
        $servicos = $GLOBALS['servicosSelecionados'];
    }

} else {

  // ---------- obter id da proposta ----------
  $id = null;
  if (isset($id_proposta) && $id_proposta) {
      $id = (int)$id_proposta;
  } elseif (!empty($_GET['id'])) {
      $id = (int)$_GET['id'];
  }
  if (!$id) { die('ID da proposta não fornecido.'); }

  // ---------- fetch proposta ----------
  $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
  $stmt->execute([$id]);
  $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$proposta) { die('Proposta não encontrada.'); }

  // ---------- fetch áreas ----------
  $stmt = $pdo->prepare("SELECT * FROM areas_proposta WHERE id_proposta = ? ORDER BY id ASC");
  $stmt->execute([$id]);
  $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // ---------- fetch serviços ----------
  $stmt = $pdo->prepare("
      SELECT sp.*, COALESCE(s.nome, sp.nome_servico) AS nome_base
      FROM servicos_proposta sp
      LEFT JOIN servicos_produtos s ON s.id = sp.id_servico
      WHERE sp.id_proposta = ?
      ORDER BY sp.id_servico ASC
  ");
  $stmt->execute([$id]);
  $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

}



if (!function_exists('toIso')) {
    function toIso($str) {
        if ($str === null) return '';
        $out = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', (string)$str);
        if ($out === false) $out = (string)$str; // fallback
        return $out;
    }

}

// ===== Currency/Lang helpers (PDF) =====
$currency = strtoupper(trim((string)($proposta['codigo_pais'] ?? 'EUR')));
$lang = ($currency === 'EUR') ? 'pt' : 'en';

// 1 [moeda] = X EUR (igual ao que usas nas páginas)
$FX = [
  'EUR' => 1.0,
  'USD' => 0.85,
  'GBP' => 1.15,
  'JPY' => 0.0055,
];

$CURRENCY_DECIMALS = [
  'EUR' => 2,
  'USD' => 2,
  'GBP' => 2,
  'JPY' => 0,
];



if (!function_exists('formatNumberLang')) {
  function formatNumberLang(float $n, int $decimals, string $lang): string {
    if ($lang === 'en') {
      return number_format($n, $decimals, '.', ',');
    }
    return number_format($n, $decimals, ',', '.');
  }
}

if (!function_exists('moneyPdf')) {
  function moneyPdf(float $eur, string $code, string $lang, array $FX, array $decMap): string {
    $code = strtoupper(trim($code ?: 'EUR'));
    $dec  = $decMap[$code] ?? 2;
    $val  = eurToCurrency($eur, $code, $FX);
    $num  = formatNumberLang($val, $dec, $lang);

    // EUR mantém "€" como tinhas. Outras ficam com o código no fim (em inglês, como pediste).
    if ($code === 'EUR') return $num . " €";
    return $num . " " . $code;
  }
}

// ======== Dados da proposta ========
$cliente = $proposta['nome_cliente'] ?? '';
$obra = $proposta['nome_obra']    ?? '';
$cidade = $proposta['endereco_obra']?? '';
$data = dmy($proposta['data_emissao']); // Data de hoje
$codigo = $proposta['codigo']?? '';
$lev_arquit = $proposta['preco_levantamento_arquitetonico'] ?? '';
$lev_topo = $proposta['preco_levantamento_topografico'] ?? '';
$lev_drone = $proposta['preco_drone'] ?? '';
$dataEmissaoBR = dmy($proposta['data_emissao'] ?? '');
$dataVencimentoBR = dmy($proposta['data_vencimento'] ?? '');
$valorDeslocacaoTotal = (float)($proposta['preco_deslocacao_total'] ?? 0);



// ======== Caminhos para imagens ========
$imginicial     = __DIR__ . '/../img/img1_pdf.png';  // linha vermelha topo
$logoSupreme    = __DIR__ . '/../img/img2_pdf.jpg';  // logo Supreme
$imagemObra     = __DIR__ . '/../img/img3_pdf.png';  // imagem principal
$imagemrodape1  = __DIR__ . '/../img/icon_loc.jpeg';  // ícone + morada
$imagemrodape2  = __DIR__ . '/../img/img5_pdf.png';  // faixa vermelha site
$imagempagina3  = __DIR__ . '/../img/img6_pdf.png';  // faixa vermelha site
$imagempagina4  = __DIR__ . '/../img/img7_pdf.png';  // faixa vermelha site
$imagempagina5_1  = __DIR__ . '/../img/img8_pdf.png';  // faixa vermelha site
$imagempagina5_2  = __DIR__ . '/../img/img9_pdf.png';  // faixa vermelha site
$imagempagina5_3  = __DIR__ . '/../img/img10_pdf.png';  // faixa vermelha site
$imagempagina5_4  = __DIR__ . '/../img/img11_pdf.png';  // faixa vermelha site
$imagempagina5_5  = __DIR__ . '/../img/img12_pdf.png';  // faixa vermelha site
$imagempagina5_6  = __DIR__ . '/../img/img13_pdf.png';  // faixa vermelha site

if (!function_exists('dataPorExtenso')) {
  function dataPorExtenso($dataSql, $lang = 'pt') {
    if (empty($dataSql)) return '';

    $mesesPT = [
      1 => "Janeiro", "Fevereiro", "Março", "Abril",
      "Maio", "Junho", "Julho", "Agosto",
      "Setembro", "Outubro", "Novembro", "Dezembro"
    ];
    $mesesEN = [
      1 => "January", "February", "March", "April",
      "May", "June", "July", "August",
      "September", "October", "November", "December"
    ];

    $ts = strtotime($dataSql);
    if (!$ts) return '';

    $dia = (int)date("d", $ts);
    $mes = (int)date("m", $ts);
    $ano = date("Y", $ts);

    if ($lang === 'en') {
      // exemplo: 8 January 2026 (inglês correto e simples)
      return $dia . " " . ($mesesEN[$mes] ?? '') . " " . $ano;
    }

    // PT: 08 de Janeiro de 2026 (como tinhas)
    $dia2 = str_pad((string)$dia, 2, '0', STR_PAD_LEFT);
    return "$dia2 de " . ($mesesPT[$mes] ?? '') . " de $ano";
  }
}

// =====================================================
//   CALCULAR EXTRAS DOS SERVIÇOS COM OPÇÕES (LOD/3D/BIM)
// =====================================================

$valorExtraOpcoes = 0;     // soma final dos incrementos
$baseAreas = (float)($proposta['preco_levantamento_arquitetonico'] ?? 0);

// percentagens iguais às usadas na criação/salvamento
$ajustesLOD = [
    "1:200" => -10,
    "1:100" => 0,
    "1:50"  => 60,
    "1:20"  => 130,
    "1:1"   => 300
];

$ajustes3D = [
    "1:200" => 0,
    "1:100" => 60,
    "1:50"  => 130,
    "1:20"  => 300
];

$ajustesBIM = [
    "Bricscad" => 20,
    "Archicad" => 20,
    "Revit"    => 20
];

foreach ($servicos as $s) {

    $idServico = (int)($s['id_servico'] ?? 0);
    $opc  = $s['opcao_escolhida'] ?? null;

    // LOD
    if ($idServico === 6 && $opc && isset($ajustesLOD[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustesLOD[$opc] / 100);
    }

    // Modelo 3D
    if ($idServico === 8 && $opc && isset($ajustes3D[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustes3D[$opc] / 100);
    }

    // BIM
    if ($idServico === 10 && $opc && isset($ajustesBIM[$opc])) {
        $valorExtraOpcoes += $baseAreas * ($ajustesBIM[$opc] / 100);
    }
}



// ======== Criar PDF ========
$pdf = new FPDF();
$pdf->AddPage();


// ---------- CABEÇALHO (apenas img1) ----------
if (file_exists($imginicial)) {
    // cobre toda a largura
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- LOGO E INFO ----------
$pdf->Image($logoSupreme, 10, 16, 100);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 40);
$txtHeader = ($lang === 'en')
  ? "Specialists in Architectural Surveys\n3D Laser Scanning Technology – Aerial and Terrestrial\nwww.supremexpansion.com"
  : "Especialistas em levantamentos Arquitetónicos\nTecnologia Laser Scan 3D Aéreos e Terrestres\nwww.supremexpansion.com";

$pdf->MultiCell(0, 4, toIso($txtHeader));


// ---------- TÍTULOS ----------
$pdf->SetFont('Arial', 'B', 22);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 65);
$pdf->Cell(20, 12, toIso($lang === 'en' ? "Quotation" : "PROPOSTA DE HONORÁRIOS"), 0, 1, 'L');


$pdf->SetFont('Arial', 'B', 14);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "3D LASER SCANNING SURVEY" : "LEVANTAMENTO 3D POR LASER"), 0, 1, 'L');


// ---------- CLIENTE E OBRA ----------
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetX(20);
$pdf->Cell(0, 8, toIso($cliente), 0, 1, 'L');

$pdf->SetFont('Arial', '', 12);
$pdf->SetX(20);
$pdf->Cell(0, 7, toIso($obra), 0, 1, 'L');
$pdf->SetX(20);
$pdf->Cell(0, 7, toIso($cidade), 0, 1, 'L');

// ---------- LINHA VERMELHA + DATA ----------
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(1);
$pdf->Line(20, 110, 60, 110);

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 114);
$dataExtenso = dataPorExtenso($proposta['data_emissao'], $lang);

$pdf->Cell(0, 8, toIso($dataExtenso), 0, 1, 'L');


$pdf->SetXY(20, 122);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, toIso($codigo), 0, 1, 'L');

// ---------- IMAGEM PRINCIPAL ----------
if (file_exists($imagemObra)) {
    $pdf->Image($imagemObra, 20, 140, 170);
}
// ---------- RODAPÉ (imagens + morada) ----------

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 2 – ÍNDICE =======
$pdf->AddPage();

// Cabeçalho (imagem de topo)
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título “ÍNDICE” ----------
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(25, 35);
$pdf->Cell(0, 14, toIso($lang === 'en' ? "TABLE OF CONTENTS" : "ÍNDICE"), 0, 1, 'L');


// Linha abaixo do título
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.8);
$pdf->Line(20, 45, 52, 45);




// ---------- Conteúdo do índice ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Ln(8);

// Lista principal
$itens = ($lang === 'en') ? [
    ["I.",  "SUMMARY", 3],
    [" ",  " ", " "],
    ["II.", "INTRODUCTION", 3],
    [" ",  " ", " "],

    ["III.", "GENERAL SCOPE OF WORK", 4],
    [" ",  " ", " "],

    ["IV.", "TECHNICAL TEAM", 4],
    [" ",  " ", " "],

    ["V.", "WORK METHODOLOGY", 6],
] : [
    ["I.",  "RESUMO", 3],
    [" ",  " ", " "],
    ["II.", "INTRODUÇÃO", 3],
    [" ",  " ", " "],

    ["III.", "DEFINIÇÃO GERAL DOS TRABALHOS", 4],
    [" ",  " ", " "],

    ["IV.", "EQUIPA TÉCNICA", 4],
    [" ",  " ", " "],

    ["V.", "METODOLOGIA DE TRABALHO", 6],
];

$subitens = ($lang === 'en') ? [
    ["1.", "PREPARATION AND PLANNING OF THE WORK", 6],
    [" ",  " ", " "],

    ["2.", "TOPOGRAPHIC SUPPORT", 6],
    [" ",  " ", " "],

    ["3.", "ARCHITECTURAL SURVEY", 7],
    [" ",  " ", " "],

    ["4.", "DRAWING OF PLANS, SECTIONS AND ELEVATIONS", 8],
] : [
    ["1.", "PREPARAÇÃO E PLANEAMENTO DOS TRABALHOS", 6],
    [" ",  " ", " "],

    ["2.", "APOIO TOPOGRÁFICO", 6],
    [" ",  " ", " "],

    ["3.", "LEVANTAMENTO ARQUITETÓNICO", 7],
    [" ",  " ", " "],

    ["4.", "DESENHO DAS PLANTAS, CORTES E ALÇADOS", 8],
];

$outros = ($lang === 'en') ? [
    ["VI.", "DELIVERABLES", 9],
    [" ",  " ", " "],
    ["VII.", "QUOTATIONS", 10],
    [" ",  " ", " "],
    ["VIII.", "PAYMENT TERMS", 10],
    [" ",  " ", " "],
    ["IX.", "EXECUTION TIMEFRAME", 10],
    [" ",  " ", " "],
    ["X.", "VALIDITY OF THIS QUOTATION", 10],
    [" ",  " ", " "],
    ["XI.", "AVAILABILITY", 10],
] : [
    ["VI.", "ENTREGAS", 9],
    [" ",  " ", " "],
    ["VII.", "HONORÁRIOS", 10],
    [" ",  " ", " "],
    ["VIII.", "CONDIÇÕES DE PAGAMENTO", 10],
    [" ",  " ", " "],
    ["IX.", "PRAZO DE EXECUÇÃO", 10],
    [" ",  " ", " "],
    ["X.", "VALIDADE DA PROPOSTA", 10],
    [" ",  " ", " "],
    ["XI.", "DISPONIBILIDADE", 10],
];


if (!function_exists('linhaIndice')) {
    // Função para alinhar páginas à direita
    function linhaIndice($pdf, $num, $texto, $pagina, $xInicio, $xPag, $bold = true) {
        $pdf->SetX($xInicio);
        $pdf->SetFont('Arial', $bold ? 'B' : '', 12);
        $pdf->Cell(0, 7, toIso("$num $texto"), 0, 0, 'L');
        $pdf->SetX($xPag);
        $pdf->Cell(0, 7, $pagina, 0, 1, 'R');
    }
}


// Primeira parte
foreach ($itens as $item) {
    linhaIndice($pdf, $item[0], $item[1], $item[2], 25, 185, true);
}

// Subitens dentro da Metodologia
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 11);
foreach ($subitens as $s) {
    linhaIndice($pdf, $s[0], $s[1], $s[2], 35, 185, false);
}

// Restantes
$pdf->Ln(2);
foreach ($outros as $item) {
    linhaIndice($pdf, $item[0], $item[1], $item[2], 25, 185, true);
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);
// ======= PÁGINA 3 – RESUMO + INTRODUÇÃO =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título I. RESUMO ----------
$pdf->SetXY(20, 15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "I.  Summary" : "I.  Resumo"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());

// ---------- Texto Resumo ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "Considering the specific needs of each client and the extensive experience of the SUPREMEXPANSION team in delivering this type of project, we present this Quotation, carefully prepared to efficiently support the intended works or services."
      : "Tendo em consideração as necessidades específicas de cada cliente e a vasta experiência da equipa da SUPREMEXPANSION na execução deste tipo de projetos, apresentamos a presente proposta, cuidadosamente elaborada para apoiar com eficiência as obras ou serviços pretendidos."
));
$pdf->Ln(4);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "Based on the detailed content of this document, SUPREMEXPANSION is fully confident that, once awarded, the project will be executed with maximum accuracy and quality, ensuring full compliance with all established specifications and requirements."
      : "Com base no conteúdo detalhado deste documento, a SUPREMEXPANSION está plenamente convicta de que, uma vez adjudicado, o projeto será executado com máxima precisão e qualidade, assegurando o cumprimento integral de todas as especificações e requisitos estabelecidos."
));

// ---------- Título II. INTRODUÇÃO ----------
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "II.  Introduction" : "II.  Introdução"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());

// ---------- Texto Introdução ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "SUPREMEXPANSION, founded in January 2023, specialises in architectural surveying and operates across several areas of the construction sector, covering public and private projects at all stages."
      : "A SUPREMEXPANSION, fundada em janeiro de 2023, é especializada em levantamentos arquitetónicos e atua em diversas áreas do setor da construção civil, abrangendo obras públicas e privadas em todas as fases do projeto."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "We offer a wide range of high-quality services, combining technical knowledge and experience with state-of-the-art technology, with a constant focus on customer satisfaction."
      : "Oferecemos uma ampla gama de serviços de elevada qualidade, aliando conhecimento técnico e experiência à mais avançada tecnologia, com foco permanente na satisfação dos nossos clientes."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "With more than a decade of recognised presence in the United Kingdom under the 3DSCAN2CAD brand, we stand out for the excellence of our services and the capability of our multidisciplinary team of architects, draughtsmen and specialised technicians, delivering innovative, cutting-edge solutions."
      : "Com mais de uma década de referência no Reino Unido sob a marca 3DSCAN2CAD, destacamo-nos pela excelência dos nossos serviços e pela competência da nossa equipa multidisciplinar de arquitetos, desenhadores e técnicos especializados, que garantem soluções inovadoras e de última geração."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "We provide 3D Laser Scanner technology services for architectural and topographic surveys and 3D building modelling, as well as LIDAR (Light Detection and Ranging) solutions using static laser scanning, applied to industrial sites, mines, heritage, infrastructure and other high-demand contexts."
      : "Disponibilizamos tecnologia Laser Scanner 3D para levantamentos arquitetónicos, topográficos e modelação 3D de edifícios, assim como soluções LIDAR (Light Detection and Ranging) com Laser Scanner estático, aplicadas em áreas industriais, minas, património, infraestruturas e outros contextos de elevada exigência."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
      ? "Our mission is to deliver accurate, efficient results tailored to each project’s needs, supporting safer decisions and better-planned works."
      : "A nossa missão é fornecer resultados precisos, eficientes e adaptados às necessidades de cada projeto, contribuindo para decisões mais seguras e obras mais bem planeadas."
));

// ---------- Imagem inferior ----------
if (file_exists($imagempagina3)) {
    $pdf->Image($imagempagina3, 43, 210, 120);
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 4 – DEFINIÇÃO GERAL DOS TRABALHOS + EQUIPA TÉCNICA =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título III. Definição Geral dos Trabalhos ----------
$pdf->SetXY(20, 15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en'
        ? "III.  General Definition of the Works"
        : "III.  Definição Geral dos Trabalhos"
    ),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());

// ---------- Texto da Definição ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "This Quotation is intended to define and quote the technical services related to Architectural Surveying using 3D Laser Scanning, including the subsequent production of technical drawings."
        : "A presente proposta destina-se à definição técnica e à cotação dos serviços de Levantamento Arquitetónico por varrimento Laser Scan 3D, com consequente criação de peças desenhadas."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The service includes:\n
· Execution of a georeferenced (or non-georeferenced) point cloud, properly consolidated into a single file in *.E57 format (or another format to be defined), covering both the interior and exterior of the building."
        : "O serviço compreende:\n
· Execução de nuvem de pontos georreferenciada (ou não), devidamente consolidada num único ficheiro no formato *.E57 (ou outro a definir), abrangendo o interior e o exterior do edifício."
));

// ---------- Título IV. Equipa Técnica ----------
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en'
        ? "IV.  Technical Team"
        : "IV.  Equipa Técnica"
    ),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());

// ---------- Texto da Equipa Técnica ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The professionals working at SUPREMEXPANSION have extensive experience in Architecture, Topography, Drafting and 3D Modelling, demonstrating a high level of competence with all sort of projects, as well as full knowledge of the equipment used for data acquisition, production and processing."
        : "Os profissionais que colaboram com a SUPREMEXPANSION possuem vasta experiência nas áreas de Arquitetura, Topografia, Desenho e Modelação 3D, demonstrando elevada capacidade de leitura e análise de projetos, bem como domínio dos equipamentos utilizados na aquisição, produção e tratamento de dados."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "For this project, the following human resources will be allocated:\n· 1 Field Technician\n· 1 3D Modelling Technician\n· 1 Drafting Technician"
        : "Neste projeto iremos utilizar os seguintes meios humanos:\n· 1 Técnico de Campo\n· 1 Técnico de Modelação 3D\n· 1 Técnico de Desenho"
));

// ---------- Imagem inferior ----------
if (file_exists($imagempagina4)) {
    $pdf->Image($imagempagina4, 25, 155, 160);
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 5 – MEIOS TÉCNICOS + MEIOS INFORMÁTICOS =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// Imagem 1 (img)_pdf
$pdf->Image(__DIR__ . '/../img/img8_pdf.png', 15, 28, 20);  // esquerda

// ---------- Título “Meios Técnicos” ----------
$pdf->SetXY(35, 30);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, toIso($lang === 'en' ? "Technical Resources" : "Meios Técnicos"), 0, 1, 'L');
$pdf->Ln(3);

// ---------- Bloco 1 ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(45);
$pdf->Cell(0, 6, toIso($lang === 'en' ? "·  1 Static Laser Scanner - RTC 360" : "·  1 Laser Estático - RTC 360"), 0, 1, 'L');

// Imagem 1 (img)_pdf
$pdf->Image(__DIR__ . '/../img/img9_pdf.png', 58, 48, 20);  // esquerda
$pdf->Image(__DIR__ . '/../img/img10_pdf.png', 110, 35, 98); // direita

$pdf->Ln(27);

// ---------- Bloco 2 ----------
$pdf->SetX(45);
$pdf->Cell(0, 6, toIso($lang === 'en' ? "·  1 Static Laser Scanner – BLK 360" : "·  1 Laser Estático – BLK 360"), 0, 1, 'L');

// Imagens 3 e 4
$pdf->Image(__DIR__ . '/../img/img11_pdf.png', 60, 82, 15);
$pdf->Image(__DIR__ . '/../img/img12_pdf.png', 110, 95, 98);

$pdf->Ln(27);

// ---------- Bloco 3 ----------
$pdf->SetX(45);
$pdf->Cell(0, 6, toIso("·  1 DJI Phantom 4 Pro"), 0, 1, 'L'); // (igual em PT/EN)

// Imagens 5 e 6
$pdf->Image(__DIR__ . '/../img/img14_pdf.png', 60, 115, 20);

$pdf->Image(__DIR__ . '/../img/img13_pdf.png', 15, 182, 20);

// ---------- Secção “Meios Informáticos” ----------
$pdf->SetXY(40, 185);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, toIso($lang === 'en' ? "IT Resources" : "Meios Informáticos"), 0, 1, 'L');

$pdf->Ln(7);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "Workstations with all the software required for data processing and handling:"
        : "Computadores fixos com todo o software necessário ao processamento e tratamento de dados:"
));

// Lista de softwares
$softwares = ($lang === 'en')
    ? [
        "Autodesk AutoCAD and BricsCAD",
        "Graphisoft ArchiCAD",
        "Autodesk Recap",
        "Cyclone Register 360 and Cyclone Field",
        "Agisoft Metashape",
        "Cloud Compare",
        "Nubigon"
      ]
    : [
        "Autodesk AutoCAD e BricsCAD",
        "Graphisoft ArchiCAD",
        "Autodesk Recap",
        "Cyclone Register 360 e Cyclone Field",
        "Agisoft Metashape",
        "Cloud Compare",
        "Nubigon"
      ];

$pdf->SetFont('Arial', '', 11);
foreach ($softwares as $s) {
    $pdf->SetX(25);
    $pdf->Cell(0, 6, toIso("·  $s"), 0, 1, 'L');
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 6 – METODOLOGIA DE TRABALHO =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título V. Metodologia de Trabalho ----------
$pdf->SetXY(20, 15);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en' ? "V.  Work Methodology" : "V.  Metodologia de Trabalho"),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(2);

// ---------- Texto introdutório ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "Before initiating any project, SUPREMEXPANSION carries out detailed planning, defining the required human resources and selecting the most appropriate methodologies according to the specific nature of the tasks to be performed."
        : "Antes de iniciar qualquer projeto, a SUPREMEXPANSION realiza um planeamento detalhado, definindo a quantidade de recursos humanos e as metodologias mais adequadas de acordo com a especificidade das tarefas a executar."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "Below, we present a detailed description of the services to be carried out."
        : "De seguida, apresentamos a descrição detalhada dos serviços a serem realizados."
));
$pdf->Ln(20);

// ---------- Subtítulo 1 ----------
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->Cell(
    0,
    8,
    toIso($lang === 'en'
        ? "1.  Preparation and Work Planning"
        : "1.  Preparação e Planeamento dos Trabalhos"),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(5);

// ---------- Texto 1 ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "During the initial phase, following the kick-off meeting, the project execution plan is developed and integrated with any existing interventions. At this stage, all necessary authorisations, consents, approvals, registrations and licences are obtained, including aerial or access permits, as required for the execution of the contracted services."
        : "Na fase inicial, após a reunião de arranque, é efetuado o planeamento da execução do projeto e a sua integração com as intervenções já existentes. Nesta etapa, são obtidas todas as autorizações, consentimentos, aprovações, registos e licenças necessárias, incluindo permissões aéreas ou de acesso, conforme exigido para a realização dos serviços contratados."
));
$pdf->Ln(20);

// ---------- Subtítulo 2 ----------
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->Cell(
    0,
    8,
    toIso($lang === 'en'
        ? "2.  Topographic Support"
        : "2.  Apoio Topográfico"),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(5);

// ---------- Texto 2 ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "SUPREMEXPANSION's team works in collaboration with highly qualified topographic technicians throughout the country, ensuring fast and high-quality service for our clients. Topographic support may be carried out by one of our partners or by a technician appointed by the client."
        : "A equipa da SUPREMEXPANSION colabora com os melhores técnicos topográficos, de norte a sul do país, para garantir aos nossos clientes um serviço rápido e de elevada qualidade. O apoio topográfico poderá ser realizado por um dos nossos parceiros ou por um técnico indicado pelo cliente."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "For the creation of control points, which serve as the basis for all topographic and cadastral works, GPS equipment connected to the Geodetic Network is used, supported by permanent reference antennas."
        : "Para a criação dos pontos de apoio, que servirão de base para todos os trabalhos de topografia e cadastro, recorremos a equipamentos GPS com ligação à Rede Geodésica, através de antenas permanentes."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The control points are marked on site using steel markers of the “Geo” type or similar, clearly identified with spray markings, positioned in durable and easily recognisable locations, in accordance with technical specifications and existing site references."
        : "Os pontos de apoio serão assinalados no terreno com pregos de aço tipo “Geo” ou similares, devidamente sinalizados com spray, em locais duradouros e de fácil identificação, cumprindo as especificações técnicas e considerando os pontos já existentes no local."
));
$pdf->Ln(3);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "This process results in a set of coordinated reference points that form the technical basis for all topographic surveys."
        : "Este processo gera um conjunto de pontos coordenados que constituem a base de apoio para os levantamentos topográficos."
));

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 7 – LEVANTAMENTO ARQUITETÓNICO =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título ----------
$pdf->SetXY(20, 15);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en' ? "3.  Architectural Survey" : "3.  Levantamento Arquitetónico"),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(5);

// ---------- Texto introdutório ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "SUPREMEXPANSION’s field team carries out terrestrial and aerial surveys using static laser scanning technology and drones, ensuring the required accuracy and compliance with the specifications provided by the client."
        : "A equipa de campo da SUPREMEXPANSION realiza levantamentos terrestres e aéreos, recorrendo a tecnologia de laser scanner estático e drones, assegurando a precisão exigida e o cumprimento das especificações fornecidas pelo cliente."
));
$pdf->Ln(3);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The work methodology follows the steps below:"
        : "A metodologia de trabalho segue as seguintes etapas:"
));
$pdf->Ln(5);

// ---------- Lista de etapas ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);

$etapas = ($lang === 'en')
  ? [
      "-> Georeferencing of reference points with a total station (when requested by the client)",
      " ",
      "-> Topographic survey (when requested by the client)",
      " ",
      "-> Aerial survey using a drone (when requested by the client)",
      " ",
      "-> Internal and external terrestrial survey using a laser scanner",
      " ",
      "-> Transfer of the data to the technical office",
      " ",
      "-> Generation of point clouds using specialised software",
      " ",
      "-> Generation of an LGS file for virtual 3D viewing",
      " ",
      "-> Creation of a 3D model",
      " ",
      "-> Production of plans, sections and elevations"
    ]
  : [
      "-> Georreferenciação de pontos de referência com estação total (quando requisitado pelo cliente)",
      " ",
      "-> Levantamento topográfico (quando requisitado pelo cliente)",
      " ",
      "-> Levantamento aéreo com drone",
      " ",
      "-> Levantamento terrestre interno e externo com laser scanner",
      " ",
      "-> Envio dos dados para o gabinete técnico",
      " ",
      "-> Criação de nuvens de pontos com softwares especializados",
      " ",
      "-> Geração de ficheiro LGS para vista virtual",
      " ",
      "-> Criação de modelo 3D",
      " ",
      "-> Elaboração de plantas, cortes e alçados"
    ];

foreach ($etapas as $linha) {
    $pdf->SetX(20);
    $pdf->MultiCell(170, 6, toIso($linha));
}
$pdf->Ln(5);

// ---------- Texto final ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The data collected on site is subsequently processed in the technical office, enabling our draughts people to extract all the information required to characterise the area in a 3D environment or to deliver the point cloud, as requested."
        : "Os dados recolhidos em campo são posteriormente processados no gabinete técnico, permitindo aos desenhadores extrair toda a informação necessária para a caracterização da zona em ambiente 3D ou para a entrega da nuvem de pontos, conforme solicitado."
));
$pdf->Ln(10);

// ---------- Imagens inferiores ----------
$imagem1 = __DIR__ . '/../img/img15_pdf.png';
$imagem2 = __DIR__ . '/../img/img16_pdf.png';

if (file_exists($imagem1)) {
    $pdf->Image($imagem1, 25, 205, 80);
}
if (file_exists($imagem2)) {
    $pdf->Image($imagem2, 105, 206, 80);
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);



// ======= PÁGINA 8 – DESENHO DAS PLANTAS, CORTES E ALÇADOS =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Título ----------
$pdf->SetXY(20, 15);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en'
        ? "4.  Drawings of plans, sections and elevations"
        : "4.  Desenho das plantas, cortes, e alçados"
    ),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(5);

// ---------- Texto introdutório ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "The data acquisition work is distributed across different drafting departments, both 2D and 3D, using several processing, treatment and vector information capture methods."
        : "Os trabalhos de aquisição são direcionados para diferentes departamentos de desenho, tanto 2D como 3D, recorrendo a diversos métodos de processamento, tratamento e aquisição de informação vetorial."
));
$pdf->Ln(10);

// ---------- Painel cinzento por trás das imagens ----------
$panelX = 20;   // margem esquerda
$panelY = 60;   // começa logo após o texto introdutório
$panelW = 170;  // largura útil (210 - 2*20)
$panelH = 115;  // altura para cobrir a img17 + “topo” da img18

$pdf->SetFillColor(150, 150, 150); // cinzento claro (#EEE)
$pdf->Rect($panelX, $panelY, $panelW, $panelH, 'F');

// ---------- Primeira imagem (fica totalmente dentro do painel) ----------
$imgTopo = __DIR__ . '/../img/img17_pdf.png';
if (file_exists($imgTopo)) {
    // ocupa a zona superior do painel
    $img1X = $panelX + 5;      // pequena margem interna
    $img1Y = $panelY + 0;
    $img1W = $panelW - 10;     // respeita margens laterais
    $pdf->Image($imgTopo, 25, 67, $img1W);

    // legenda centrada, ainda dentro do painel
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($panelX, 70 + 72);
    $pdf->Cell(
        $panelW,
        6,
        toIso($lang === 'en'
            ? "Processing and vectorisation of information in a 3D environment"
            : "Tratamento e vetorização de informação em ambiente 3D"
        ),
        0,
        0,
        'C'
    );
}

// ---------- Segunda imagem (topo “entra” no painel) ----------
$imgBaixo = __DIR__ . '/../img/img18_pdf.png';
if (file_exists($imgBaixo)) {
    // começa um pouco abaixo do fim da primeira, ainda sobre o painel
    $img2X = $panelX + 5;
    $img2Y = $panelY + 85;   // este Y garante que o topo fica dentro do painel
    $img2W = $panelW - 10;
    $pdf->Image($imgBaixo, 25, 150, $img2W);

    // legenda fora do painel, mais abaixo
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($panelX, 189 + 60);
    $pdf->Cell(
        $panelW,
        6,
        toIso($lang === 'en'
            ? "Example of an exterior 3D point cloud"
            : "Exemplo de nuvem de pontos 3D Exterior"
        ),
        0,
        0,
        'C'
    );
}


// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);



// ======= PÁGINA 9 – VETORIZAÇÃO E ENTREGAS =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Imagem Superior ----------
$imgTopo = __DIR__ . '/../img/img19_pdf.png';
if (file_exists($imgTopo)) {
    $pdf->Image($imgTopo, 20, 15, 170);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetXY(10, 100);
    $pdf->Cell(
        0,
        8,
        toIso($lang === 'en'
            ? "Example of an interior 3D point cloud"
            : "Exemplo de nuvem de pontos 3D Interior"
        ),
        0,
        1,
        'C'
    );
}

// ---------- Texto ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 115);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "After merging and processing the point clouds, it is possible to carry out the precise vectorisation of all required elements."
        : "Após a junção e tratamento das nuvens de pontos, é possível proceder à vetorização rigorosa de todos os elementos pretendidos."
));
$pdf->Ln(5);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "In addition, point clouds can be provided in .LAS, .RCP, .E57 formats, as well as through free software provided by SUPREMEXPANSION — TruView — which allows you to navigate the cloud, view photographs of each scan, take measurements between two objects and obtain the X, Y and Z coordinates of the desired points."
        : "Em complemento, as nuvens de pontos podem ser disponibilizadas nos formatos .LAS, .RCP, .E57, bem como através de software gratuito fornecido pela SUPREMEXPANSION — TruView — que permite navegar na nuvem, visualizar as fotografias de cada varrimento, efetuar medições entre dois objetos e obter as coordenadas X, Y e Z dos pontos desejados."
));
$pdf->Ln(5);

// ---------- Subtítulo ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->Cell(
    0,
    10,
    toIso($lang === 'en' ? "VI.  Deliverables" : "VI.  Entregas"),
    0,
    1,
    'L'
);
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(5);

// ---------- Texto “Entregas” ----------
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "At the end of the survey, the data is delivered to the client in digital format and may include files in E57, RCP, RCS, LAS, DWG, PLN, PLA, RAF and/or IFC formats, as well as the corresponding LGSx file."
        : "No final do levantamento, os dados são entregues ao cliente em formato digital, podendo incluir ficheiros nos formatos E57, RCP, RCS, LAS, DWG, PLN, PLA, RAF e/ou IFC, bem como o ficheiro correspondente em LGS."
));
$pdf->Ln(5);

// ---------- Imagem Inferior ----------
$imgBaixo = __DIR__ . '/../img/img20_pdf.png';
if (file_exists($imgBaixo)) {
    $pdf->Image($imgBaixo, 30, 200, 150);
}

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 10 – HONORÁRIOS E CONDIÇÕES =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ---------- Secção VII – Honorários ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 35);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "VII.  Quotations" : "VII.  Honorários"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->SetLineWidth(0.6);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetX(20);

$pdf->Cell(0, 7, toIso($lang === 'en' ? "Architectural Survey" : "Levantamento Arquitetónico"), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);

$totalLiquido    = (float)($proposta['total_liquido'] ?? 0);


// ===== moeda/valores =====
$/* Recompute extras (LOD/3D/BIM) and Georreferenciação here so the
     final totals printed in the PDF include the geo-extra.
     This mirrors the later calculations but must run before we render
     the Section VII totals (otherwise the geo value added later is
     not reflected in the printed total). */

$baseTotalAreas = 0.0;
foreach ($areas as $aCalc) {
        $baseTotalAreas += (float)($aCalc['subtotal'] ?? 0);
}

$lodEscolhido      = $proposta['opcao_nivel_detalhe']  ?? null;
$modelo3dEscolhido = $proposta['opcao_modelo3d_nivel'] ?? null;
$bimEscolhido      = $proposta['opcao_bim']            ?? null;

$percentLOD = ($lodEscolhido && isset($ajustesLOD[$lodEscolhido])) ? (float)$ajustesLOD[$lodEscolhido] : 0.0;
$percent3D  = ($modelo3dEscolhido && isset($ajustes3D[$modelo3dEscolhido])) ? (float)$ajustes3D[$modelo3dEscolhido] : 0.0;
$bimKey = $bimEscolhido ? strtolower(trim($bimEscolhido)) : null;
$ajustesBIM_lc = [
    'bricscad' => 20,
    'archicad' => 20,
    'revit'    => 20,
];
$percentBIM = ($bimKey && isset($ajustesBIM_lc[$bimKey])) ? (float)$ajustesBIM_lc[$bimKey] : 0.0;

$extraOpcoesTotal = $baseTotalAreas * (($percentLOD + $percent3D + $percentBIM) / 100.0);

// Georreferenciação detection and extra
$ID_GEO = 11;
$temGeo = false;
foreach ((array)$servicos as $s) {
    $idS = (int)($s['id_servico'] ?? $s['id'] ?? 0);
    if ($idS === (int)$ID_GEO) { $temGeo = true; break; }
}
if (!$temGeo) {
    $temGeo = temServicoPorNome((array)$servicos, 'Georreferenciação');
}
if (!$temGeo) {
    if (!empty($proposta['inclui_georreferenciacao']) && (int)$proposta['inclui_georreferenciacao'] === 1) {
        $temGeo = true;
    }
}

$extraGeoTotal = 0.0;
if ($temGeo) {
    $totalBase = (float)$baseTotalAreas;
    if ($totalBase <= 1000.0) {
        $extraGeoTotal = 200.0;
    } else {
        $extraGeoTotal = 200.0 + (floor($totalBase / 1000.0) * 50.0);
    }
}

$extraOpcoesTotal += $extraGeoTotal;
$valorExtraOpcoes = $extraOpcoesTotal;

$qtdAreas = max(count($areas), 1);
$extraOpcoesPorArea = $extraOpcoesTotal / $qtdAreas;

$descontoPercent = (float)($proposta['desconto_percentagem'] ?? 0);
if ($descontoPercent < 0) $descontoPercent = 0;
if ($descontoPercent > 100) $descontoPercent = 100;

$factorDesc = 1.0 - ($descontoPercent / 100.0);

$valorArquitEUR = (float)$lev_arquit + (float)$valorDeslocacaoTotal + (float)$valorExtraOpcoes;

// ✅ aplica desconto global também ao total das áreas (levantamento arquitetónico)
$valorArquitEUR_desc = $valorArquitEUR * $factorDesc;

$valorFormatadoArquit = number_format(
    eurToCurrency($valorArquitEUR_desc, $currency),
    $currency === 'JPY' ? 0 : 2,
    $lang === 'en' ? '.' : ',',
    $lang === 'en' ? ',' : '.'
) . " " . $currency;

$pdf->Cell(0, 7, toIso($valorFormatadoArquit), 0, 1, 'L');


$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetX(20);
$pdf->Cell(0, 7, toIso($lang === 'en' ? "Topographic Survey" : "Levantamento Topográfico"), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);

$valorFormatadoTopo = number_format(
    eurToCurrency((float)$lev_topo, $currency),
    $currency === 'JPY' ? 0 : 2,
    $lang === 'en' ? '.' : ',',
    $lang === 'en' ? ',' : '.'
) . " " . $currency;

$pdf->Cell(0, 7, toIso($valorFormatadoTopo), 0, 1, 'L');

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetX(20);
$pdf->Cell(0, 7, toIso($lang === 'en' ? "Drone Survey" : "Levantamento com Drone"), 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);

$valorFormatadoDrone = number_format(
    eurToCurrency((float)$lev_drone, $currency),
    $currency === 'JPY' ? 0 : 2,
    $lang === 'en' ? '.' : ',',
    $lang === 'en' ? ',' : '.'
) . " " . $currency;

$pdf->Cell(0, 7, toIso($valorFormatadoDrone), 0, 1, 'L');

$pdf->Ln(8);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "Please note that the topographic service may be carried out by a technician appointed by the client."
        : "Relembramos que o serviço de topografia poderá ser realizado por um técnico indicado pelo cliente."
));
$pdf->Ln(4);

// fonte mais pequena + itálico
$pdf->SetFont('Helvetica', 'I', 7);

$pdf->SetX(20);
$pdf->MultiCell(
    170,
    3,
    toIso(
        $lang === 'en'
            ? "VAT at the legally applicable rate (currently 23%) will be added to the amounts described above."
            : "Aos valores acima discriminados será acrescido IVA à taxa legal em vigor, atualmente de 23%."
    )
);

$pdf->Ln(6);

// opcional: voltar à fonte normal depois
$pdf->SetFont('Helvetica', '', 9);


// ---------- Secção VIII – Condições de Pagamento ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "VIII.  Payment Terms" : "VIII.  Condições de Pagamento"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso($lang === 'en' ? "50% upon award and 50% upon delivery." : "50% na adjudicação e 50% na entrega."));
$pdf->Ln(10);

// ---------- Secção IX – Prazo de Execução ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "IX.  Lead Time" : "IX.  Prazo de Execução"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso($lang === 'en' ? "To be agreed." : "A combinar."));
$pdf->Ln(10);

// ---------- Secção X – Validade da Proposta ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "X.  VALIDITY OF THIS QUOTATION" : "X.  Validade da Proposta"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso($lang === 'en' ? "60 days." : "60 dias."));
$pdf->Ln(10);

// ---------- Secção XI – Disponibilidade ----------
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($lang === 'en' ? "XI.  Availability" : "XI.  Disponibilidade"), 0, 1, 'L');
$pdf->SetDrawColor(163, 1, 1);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(20);
$pdf->MultiCell(170, 6, toIso(
    $lang === 'en'
        ? "To be agreed (Subject to weather conditions)."
        : "A combinar (Por vezes condicionada por condições meteorológicas)."
));

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);


// ======= PÁGINA 11 – ENCERRAMENTO =======
$pdf->AddPage();

// ---------- Cabeçalho ----------
if (file_exists($imginicial)) {
    $pdf->Image($imginicial, 0, 0, 210);
}

// ===== idioma (EUR = PT, outras = EN) =====
$txt1 = ($lang === 'en')
  ? "We are committed to delivering the best of our services, always focused on quality and accuracy, ensuring the full satisfaction of our clients."
  : "Comprometemo-nos a oferecer o melhor dos nossos serviços, sempre com foco na qualidade e precisão, garantindo a plena satisfação dos nossos clientes.";

$txt2 = ($lang === 'en')
  ? "With no further matters to address at this time."
  : "Sem mais assuntos a tratar no momento, despedimo-nos com elevada estima e consideração.";

$txt3 = ($lang === 'en')
  ? "Sincerely,"
  : "Atenciosamente,";

$txtGerencia = ($lang === 'en')
  ? "(Management)"
  : "(A Gerência)";

// ---------- Texto principal ----------
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(20, 120);
$pdf->MultiCell(160, 7, toIso($txt1));

$pdf->Ln(5);
$pdf->SetX(20);
$pdf->MultiCell(160, 7, toIso($txt2));

$pdf->Ln(5);
$pdf->SetX(20);
$pdf->Cell(0, 10, toIso($txt3), 0, 1, 'L');

// ---------- Assinatura ----------
$assinatura = __DIR__ . '/../img/img21_pdf.png';
if (file_exists($assinatura)) {
    $pdf->Image($assinatura, 95, 160, 50);
}

// ---------- Legenda abaixo da assinatura ----------
$pdf->SetFont('Arial', 'I', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(90, 190);
$pdf->Cell(50, 8, toIso($txtGerencia), 0, 0, 'C');

// desativa page break só para desenhar o rodapé
$pdf->SetAutoPageBreak(false);

if (file_exists($imagemrodape1)) {

    $x = 3;
    $y = 286.5;

    $iconW = 9;
    $iconH = 9;

    $pdf->Image($imagemrodape1, $x, $y, $iconW, $iconH);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(60, 60, 60);

    $textX = $x + $iconW + 2;
    $textY = $y + 1.2;

    $pdf->SetXY($textX, $textY);

    $morada1 = "Rua de Coruche Nº: 60 e 62";
    $morada2 = "2080-094 Almeirim - Tel: 935 584 011";

    $w = 120;
    $h = 3.5;

    $pdf->Cell($w, $h, toIso($morada1), 0, 2, 'L');
    $pdf->Cell($w, $h, toIso($morada2), 0, 2, 'L');
}

if (file_exists($imagemrodape2)) {
    $pdf->Image($imagemrodape2, 150, 288, 60);
}

// volta a ligar page break para o resto do PDF (margem default do FPDF costuma ser 20)
$pdf->SetAutoPageBreak(true, 20);



// ======= PÁGINA 12 – ENCERRAMENTO VISUAL =======
$pdf->AddPage();

// ---------- Imagem de fundo ----------
$imgFundo = __DIR__ . '/../img/img22_pdf.png';
if (file_exists($imgFundo)) {
    $pdf->Image($imgFundo, 0, 0, 210, 297); // ocupa toda a página
}

// ---------- Logo ----------
if (file_exists($logoSupreme)) {
    $pdf->Image($logoSupreme, 5, 15, 120); // mesmo logo da capa
}

// ---------- Texto principal ----------
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(23, 43);
$pdf->Cell(
  0, 10,
  toIso(($lang === 'en') ? "FOLLOW US ON SOCIAL MEDIA" : "SIGAM-NOS NAS REDES SOCIAIS"),
  0, 1, 'L'
);


$pdf->SetFont('Arial', '', 12);
$pdf->SetXY(23, y: 51);
$pdf->Cell(0, 8, toIso("Facebook, Instagram, LinkedIn e YouTube"), 0, 1, 'L');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetX(45);
$pdf->Cell(0, 8, toIso("NIF: 517333198"), 0, 1, 'L');
$pdf->SetX(35);
$pdf->Cell(0, 8, toIso("TEL: PT +351 935 584 011"), 0, 1, 'L');
$pdf->SetX(35);
$pdf->Cell(0, 8, toIso("TEL: UK +44 755 779 5968"), 0, 1, 'L');
$pdf->SetX(33);
$pdf->Cell(0, 8, toIso("info@supremexpansion.com"), 0, 1, 'L');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(35);
$pdf->MultiCell(170, 6, toIso(
    "Rua de Coruche Nº: 60 e 62"
), 0, 'L');

$pdf->SetFont('Arial', '', 11);
$pdf->SetX(29);
$pdf->Cell(0, 8, toIso("2080-094 Almeirim - Tel: 935 584 011"), 0, 1, 'L');
$pdf->Ln(5);






















$totalBruto      = (float)($proposta['total_bruto'] ?? 0);
$valorDesconto   = (float)($proposta['valor_desconto'] ?? 0);
$totalIVA        = (float)($proposta['total_iva'] ?? 0);
$totalFinal      = (float)($proposta['total_final'] ?? 0);
$descontoPercent = (float)($proposta['desconto_percentagem'] ?? 0);



$valorDeslocacaoTotal   = (float)($proposta['preco_deslocacao_total'] ?? 0);
$qtdAreas               = max(count($areas), 1);
$valorDeslocacaoPorArea = $valorDeslocacaoTotal / $qtdAreas;

// Extra das opções (LOD/3D/BIM) moved earlier to ensure totals
// (calculation is performed above before Section VII so the
// georeference extra is included in printed totals).



// Georreferenciação handled earlier to avoid double-counting.

// ======================================
//   PÁGINA — TOPO IGUAL À IMAGEM
// ======================================
$pdf->AddPage();
$pdf->SetTextColor(0,0,0);

// ===========================
// IMAGEM DA SUPREMEXPANSION
// ===========================
$pdf->Image($logoSupreme , 20, 10, 70); 
// (ajusta o nome da imagem se for diferente)

// ===========================
// MORADA / INFO DA EMPRESA
// ===========================
$pdf->SetFont('Arial','',7);
$pdf->SetXY(10, 27);

$empresaInfoPT =
"Rua de Coruche Nº: 60 e 62 2080-094 Almeirim
Capital Social 2.500€ | CRC de Santarém | NIF 517 333 198
Email: info@supremexpansion.com | Tlm: PT (+351) 935 584 011 / UK (+44) 755 779 5968
(Chamada para rede móvel nacional)";

$empresaInfoEN =
"Rua de Coruche Nº: 60 e 62 2080-094 Almeirim
Share Capital 2,500 EUR | Commercial Registry (Santarém) | VAT No. 517 333 198
Email: info@supremexpansion.com | Phone: PT (+351) 935 584 011 / UK (+44) 755 779 5968
(National mobile network call)";

$pdf->MultiCell(90, 4, toIso(($lang==='en') ? $empresaInfoEN : $empresaInfoPT), 0, 'C');



// ===================== CABEÇALHO — PARTE DIREITA (Compactado até Y=40) =====================

// Linha superior
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.5);
$pdf->Line(110, 12, 200, 12);

// "Proposta Nº" centrado
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(110, 4);
$pdf->Cell(90, 8, toIso(($lang==='en') ? "Quotation No." : "Proposta N°"), 0, 0, 'C');

// Código da proposta (direita)
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetXY(110, 4);
$pdf->Cell(90, 8, toIso($codigo), 0, 0, 'R');

// Natureza (esquerda)
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(60,60,60);
$pdf->SetXY(112, 14);
$pdf->Cell(50, 4, toIso(($lang==='en') ? "Type: Quotation" : "Natureza: Proposta"), 0, 0, 'L');

// Folha nº (direita)
$pdf->SetFont('Arial','',8);
$pdf->SetTextColor(0,0,0);
$pdf->SetXY(110, 14);
$pdf->Cell(90, 4, toIso(($lang==='en') ? "Page 1 of 1" : "Folha Nº 1 de 1"), 0, 0, 'R');

// "Original"
$pdf->SetFont('Arial','B',8);
$pdf->SetXY(110, 18);
$pdf->Cell(90, 4, toIso(($lang==='en') ? "Original" : "Original"), 0, 0, 'R');

// Título "Exmo.(s) Senhor(es)"
$pdf->SetFont('Arial','B',9);
$pdf->SetTextColor(0,0,0);
$pdf->SetXY(110, 23);
$pdf->Cell(90, 5, toIso(($lang==='en') ? "Dear Sir/Madam" : "Exmo.(s) Senhor(es)"), 0, 0, 'L');

// Primeira linha horizontal (no Y exato como na imagem)
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.4);
$pdf->Line(110, 23, 200, 23);

// ===================== DADOS DO CLIENTE (parte direita) =====================

// Nome do cliente (negrito)
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0,0,0);
$pdf->SetXY(110, 30);
$pdf->Cell(90, 6, toIso($cliente), 0, 1, 'L');

// Obra / Morada
$pdf->SetFont('Arial', '', 11);
$pdf->SetXY(110, 36);
$pdf->Cell(90, 6, toIso($obra), 0, 1, 'L');

// Cidade
$pdf->SetXY(110, 42);
$pdf->Cell(90, 6, toIso($cidade), 0, 1, 'L');

// Linha horizontal inferior (igual à imagem)
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.4);
$pdf->Line(110, 51, 200, 51);


// Linha horizontal inferior — **TEM DE ACABAR no Y=40**
$pdf->Line(110, 28, 200, 28);

// ======================================
//   FAIXA DOS DADOS (Cliente Nº, Proposta, etc.)
// ======================================

// Y inicial da faixa (÷3)
$y = 62;  // 20.66 aprox

$pdf->SetFillColor(220,220,220);
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.5 / 2);

// ALTURA das células principais (÷3)
$h = 14;


$scale = 1/1.25;   // fator de redução = 0.714285...

// ======================================
//   FAIXA DOS DADOS (Cliente Nº, Proposta, etc.)
// ======================================

$y = 55;

$pdf->SetFillColor(220,220,220);
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.5);

// --------------------------------------
//  PRIMEIRA LINHA
// --------------------------------------

// CLIENTE Nº
$pdf->SetXY(10 * $scale, $y);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(38 * $scale, 6 * $scale, toIso(($lang==='en') ? "CUSTOMER ID." : "CLIENTE Nº"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 10 * $scale);
$pdf->Cell(38 * $scale, 8 * $scale, toIso("N/A"), 1, 0, 'C');

// V/ CONTRIBUINTE
$pdf->SetXY(48 * $scale, $y);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(38 * $scale, 6 * $scale, toIso(($lang==='en') ? "VAT NO." : "V/ CONTRIBUINTE"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 10 * $scale);
$pdf->Cell(38 * $scale, 8 * $scale, toIso("N/A"), 1, 0, 'C');

// VENDEDOR
$pdf->SetXY(86 * $scale, $y);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(32 * $scale, 6 * $scale, toIso(($lang==='en') ? "SALES REP" : "VENDEDOR"), 1, 2, 'C', true);
$pdf->SetFont('Arial','I', 10 * $scale);
$pdf->Cell(32 * $scale, 8 * $scale, toIso("Geral"), 1, 0, 'C');

// PROPOSTA
$pdf->SetXY(118 * $scale, $y);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(42 * $scale, 6 * $scale, toIso(($lang==='en') ? "QUOTATION NO." : "PROPOSTA"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 12 * $scale);
$pdf->Cell(42 * $scale, 8 * $scale, toIso($codigo), 1, 0, 'C');


// TOTAL
$pdf->SetXY(160 * $scale, $y);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(38 * $scale, 6 * $scale, toIso(($lang==='en') ? "TOTAL" : "TOTAL LIQUIDO"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 11 * $scale);
$pdf->Cell(
  38 * $scale, 8 * $scale,
  toIso(moneyPdf((float)$totalLiquido, $currency, $lang, $FX, $CURRENCY_DECIMALS)),
  1, 0, 'C'
);


// DATA EMISSÃO
$pdf->SetXY(198 * $scale, $y);
$pdf->SetFont('Arial','', 10 * $scale);
$pdf->Cell(52 * $scale, 6 * $scale, toIso(($lang==='en') ? "ISSUE DATE" : "DATA DE EMISSÃO"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 12 * $scale);
$pdf->Cell(52 * $scale, 8 * $scale, toIso($dataEmissaoBR), 1, 0, 'C');


// ======================================
//   SEGUNDA LINHA
// ======================================

$y2 = $y + (14);

// CONDIÇÕES DE PAGAMENTO
$pdf->SetXY(10 * $scale, $y2);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(80 * $scale, 6 * $scale, toIso(($lang==='en') ? "PAYMENT TERMS" : "CONDIÇÕES DE PAGAMENTO"), 1, 2, 'L', true);
$pdf->SetFont('Arial','B', 10 * $scale);
$pdf->Cell(80 * $scale, 8 * $scale, toIso(($lang==='en') ? "50% ON AWARD AND 50% ON DELIVERY" : "50% NA ADJUDICAÇÃO E 50% NA ENTREGA"), 1, 0, 'L');

// DATA VENCIMENTO
$pdf->SetXY(90 * $scale, $y2);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(38 * $scale, 6 * $scale, toIso(($lang==='en') ? "DUE DATE" : "DATA VENCIMENTO"), 1, 2, 'C', true);
$pdf->SetFont('Arial','B', 11 * $scale);
$pdf->Cell(38 * $scale, 8 * $scale, toIso($dataVencimentoBR), 1, 0, 'C');

// V/ REFª
$pdf->SetXY(128 * $scale, $y2);
$pdf->SetFont('Arial','', 9 * $scale);
$pdf->Cell(122 * $scale, 6 * $scale, toIso(($lang==='en') ? "YOUR REF." : "V/ REFª"), 1, 2, 'L', true);
$pdf->SetFont('Arial','', 10 * $scale);
$pdf->Cell(122 * $scale, 8 * $scale, toIso(""), 1, 0, 'L');


// ======================================
//   TABELA DETALHE  (Serviços + Áreas)
// ======================================

$scale = 1/1.1;  // redução exata pedida

// ponto de partida
$yTabela = $y2 + 15;

// larguras (todas divididas por 1.25)
$xInicio   = 8.65 * $scale;
$wRef      = 15.2 * $scale;
$wDesc     = 85.2 * $scale;
$wQuant    = 18.2 * $scale;
$wUni      = 12.2 * $scale;
$wPreco    = 25.2 * $scale;
$wDescPerc = 15.2 * $scale;
$wValor    = 25.2 * $scale;
$wIva      = 15.2 * $scale;

$hCab   = 6 * $scale;
$hLinha = 6 * $scale;

// ---------- CABEÇALHO ----------
$pdf->SetXY($xInicio, $yTabela);
$pdf->SetFillColor(200,200,200);
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0.3 * $scale);
$pdf->SetFont('Arial','B',7 * $scale);

$pdf->Cell($wRef,      $hCab, toIso(($lang==='en') ? 'REF' : 'REFª'),         1, 0, 'C', true);
$pdf->Cell($wDesc,     $hCab, toIso(($lang==='en') ? 'DESCRIPTION' : 'DESCRIÇÃO'),    1, 0, 'C', true);
$pdf->Cell($wQuant,    $hCab, toIso(($lang==='en') ? 'QTY' : 'QUANT.'),       1, 0, 'C', true);
$pdf->Cell($wUni,      $hCab, toIso(($lang==='en') ? 'UNIT' : 'UNI'),          1, 0, 'C', true);
$pdf->Cell($wPreco,    $hCab, toIso(($lang==='en') ? 'PRICE EXCL. VAT' : 'P.VENDA S/IVA'),1, 0, 'C', true);
$pdf->Cell($wDescPerc, $hCab, toIso(($lang==='en') ? 'DISCOUNT' : 'DESC'),         1, 0, 'C', true);
$pdf->Cell($wValor,    $hCab, toIso(($lang==='en') ? 'TOTAL AMOUNT' : 'VALOR TOTAL'),  1, 0, 'C', true);
$pdf->Cell($wIva,      $hCab, toIso(($lang==='en') ? 'VAT' : 'IVA'),          1, 1, 'C', true);

// fundo cinzento claro
$pdf->SetFillColor(235,235,235);
$pdf->SetFont('Arial','',7 * $scale);

$yLinha = $yTabela + $hCab;



// função para imprimir linhas
$linhaTabela = function(
    $pdf, $ref, $desc, $quant = '', $uni = '', $preco = '', $descPerc = '', $valor = '', $iva = ''
) use ($xInicio,$wRef,$wDesc,$wQuant,$wUni,$wPreco,$wDescPerc,$wValor,$wIva,$hLinha) {

    // REFª
    $pdf->SetFillColor(235,235,235);
    $pdf->SetX($xInicio);
    $pdf->Cell($wRef, $hLinha, toIso($ref), 1, 0, 'C', false);

    // DESCRIÇÃO
    $pdf->SetFillColor(240,240,240);
    $pdf->Cell($wDesc, $hLinha, toIso($desc), 1, 0, 'L', true);

    // restantes colunas
    $pdf->SetFillColor(255,255,255);
    $pdf->Cell($wQuant,    $hLinha, $quant,     1, 0, 'R', !empty($quant));
    $pdf->Cell($wUni,      $hLinha, $uni,       1, 0, 'C', !empty($uni));
    $pdf->Cell($wPreco, $hLinha, $preco, 1, 0, 'R');
    $pdf->Cell($wDescPerc, $hLinha, $descPerc,  1, 0, 'R', !empty($descPerc));
    $pdf->Cell($wValor, $hLinha, $valor, 1, 0, 'R');
    $pdf->Cell($wIva,      $hLinha, $iva,       1, 1, 'C', !empty($iva));
};
// ======================================
//   1) SERVIÇOS — MOSTRA AS OPÇÕES (LOD, MODELO3D, BIM)
// ======================================

// valores escolhidos no formulário/bd
$lodEscolhido       = $proposta['opcao_nivel_detalhe']    ?? null;
$modelo3dEscolhido  = $proposta['opcao_modelo3d_nivel']   ?? null;
$bimEscolhido       = $proposta['opcao_bim']              ?? null;

// IDs REAIS dos serviços
$ID_LOD        = 6;
$ID_MODELO3D   = 8;
$ID_BIM        = 10;
$ID_TOPOGRAFIA = 12; 
$ID_DRONE      = 13;   

// ================================
// TRADUÇÃO NOMES DE SERVIÇOS (PDF)
// ================================
$TR_SERVICOS_EN = [
  'Levantamento Laser Scan'        => '3D Laser Scanning Survey',
  'Levantamento Arquitetónico'     => 'Architectural Survey',
  'Plantas'                        => 'Floor Plans',
  'Cortes'                         => 'Sections',
  'Alçados'                        => 'Elevations',
  'Nível de Detalhe'               => 'Level of Detail (LOD)',
  'Modelo 3D'                      => '3D Model',
  'Modelo 3D - Nível de Detalhe'   => '3D Model – Level of Detail',
  'Vista Virtual 3D 360°'          => '3D Virtual Tour 360°',
  'BIM'                            => 'Building Information Modeling (BIM)',
  'Georreferenciação'              => 'Georeferencing',
  'Levantamento Topográfico'       => 'Topographic Survey',
  'Levantamento Drone Fotos'       => 'Drone Survey (Photos)',
  'Nuvem de Pontos'                => 'Point Cloud',
  'Software para Consultar Dados'  => 'Viewer Software',
  'Renderizações'                  => 'Renders',
  'Levantamento Altimétrico'       => 'Altimetric Survey',
];

if (!function_exists('trServicoNome')) {
    function trServicoNome(string $nomePT, string $lang, array $mapEN): string {
    if ($lang !== 'en') return $nomePT;
    $nomePT = trim($nomePT);
    return $mapEN[$nomePT] ?? $nomePT; // fallback seguro
    }
}

foreach ($servicos as $s) {

    $idServico  = (int)$s['id_servico'];

    // 🔥 NÃO mostrar a topografia aqui!
    if ($idServico === $ID_TOPOGRAFIA) {
        continue;
    }
    // 🔥 NÃO mostrar o levantamento drone aqui!
    if ($idServico === $ID_DRONE) {
        continue;
    }


    $nome = trServicoNome((string)$s['nome_base'], $lang, $TR_SERVICOS_EN);



    // --- LOD ---
    if ($idServico === $ID_LOD && $lodEscolhido) {
        $nome .= " – " . $lodEscolhido;
    }

    // --- MODELO 3D ---
    if ($idServico === $ID_MODELO3D && $modelo3dEscolhido) {
        $nome .= " – " . $modelo3dEscolhido;
    }

    // imprime linha
    $linhaTabela($pdf, '', $nome, '', '', '', '', '');

    
    $yLinha += $hLinha;
}
// =========================================================
//  🔥 SE O SERVIÇO DE TOPOGRAFIA ESTIVER NA PROPOSTA
//     MOSTRAR O VALOR TAMBÉM NA TABELA DE SERVIÇOS
// =========================================================


$temTopografia = false;

// verificar se o serviço existe na proposta
foreach ($servicos as $s) {
    if ((int)$s['id_servico'] === $ID_TOPOGRAFIA) {
        $temTopografia = true;
        break;
    }
}

$temDrone = false;
$opcoesDroneLista = [];

foreach ($servicos as $s) {
    if ((int)$s['id_servico'] === 13) {   // <--- ID do LEVANTAMENTO DRONE
        $temDrone = true;

        // decodificar opções JSON
        if (!empty($s['opcao_escolhida'])) {
            $json = json_decode($s['opcao_escolhida'], true);
            if (is_array($json)) {
                $opcoesDroneLista = $json;
            }
        }
        break;
    }
}




// separador
$linhaTabela($pdf, '',''); 
$yLinha += $hLinha;



// ======================================
//   2) ÁREAS — REF começa AQUI
// ======================================
// =====================================================
// DISTRIBUIR UM TOTAL EM N PARTES, COM ARREDONDAMENTO
// garantindo que a soma final == total original
// (o "resto" vai para a última área)
// =====================================================
if (!function_exists('distribuirTotalPorAreas')) {
    function distribuirTotalPorAreas(float $total, int $n, int $dec = 2): array {
        if ($n <= 0) return [];
        if ($n === 1) return [round($total, $dec)];

        $parte = round($total / $n, $dec);

        $arr = array_fill(0, $n, $parte);

        // Ajuste do resto na última área para bater certo
        $somaParcial = $parte * ($n - 1);
        $arr[$n - 1] = round($total - $somaParcial, $dec);

        return $arr;
    }
}
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

if (!function_exists('distribuirProporcional')) {
  // Distribui um total (em cêntimos) por N linhas, proporcionalmente ao peso de cada linha.
  function distribuirProporcional(int $totalCents, array $weights): array {
    $n = count($weights);
    if ($n === 0) return [];

    $sumW = array_sum($weights);
    if ($sumW <= 0) {
      $base = intdiv($totalCents, $n);
      $out = array_fill(0, $n, $base);
      $rem = $totalCents - ($base * $n);
      for ($i = 0; $i < $rem; $i++) $out[$i] += 1;
      return $out;
    }

    $raw = [];
    $alloc = array_fill(0, $n, 0);
    $sumAlloc = 0;

    for ($i = 0; $i < $n; $i++) {
      $x = ($totalCents * $weights[$i]) / $sumW;
      $floor = (int) floor($x);
      $alloc[$i] = $floor;
      $sumAlloc += $floor;
      $raw[$i] = $x - $floor;
    }

    $rem = $totalCents - $sumAlloc;
    if ($rem > 0) {
      arsort($raw);
      $idxs = array_keys($raw);
      $k = 0;
      while ($rem > 0) {
        $alloc[$idxs[$k % $n]] += 1;
        $rem--;
        $k++;
      }
    }

    return $alloc;
  }
}

if (!function_exists('ivaCents')) {
  function ivaCents(int $netCents): int {
    return (int) round($netCents * 23 / 100);
  }
}

// ================================
// 1) Construir linhas base (SEM desconto)
// ================================
$lines = []; // cada item: ['ref','desc','qty','unit','baseNetCents']

// --- deslocação + extras (em EUR base) distribuídos por área, sem perder cêntimos ---
$qtdAreas = max(count($areas), 1);

$deslocTotalC = toCents($proposta['preco_deslocacao_total'] ?? 0);

// extra total (LOD/3D/BIM) já calculado por ti: $extraOpcoesTotal (EUR)
// garante que existe e é float
$weightsAreas = [];
foreach ($areas as $aW) {
  $weightsAreas[] = max(0, toCents((float)($aW['subtotal'] ?? 0)));
}

$extraTotalC = toCents($extraOpcoesTotal ?? 0);

// distribui proporcional ao "peso" (subtotal) de cada área
$extraParts  = distribuirProporcional($extraTotalC, $weightsAreas);


// dividir por áreas com soma exata
$deslocParts = distribuirProporcional($deslocTotalC, array_fill(0, $qtdAreas, 1));

$linhaAtual = 1;

foreach ($areas as $idx => $a) {
    $m2       = (float)($a['metros_quadrados'] ?? 0);
    $preco_m2 = (float)($a['preco_m2'] ?? 0);

    $subManualAtivo = !empty($a['subtotal_manual_ativo']) && (int)$a['subtotal_manual_ativo'] === 1;
    $subManual      = (float)($a['subtotal_manual'] ?? 0);

    $subtotalEUR = (float)($a['subtotal'] ?? ($m2 * $preco_m2));


    $baseNetC = toCents($subtotalEUR)
              + (int)($deslocParts[$idx] ?? 0)
              + (int)($extraParts[$idx]  ?? 0);

    $nomeArea = $a['nome_area'] ?? ("AREA " . str_pad($linhaAtual, 2, '0', STR_PAD_LEFT));

    $lines[] = [
        'ref' => (string)$linhaAtual,
        'desc' => $nomeArea,
        'qty' => number_format($m2, 2, ',', '.'),
        'unit' => 'm2',
        'baseNetCents' => $baseNetC,
    ];

    $linhaAtual++;
}

// --- TOPO (se existir) ---
if (!empty($temTopografia)) {
    $lines[] = [
        'ref' => (string)$linhaAtual,
        'desc' => ($lang === 'en' ? 'Topographic Survey' : 'Levantamento Topográfico'),
        'qty' => '',
        'unit' => '',
        'baseNetCents' => toCents($lev_topo ?? 0),
    ];
    $linhaAtual++;
}

// --- DRONE (se existir) ---
if (!empty($temDrone)) {
    $valorDroneEUR = (float)($proposta['preco_drone'] ?? 0);

    $opcoesTexto = "—";
    if (!empty($opcoesDroneLista)) $opcoesTexto = implode(", ", $opcoesDroneLista);

    $desc = ($lang === 'en' ? 'Drone Survey' : 'Levantamento Drone');

    if ($opcoesTexto !== "—") $desc .= " (" . $opcoesTexto . ")";

    $lines[] = [
        'ref' => (string)$linhaAtual,
        'desc' => $desc,
        'qty' => '',
        'unit' => '',
        'baseNetCents' => toCents($valorDroneEUR),
    ];
    $linhaAtual++;
}

// ================================
// 2) Aplicar DESCONTO GLOBAL às linhas (em cêntimos)
// ================================
$totalBrutoC = toCents($proposta['total_bruto'] ?? 0);
$totalLiquidoC = toCents($proposta['total_liquido'] ?? 0);
$valorDescontoC = toCents($proposta['valor_desconto'] ?? 0);

// Se por algum motivo a BD não tiver valor_desconto consistente, recalcula:
if ($valorDescontoC <= 0 && !empty($proposta['desconto_percentagem'])) {
    $d = (float)$proposta['desconto_percentagem'];
    $valorDescontoC = (int) round($totalBrutoC * ($d / 100));
}

// Soma base das linhas (deve ser totalBruto; mas não confiamos a 100%)
$sumBaseC = 0;
$weights = [];
foreach ($lines as $ln) {
    $sumBaseC += $ln['baseNetCents'];
    $weights[] = max(0, $ln['baseNetCents']);
}

// Distribuir desconto proporcionalmente por cada linha
$discParts = distribuirProporcional($valorDescontoC, $weights);

// Calcular net por linha após desconto
for ($i=0; $i<count($lines); $i++) {
    $lines[$i]['discCents'] = (int)$discParts[$i];
    $lines[$i]['netCents']  = $lines[$i]['baseNetCents'] - $lines[$i]['discCents'];
    if ($lines[$i]['netCents'] < 0) $lines[$i]['netCents'] = 0;
}

// Garantir que soma NET = total_liquido da BD (ajuste de 1-2 cêntimos, se necessário)
$sumNetC = array_sum(array_column($lines, 'netCents'));
$diffNet = $totalLiquidoC - $sumNetC;
if ($diffNet !== 0 && count($lines) > 0) {
    // ajusta na última linha (pode ser + ou -)
    $last = count($lines) - 1;
    $lines[$last]['netCents'] += $diffNet;
}

// ================================
// 3) IVA por linha + total (e ajustar para bater com a BD)
// ================================
$totalIvaC   = toCents($proposta['total_iva'] ?? 0);
$totalFinalC = toCents($proposta['total_final'] ?? 0);

foreach ($lines as $i => $ln) {
    $ivaC = ivaCents($ln['netCents']);
    $lines[$i]['ivaCents'] = $ivaC;
    $lines[$i]['totCents'] = $ln['netCents'] + $ivaC;
}

$sumVatC = array_sum(array_column($lines, 'ivaCents'));
$sumTotC = array_sum(array_column($lines, 'totCents'));

// Ajuste final: força IVA/TOTAL a bater com a BD (normalmente diferença de 1 cêntimo)
if (count($lines) > 0) {
    $last = count($lines) - 1;

    $diffVat = $totalIvaC - $sumVatC;
    if ($diffVat !== 0) {
        $lines[$last]['ivaCents'] += $diffVat;
        $lines[$last]['totCents'] += $diffVat;
        $sumVatC += $diffVat;
        $sumTotC += $diffVat;
    }

    $diffTot = $totalFinalC - $sumTotC;
    if ($diffTot !== 0) {
        // por segurança (se o total_final tiver arredondamento diferente)
        $lines[$last]['totCents'] += $diffTot;
        // para não quebrar a coerência, mete a diferença no IVA
        $lines[$last]['ivaCents'] += $diffTot;
        $sumTotC += $diffTot;
        $sumVatC += $diffTot;
    }
}

// A partir daqui:
// - soma netCents == total_liquido (BD)
// - soma ivaCents == total_iva (BD)
// - soma totCents == total_final (BD)
$descontoPercent = (float)($proposta['desconto_percentagem'] ?? 0);
$descontoFmt = number_format($descontoPercent, 0) . "%";

foreach ($lines as $ln) {
    $precoFmt = toIso(moneyPdf(fromCents($ln['netCents']), $currency, $lang, $FX, $CURRENCY_DECIMALS));
    $valorFmt = toIso(moneyPdf(fromCents($ln['totCents']), $currency, $lang, $FX, $CURRENCY_DECIMALS));

    $linhaTabela(
        $pdf,
        $ln['ref'],
        $ln['desc'],
        $ln['qty'],
        $ln['unit'],
        $precoFmt,
        $descontoFmt,
        $valorFmt,
        '23%'
    );

    $yLinha += $hLinha;
}



// =======================================
//   BANDA — PAGAMENTO POR TRANSFERÊNCIA
// =======================================

// Linha cinzenta longa
$pdf->SetY(219); 
$pdf->SetX(7.6);
$pdf->SetFillColor(220,220,220);
$pdf->SetDrawColor(200,200,200);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(
  193, 8,
  toIso(($lang==='en') ? 'Payment by Bank Transfer' : 'Pagamento por Transferência Bancária'),
  1, 1, 'C', true
);

// IBAN / Banco
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(
  193, 7,
  toIso('MILLENNIUM BCP - IBAN PT50 0033 0000 4568 8520 1850 5'),
  0, 1, 'C'
);
// adiciona espaço antes das tabelas finais
$pdf->Ln(3);

// atualiza posição Y para as tabelas finais
$yRodape = $pdf->GetY() + 2;


// ===============================
//     RODAPÉ — TABELAS FINAIS
// ===============================

// posição do rodapé
$yRodape = 230; // ajustado para ficar igual à imagem
$pdf->SetY(234);

// -------------------------------
//  BLOCO ESQUERDA — IVA
// -------------------------------
$pdf->SetFillColor(220,220,220);
$pdf->SetDrawColor(0,0,0);
$pdf->SetFont('Arial','B',7);

$pdf->SetX(7.6);
$pdf->Cell(30,6,toIso(($lang==='en') ? 'Rate' : 'Taxa'),1,0,'C',true);
$pdf->Cell(40,6, toIso(($lang==='en') ? 'Tax Base' : 'Incidência'),1,0,'C',true);
$pdf->Cell(40,6,toIso(($lang==='en') ? 'VAT Amount' : 'Valor de I.V.A.'),1,1,'C',true);

$pdf->SetFont('Arial','',7);

$totalLiquido = (float)($proposta['total_liquido'] ?? 0);
$totalIVAesquerda = (float)($proposta['total_iva'] ?? 0);
// linha 23% (principal)
$pdf->SetX(7.6);
$pdf->Cell(30,6,'23%',1,0,'C');
$pdf->Cell(40,6, toIso(moneyPdf((float)$totalLiquido, $currency, $lang, $FX, $CURRENCY_DECIMALS)), 1,0,'R');
$pdf->Cell(40,6, toIso(moneyPdf((float)$totalIVAesquerda, $currency, $lang, $FX, $CURRENCY_DECIMALS)), 1,1,'R');

// restantes linhas (todas zero)
for ($i=0;$i<4;$i++) {
    $pdf->SetX(7.6);
    $pdf->Cell(30,6,'0%',1,0,'C');
    $pdf->Cell(40,6, toIso(moneyPdf(0, $currency, $lang, $FX, $CURRENCY_DECIMALS)), 1,0,'R');
    $pdf->Cell(40,6, toIso(moneyPdf(0, $currency, $lang, $FX, $CURRENCY_DECIMALS)), 1,1,'R');

}

// nota legal
$pdf->SetFont('Arial','',6);
$pdf->SetX(7.6);
$pdf->Cell(
  110, 5,
  toIso(($lang==='en')
    ? 'This document does not constitute a transport document under Decree-Law No. 147/2003.'
    : 'Este documento não constitui documento de transporte, nos termos do Decreto-Lei n.º 147/2003'
  ),
  0, 1, 'L'
);



// -------------------------------
//  BLOCO DIREITA — TOTAIS GERAIS
// -------------------------------
$pdf->SetFont('Arial','B',8);
$pdf->SetFillColor(230,230,230);

// coluna esquerda — labels
$pdf->SetXY(120, 234);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'GROSS TOTAL' : 'TOTAL BRUTO'),1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'LINE DISCOUNT' : 'DESCONTO LINHA'),1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? ('GLOBAL DISCOUNT            ' . $descontoPercent . '%') : ('DESCONTO GLOBAL            ' . $descontoPercent . '%')),1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'NET TOTAL' : 'TOTAL LIQUIDO') ,1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'TOTAL VAT' : 'TOTAL I.V.A.'),1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'SHIPPING TOTAL' : 'TOTAL PORTES'),1,1,'L',true);
$pdf->SetX(120);
$pdf->Cell(50,6,toIso(($lang==='en') ? 'GRAND TOTAL' : 'TOTAL'),1,1,'L',true);



// coluna direita — valores
$pdf->SetFont('Arial','',8);
$pdf->SetXY(170.6, 234);

$pdf->Cell(30,6, toIso(moneyPdf((float)$totalBruto,    $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf(0,                  $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf((float)$valorDesconto,$currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf((float)$totalLiquido, $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf((float)$totalIVA,     $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf(0,                  $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');

$pdf->SetFont('Arial','B',9);
$pdf->SetX(170.6);
$pdf->Cell(30,6, toIso(moneyPdf((float)$totalFinal,   $currency, $lang, $FX, $CURRENCY_DECIMALS)),1,1,'R');


$incluir_lod_imgs = isset($proposta['inclui_lod_imgs'])
  ? (int)$proposta['inclui_lod_imgs']
  : (!empty($_POST['incluir_lod_imgs']) ? 1 : 1); // default 1

if ($incluir_lod_imgs === 1) {

  // =======================================================
  //  🔥 3 PÁGINAS EXTRA COM BASE NO NÍVEL DE DETALHE (LOD)
  // =======================================================

  $lod = $proposta['opcao_nivel_detalhe'] ?? '1:100';

  $map = [
      '1:200' => 'LOD_200',
      '1:100' => 'LOD_100',
      '1:50'  => 'LOD_50',
      '1:20'  => 'LOD_50',
      '1:1'   => 'LOD_50',
  ];

  $pastaLOD = $map[$lod] ?? 'LOD_200';
  $basePath = __DIR__ . '/../uploads/pdf_templates/' . $pastaLOD . '/';

  // ✅ sufixo do ficheiro conforme idioma/moeda
  // (EUR => pt => "pag1.png"; não-EUR => en => "pag1_ing.png")
  $sufixo = ($lang === 'en') ? '_ing' : '';

  if (is_dir($basePath)) {
      for ($i = 1; $i <= 3; $i++) {
          $ficheiro = $basePath . 'pag' . $i . $sufixo . '.png';

          // fallback: se não existir o _ing, usa o normal
          if (!file_exists($ficheiro)) {
              $ficheiro = $basePath . 'pag' . $i . '.png';
          }

          if (file_exists($ficheiro)) {
              $pdf->AddPage();
              $pdf->Image($ficheiro, 0, 0, 210, 297);
          }
      }
  }
}










// ---------- Output ----------

// limpar caracteres inválidos para nomes de ficheiro
if (!function_exists('limparNome')) {
    function limparNome($str) {
        $str = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$str);
        $str = preg_replace('/[^A-Za-z0-9_-]/', '_', $str);
        $str = preg_replace('/_+/', '_', $str);
        return trim($str, '_');
    }
}
$nomeCliente    = limparNome($proposta['nome_cliente'] ?? 'Cliente');
$codigoProposta = limparNome($proposta['codigo'] ?? 'Codigo');

// nome final do PDF
$nomeArquivo = "PRO_{$codigoProposta}.pdf";

// pasta final normal
$dirNormal = __DIR__ . '/../uploads/propostas/';
if (!is_dir($dirNormal)) {
    mkdir($dirNormal, 0777, true);
}

// caminho final normal
$pathNormal = $dirNormal . $nomeArquivo;

// ===== PREVIEW MODE (forçado por globals) =====
$previewPath = $GLOBALS['PDF_PREVIEW_PATH'] ?? null;
$previewMode = !empty($GLOBALS['PDF_PREVIEW_MODE']);

if ($previewMode && $previewPath) {

    // garante pasta
    $dirPrev = dirname($previewPath);
    if (!is_dir($dirPrev)) {
        mkdir($dirPrev, 0777, true);
    }

    // debug “hard” (se não conseguir escrever, morre aqui com mensagem clara)
    if (!is_writable($dirPrev)) {
        throw new Exception("Sem permissões de escrita em: " . $dirPrev);
    }

    $pdf->Output('F', $previewPath);

    // valida que criou mesmo
    if (!file_exists($previewPath) || filesize($previewPath) === 0) {
        throw new Exception("Falha ao gravar PDF preview em: " . $previewPath);
    }

    return $previewPath;
}

// ===== modo normal =====
$pdf->Output('F', $pathNormal);

if (!file_exists($pathNormal) || filesize($pathNormal) === 0) {
    throw new Exception("Falha ao gravar PDF normal em: " . $pathNormal);
}

return $pathNormal;
