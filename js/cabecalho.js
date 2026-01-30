document.addEventListener("DOMContentLoaded", function() {
    const abrir = document.getElementById("abrirGestao");
    const sidebar = document.getElementById("sidebarGestao");
    const fechar = document.getElementById("fecharSidebar");
    const overlay = document.getElementById("overlayGestao");
    const perfil = document.querySelector(".user-box");
    const current = window.location.pathname.split("/").pop(); // ficheiro atual

    const servicos = ["servico.php", "servico1.php", "servico2.php", "servico3.php"];
    const portfolio = ["portfolio.php", "portfolio_res.php", "portfolio_urb.php", "portfolio_com.php", "portfolio_ind.php"];

    // Marca o link em que o utilizador está
    document.querySelectorAll(".navbar a").forEach(link => {
        const href = link.getAttribute("href");

        if (href === current) {
            link.classList.add("active");
        }
    });

    // Marca SERVIÇOS como ativo se estiver numa subpágina
    if (servicos.includes(current)) {
        const servicosLink = document.querySelector(".servicos-dropdown > a");
        if (servicosLink) servicosLink.classList.add("active");
    }

    // Marca PORTFOLIO como ativo se estiver numa subpágina
    if (portfolio.includes(current)) {
        const portfolioLink = document.querySelector(".portfolio-dropdown > a");
        if (portfolioLink) portfolioLink.classList.add("active");
    }
    // === Abrir Sidebar ===
    function abrirSidebar() {
        sidebar.classList.add("aberta");
        overlay.classList.add("ativo");
    }

    // === Fechar Sidebar ===
    function fecharSidebar() {
        sidebar.classList.remove("aberta");
        overlay.classList.remove("ativo");
    }

    // === Clique no botão de abrir ===
    if (abrir) {
        abrir.addEventListener("click", e => {
            e.preventDefault();

            // Se já estiver aberta e clicar de novo -> fecha
            if (sidebar.classList.contains("aberta")) {
                fecharSidebar();
            } else {
                abrirSidebar();
            }
        });
    }

    // === Clique no botão de fechar ===
    if (fechar) fechar.addEventListener("click", fecharSidebar);

    // === Clique fora (overlay) fecha ===
    if (overlay) overlay.addEventListener("click", fecharSidebar);

    // === Clique fora (qualquer parte da página) fecha ===
    document.addEventListener("click", e => {
        const clicouFora = !sidebar.contains(e.target) && !abrir.contains(e.target);
        if (clicouFora && sidebar.classList.contains("aberta")) {
            fecharSidebar();
        }
    });

    // === Redireciona ao clicar no perfil ===
    if (perfil) {
        perfil.addEventListener("click", () => {
            window.location.href = "perfil.php";
        });
    }
});