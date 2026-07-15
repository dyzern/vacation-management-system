<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

requireRole('admin');

$adminId = getCurrentUserId();
$emailAdmin = $_SESSION['email'] ?? 'admin@ribas.pt';
$resumoEquipaAdmin = obterResumoAdmin($pdo);
$mensagem = '';
$erro = '';
$modoEdicao = false;
$utilizadorEdicao = null;
$valores = [
    'id' => '',
    'nome' => '',
    'username' => '',
    'email' => '',
    'perfil' => '',
    'responsavel_id' => '',
];

$responsaveis = listarResponsaveisAtivos($pdo);

if (isset($_GET['editar']) && ctype_digit((string) $_GET['editar'])) {
    $utilizadorEdicao = obterUtilizadorPorId($pdo, (int) $_GET['editar']);

    if ($utilizadorEdicao !== null) {
        $modoEdicao = true;
        $valores = [
            'id' => (string) $utilizadorEdicao['id'],
            'nome' => $utilizadorEdicao['nome'],
            'username' => $utilizadorEdicao['username'],
            'email' => $utilizadorEdicao['email'],
            'perfil' => $utilizadorEdicao['perfil'],
            'responsavel_id' => (string) ($utilizadorEdicao['responsavel_id'] ?? ''),
        ];
    } else {
        $erro = 'O utilizador selecionado não foi encontrado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'remover') {
        $utilizadorId = (int) ($_POST['utilizador_id'] ?? 0);

        if ($utilizadorId === $adminId) {
            $erro = 'Não pode desativar o utilizador autenticado.';
        } else {
            try {
                desativarUtilizador($pdo, $utilizadorId, $adminId);
                $mensagem = 'Utilizador desativado com sucesso.';
            } catch (Throwable $throwable) {
                $erro = $throwable->getMessage() !== ''
                    ? $throwable->getMessage()
                    : 'Não foi possível desativar o utilizador.';
            }
        }
    } else {
        $utilizadorId = (int) ($_POST['utilizador_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $perfil = trim($_POST['perfil'] ?? '');
        $responsavelId = trim($_POST['responsavel_id'] ?? '');

        $valores = [
            'id' => (string) $utilizadorId,
            'nome' => $nome,
            'username' => $username,
            'email' => $email,
            'perfil' => $perfil,
            'responsavel_id' => $responsavelId,
        ];

        if ($nome === '' || $username === '' || $email === '' || $perfil === '') {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif (!in_array($perfil, ['admin', 'responsavel', 'colaborador'], true)) {
            $erro = 'Perfil inválido.';
        } elseif ($perfil === 'colaborador' && $responsavelId === '') {
            $erro = 'Um colaborador tem de ficar associado a um responsável.';
        } elseif ($acao === 'criar' && $password === '') {
            $erro = 'A palavra-passe é obrigatória para novos utilizadores.';
        } else {
            $check = $pdo->prepare(
                "SELECT id
                 FROM utilizadores
                 WHERE (username = :username OR email = :email)
                   AND id <> :id
                 LIMIT 1"
            );
            $check->execute([
                'username' => $username,
                'email' => $email,
                'id' => $acao === 'editar' ? $utilizadorId : 0,
            ]);

            if ($check->fetch()) {
                $erro = 'Já existe um utilizador com esse username ou e-mail.';
            } else {
                try {
                    $dados = [
                        'nome' => $nome,
                        'username' => $username,
                        'email' => $email,
                        'password' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '',
                        'perfil' => $perfil,
                        'responsavel_id' => $perfil === 'colaborador' ? (int) $responsavelId : null,
                    ];

                    if ($acao === 'editar') {
                        if ($utilizadorId === $adminId && $perfil !== 'admin') {
                            throw new RuntimeException('Não pode alterar o seu próprio perfil de administrador.');
                        }

                        atualizarUtilizador($pdo, $utilizadorId, $dados, $adminId);
                        $mensagem = 'Utilizador atualizado com sucesso.';
                        $modoEdicao = false;
                    } else {
                        criarUtilizador($pdo, $dados, $adminId);
                        $mensagem = 'Utilizador criado com sucesso.';
                    }

                    $valores = [
                        'id' => '',
                        'nome' => '',
                        'username' => '',
                        'email' => '',
                        'perfil' => '',
                        'responsavel_id' => '',
                    ];
                } catch (Throwable $throwable) {
                    $erro = $throwable->getMessage() !== ''
                        ? $throwable->getMessage()
                        : 'Não foi possível guardar o utilizador.';
                }
            }
        }
    }

    $responsaveis = listarResponsaveisAtivos($pdo);
}

$utilizadores = listarUtilizadoresComRelacao($pdo);

$porPagina = 4;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$totalUtilizadores = count($utilizadores);
$totalPaginas = max(1, (int) ceil($totalUtilizadores / $porPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
$utilizadoresPagina = array_slice($utilizadores, ($paginaAtual - 1) * $porPagina, $porPagina);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Utilizadores</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        function toggleResponsavelField() {
            const perfil = document.getElementById('perfil');
            const responsavelWrap = document.getElementById('responsavel-wrap');

            if (!perfil || !responsavelWrap) {
                return;
            }

            responsavelWrap.style.display = perfil.value === 'colaborador' ? 'block' : 'none';
        }
    </script>
</head>
<body class="dashboard-page">

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-top">
            <img src="../assets/img/ribas-logo.png" alt="Ribas" class="sidebar-logo">
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>
                <span>Dashboard</span>
            </a>

            <a href="pedidos.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-regular fa-calendar-days"></i></span>
                <span>Todos os Pedidos</span>
                <?php if (($resumoEquipaAdmin['pedidos_pendentes'] ?? 0) > 0): ?>
                    <span class="sidebar-badge"><?= e((string) $resumoEquipaAdmin['pedidos_pendentes']) ?></span>
                <?php endif; ?>
            </a>

            <a href="register.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fa-solid fa-users"></i></span>
                <span>Gestão de Utilizadores</span>
            </a>

            <a href="../auth/logout.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                <span>Terminar Sessão</span>
            </a>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-right">
                <div class="admin-userbox">
                    <div class="admin-avatar"><?= e(getUserInitials($_SESSION['nome'] ?? 'Administrador')) ?></div>
                    <div class="admin-userinfo">
                        <strong>Administrador</strong>
                        <span><?= e($emailAdmin) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <section class="page-card">
                <h2><?= $modoEdicao ? 'Editar Utilizador' : 'Criar Utilizador' ?></h2>
                <p>
                    <?= $modoEdicao
                        ? 'Atualize os dados do utilizador e altere o responsável do colaborador quando necessário.'
                        : 'Registe novos administradores, responsáveis e colaboradores no sistema.' ?>
                </p>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-error"><?= e($erro) ?></div>
                <?php endif; ?>

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-success"><?= e($mensagem) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="acao" value="<?= $modoEdicao ? 'editar' : 'criar' ?>">
                    <input type="hidden" name="utilizador_id" value="<?= e($valores['id']) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome</label>
                            <input type="text" name="nome" id="nome" value="<?= e($valores['nome']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" value="<?= e($valores['username']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" name="email" id="email" value="<?= e($valores['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="password"><?= $modoEdicao ? 'Nova palavra-passe' : 'Palavra-passe' ?></label>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                <?= $modoEdicao ? '' : 'required' ?>
                                placeholder="<?= $modoEdicao ? 'Deixe em branco para manter a atual' : '' ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="perfil">Perfil</label>
                            <select name="perfil" id="perfil" onchange="toggleResponsavelField()" required>
                                <option value="">Selecione um perfil</option>
                                <option value="admin" <?= $valores['perfil'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                <option value="responsavel" <?= $valores['perfil'] === 'responsavel' ? 'selected' : '' ?>>Responsável</option>
                                <option value="colaborador" <?= $valores['perfil'] === 'colaborador' ? 'selected' : '' ?>>Colaborador</option>
                            </select>
                        </div>

                        <div class="form-group" id="responsavel-wrap" style="display:none;">
                            <label for="responsavel_id">Responsável associado</label>
                            <select name="responsavel_id" id="responsavel_id">
                                <option value="">Selecione um responsável</option>
                                <?php foreach ($responsaveis as $responsavel): ?>
                                    <option
                                        value="<?= e((string) $responsavel['id']) ?>"
                                        <?= $valores['responsavel_id'] === (string) $responsavel['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($responsavel['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="full-width button-row">
                            <button type="submit" class="primary-btn">
                                <?= $modoEdicao ? 'Guardar alterações' : 'Criar utilizador' ?>
                            </button>

                            <?php if ($modoEdicao): ?>
                                <a href="register.php" class="secondary-btn inline-btn">Cancelar edição</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="secondary-btn inline-btn">Voltar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </section>

            <section class="page-card section-spacing">
                <h2>Lista de Utilizadores</h2>
                <p>Consulte todos os utilizadores do sistema, edite dados e teste a auditoria da hierarquia.</p>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Username</th>
                                <th>Perfil</th>
                                <th>Responsável atual</th>
                                <th>Estado</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilizadoresPagina as $utilizador): ?>
                                <tr>
                                    <td><?= e($utilizador['nome']) ?></td>
                                    <td><?= e($utilizador['username']) ?></td>
                                    <td><?= e(traduzirPerfil($utilizador['perfil'])) ?></td>
                                    <td><?= e($utilizador['nome_responsavel'] ?? '-') ?></td>
                                    <td>
                                        <span class="status-pill <?= (int) $utilizador['ativo'] === 1 ? 'aprovado' : 'cancelado' ?>">
                                            <?= (int) $utilizador['ativo'] === 1 ? 'Ativo' : 'Desativado' ?>
                                        </span>
                                    </td>
                                    <td><?= e(formatarDataInterface($utilizador['criado_em'])) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ((int) $utilizador['ativo'] === 1): ?>
                                                <a href="register.php?editar=<?= e((string) $utilizador['id']) ?>" class="small-action-link">
                                                    <i class="fa-solid fa-pen"></i>
                                                    <span>Editar</span>
                                                </a>

                                                <?php if ((int) $utilizador['id'] !== $adminId): ?>
                                                    <form method="POST" onsubmit="return confirm('Pretende desativar este utilizador?');">
                                                        <input type="hidden" name="acao" value="remover">
                                                        <input type="hidden" name="utilizador_id" value="<?= e((string) $utilizador['id']) ?>">
                                                        <button type="submit" class="small-action-btn danger-btn">
                                                            <i class="fa-solid fa-user-slash"></i>
                                                            <span>Remover</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="muted-text">Sem ações</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php if ($paginaAtual > 1): ?>
                            <a href="register.php?pagina=<?= $paginaAtual - 1 ?>" class="page-btn" title="Página anterior">&#8249;</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <a href="register.php?pagina=<?= $i ?>" class="page-btn <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="register.php?pagina=<?= $paginaAtual + 1 ?>" class="page-btn" title="Página seguinte">&#8250;</a>
                        <?php endif; ?>
                    </div>
                    <p class="pagination-info">A mostrar <?= count($utilizadoresPagina) ?> de <?= $totalUtilizadores ?> utilizadores — página <?= $paginaAtual ?> de <?= $totalPaginas ?></p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script>
    toggleResponsavelField();
</script>
</body>
</html>
