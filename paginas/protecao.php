<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se não estiver autenticado → manda para login
if (empty($_SESSION['autenticado'])) {
    header("Location: login.php");
    exit;
}

$nivel = $_SESSION['nivel_acesso'] ?? null;

// ❌ Nível 7 (newsletter) não entra no backoffice
if ($nivel == 7) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

// ------------------------------
// 🔥 Definir permissões por nível
// ------------------------------

$pagina = basename($_SERVER['PHP_SELF']);

// ⭐ CLIENTE – ACESSO PERMITIDO
$paginas_cliente = [
    'propostas.php',
    'ver_proposta.php',
    'projetos.php',
    'ver_projeto.php',
    'configurar_projeto.php',
    'perfil.php'
];

// ⭐ FUNCIONÁRIO – ACESSO PERMITIDO
$paginas_funcionario = [
    'projetos.php',
    'ver_projeto.php',
    'configurar_projeto.php',
    'perfil.php',
    'ver_funcionario.php',
    'funcionarios.php'

];

$paginas_comercial = [
    'projetos.php',
    'ver_projeto.php',
    'configurar_projeto.php',
    'perfil.php',
    'clientes.php',
    'ver_utilizador.php',
    'ver_cliente.php',
    'ver_funcionario.php',
    'funcionarios.php'

];

// Caso seja CLIENTE (6)
if ($nivel == 6) {

    if (!in_array($pagina, $paginas_cliente)) {
        header("Location: acesso_negado.php");
        exit;
    }

}

// Caso seja FUNCIONÁRIO (5)
if ($nivel == 5) {

    if (!in_array($pagina, $paginas_funcionario)) {
        header("Location: acesso_negado.php");
        exit;
    }

}
if ($nivel == 4) {

    if (!in_array($pagina, $paginas_comercial)) {
        header("Location: acesso_negado.php");
        exit;
    }

}
// Os níveis 1,2,3, têm acesso total
?>
