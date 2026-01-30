<?php
include 'protecao.php';
$isContabilista = ($_SESSION['nivel_acesso'] ?? 0) == 3;
require_once __DIR__ . '/../conexao/conexao.php';
include 'cabecalho.php';

$nivel = (int)($_SESSION['nivel_acesso'] ?? 0);
$meuId = (int)($_SESSION['user_id'] ?? 0);


$nome = $_GET['nome'] ?? '';
$cargo = $_GET['cargo'] ?? '';

$where = [];
$params = [];

if ($nome !== '') {
  $where[] = "(u.nome LIKE ? OR u.email LIKE ?)";
  $params[] = "%$nome%";
  $params[] = "%$nome%";
}

if ($cargo !== '') {
  $where[] = "a.nome_acesso = ?";
  $params[] = $cargo;
}

$sql = "
  SELECT u.*, a.nome_acesso AS cargo
  FROM utilizadores u
  LEFT JOIN acesso a ON a.id = u.acesso_id
  WHERE u.acesso_id < 6
";

// ✅ Se for Funcionário (5) → só pode ver o próprio registo
if ($nivel === 5 || $nivel === 4) {
  $sql .= " AND u.id = ? ";
  $params[] = $meuId;
}

// filtros
if (count($where)) {
  $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY u.nome ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Funcionários | SupremeXpansion</title>
  <link rel="icon" type="image/png" href="../img/icon.png">
  <link rel="stylesheet" href="../css/funcionarios.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">


</head>
<body>
  <br><br><br><br>
  <main class="container">
    <h1>
      Equipa SupremeXpansion
      <?php if (!$isContabilista && (int)($_SESSION['nivel_acesso'] ?? 0) > 4): ?>
        <a href="criar_funcionario.php" class="btn-criar">
          <i class="fa-solid fa-user-plus"></i> Criar Funcionário
        </a>
      <?php endif; ?>

    </h1>

    <?php if ((int)($_SESSION['nivel_acesso'] ?? 0) > 4): ?>
    <form class="filter-bar" method="get">
      <input type="text" name="nome" placeholder="Nome ou email..." value="<?= htmlspecialchars($nome) ?>">
      <select name="cargo">
        <option value="">Todos os cargos</option>
        <option value="Admin" <?= $cargo==='Admin'?'selected':'' ?>>Admin</option>
        <option value="Proprietário" <?= $cargo==='Proprietário'?'selected':'' ?>>Proprietário</option>
        <option value="Contabilista" <?= $cargo==='Contabilista'?'selected':'' ?>>Contabilista</option>
        <option value="Comercial" <?= $cargo==='Comercial'?'selected':'' ?>>Comercial</option>
        <option value="Funcionário" <?= $cargo==='Funcionário'?'selected':'' ?>>Funcionário</option>
      </select>
      <button type="submit">Filtrar</button>
    </form>
    <?php endif; ?>

<div class="table-scroll">
    <table id="tabela-funcionarios" class="styled-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Cargo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($funcionarios as $f): ?>
            <tr onclick="window.location='ver_funcionario.php?id=<?= $f['id'] ?>'">
                <td><?= htmlspecialchars($f['nome']) ?></td>
                <td><?= htmlspecialchars($f['email']) ?></td>
                <td><?= htmlspecialchars($f['cargo'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

  </main>
<?php
include 'rodape.php';
?>
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
