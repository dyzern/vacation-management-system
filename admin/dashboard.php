<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';
requireRole('admin');

$nome = $_SESSION['nome'] ?? 'Administrador';
$email = $_SESSION['email'] ?? 'admin@ribas.pt';

$resumo = obterResumoAdmin($pdo);
$resumoEquipaAdmin = obterResumoPedidosResponsavel($pdo, getCurrentUserId());
$anoAtual = (int) date('Y');
$notifNaoLidas = contarNotificacoesNaoLidas($pdo, getCurrentUserId());
$notificacoes = listarNotificacoesRecentes($pdo, getCurrentUserId());

$proximasFerias = array_map(
    static fn(array $pedido): array => [
        'cor' => $pedido['estado'] === 'PENDENTE' ? 'orange' : 'green',
        'inicio' => formatarDataInterface($pedido['data_inicio']),
        'fim' => formatarDataInterface($pedido['data_fim']),
        'nome' => $pedido['nome_colaborador'],
    ],
    listarPedidosProximosSistema($pdo, 5)
);

$ausencias = array_map(
    static fn(array $pedido): array => [
        'data' => strtoupper(substr(formatarDataInterface($pedido['data_inicio']), 0, 5)),
        'iniciais' => getUserInitials($pedido['nome_colaborador']),
        'nome' => $pedido['nome_colaborador'],
        'descricao' => 'Férias (' . $pedido['dias_uteis'] . ' dias)',
        'estado' => $pedido['estado'] === 'APROVADO' ? 'Aprovada' : 'Pendente',
    ],
    listarPedidosRecentesSistema($pdo, 5)
);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-page">

<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="sidebar-top">
            <img src="../assets/img/ribas-logo.png" alt="Ribas" class="sidebar-logo">
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>
                <span>Dashboard</span>
            </a>

            <a href="pedidos.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-regular fa-calendar-days"></i></span>
                <span>Todos os Pedidos</span>
                <?php if (($resumo['pedidos_pendentes'] ?? 0) > 0): ?>
                    <span class="sidebar-badge"><?= e((string) $resumo['pedidos_pendentes']) ?></span>
                <?php endif; ?>
            </a>

            <a href="register.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-solid fa-users"></i></span>
                <span>Gestão de Utilizadores</span>
            </a>

            <a href="perfil.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-solid fa-user"></i></span>
                <span>Área Pessoal</span>
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
                <?php include '../includes/notif_bell.php'; ?>

                <div class="admin-userbox">
                    <div class="admin-avatar"><?= e(getUserInitials($nome)) ?></div>
                    <div class="admin-userinfo">
                        <strong>Administrador</strong>
                        <span><?= e($email) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <section class="welcome-block">
                <h1>Bem-vindo, Administrador!</h1>
                <p>Aqui fica o resumo do sistema de controlo de férias.</p>
            </section>

            <section class="summary-grid">
                <div class="summary-card">
                    <h3>Utilizadores Ativos</h3>
                    <div class="summary-big"><?= e((string) $resumo['total_ativos']) ?></div>
                    <p>Ano <?= e((string) $anoAtual) ?></p>
                </div>

                <div class="summary-card">
                    <div class="summary-title-icon">
                        <i class="fa-regular fa-calendar"></i>
                        <h3>Próximas Férias</h3>
                    </div>

                    <div class="next-list">
                        <?php if ($proximasFerias === []): ?>
                            <p>Não existem férias futuras pendentes ou aprovadas.</p>
                        <?php else: ?>
                            <?php foreach ($proximasFerias as $item): ?>
                                <div class="next-item">
                                    <div class="next-left">
                                        <i class="fa-solid fa-calendar-day <?= e($item['cor']) ?>"></i>
                                        <span><?= e($item['inicio']) ?> a <?= e($item['fim']) ?></span>
                                    </div>
                                    <span class="next-tag"><?= e($item['nome']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <a href="pedidos.php" class="admin-link">Abrir pedidos da equipa <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </section>

            <section class="page-card">
                <h2>Indicadores do Sistema</h2>
                <div class="info-grid">
                    <div class="info-tile">
                        <span class="info-label">Admins ativos</span>
                        <strong><?= e((string) $resumo['admins_ativos']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Responsáveis ativos</span>
                        <strong><?= e((string) $resumo['responsaveis_ativos']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Colaboradores ativos</span>
                        <strong><?= e((string) $resumo['colaboradores_ativos']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Pedidos pendentes</span>
                        <strong><?= e((string) $resumo['pedidos_pendentes']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Pedidos totais</span>
                        <strong><?= e((string) $resumo['total_pedidos']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Dias aprovados</span>
                        <strong><?= e((string) $resumo['dias_aprovados']) ?></strong>
                    </div>
                </div>
            </section>

            <section class="calendar-card">
                <div class="calendar-header">
                    <h2>Atividade Recente</h2>
                </div>

                <div class="absence-list">
                    <?php if ($ausencias === []): ?>
                        <div class="empty-state">Ainda não existem pedidos recentes registados.</div>
                    <?php else: ?>
                        <?php foreach ($ausencias as $item): ?>
                            <div class="absence-item">
                                <div class="absence-date"><?= e($item['data']) ?></div>

                                <div class="absence-person">
                                    <div class="person-circle initials"><?= e($item['iniciais']) ?></div>
                                    <span class="person-name"><?= e($item['nome']) ?></span>
                                </div>

                                <div class="absence-separator">-</div>

                                <div class="absence-desc"><?= e($item['descricao']) ?></div>

                                <div class="absence-status">
                                    <span class="status-pill <?= strtolower($item['estado']) === 'aprovada' ? 'approved' : 'pending' ?>">
                                        <?= e($item['estado']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>


        </main>
    </div>
</div>

</body>
</html>
