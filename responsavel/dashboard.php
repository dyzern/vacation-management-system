<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';
requireRole('responsavel');

$nome = $_SESSION['nome'] ?? 'Responsável';
$email = $_SESSION['email'] ?? 'responsavel@ribas.pt';
$resumo = obterResumoPedidosResponsavel($pdo, getCurrentUserId());
$pedidos = listarPedidosEquipa($pdo, getCurrentUserId());
$pedidosPendentes = array_values(array_filter($pedidos, static fn(array $pedido): bool => $pedido['estado'] === 'PENDENTE'));
$ultimosPedidos = array_slice($pedidos, 0, 5);
$notifNaoLidas = contarNotificacoesNaoLidas($pdo, getCurrentUserId());
$notificacoes = listarNotificacoesRecentes($pdo, getCurrentUserId());
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Responsável</title>
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
                <span>Pedidos da Equipa</span>
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
                        <strong>Responsável</strong>
                        <span><?= e($email) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <div class="page-card">
                <h2>Bem-vindo, <?= e($nome) ?></h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Elementos da equipa</span>
                        <strong class="stat-value"><?= e((string) $resumo['elementos_equipa']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pedidos pendentes</span>
                        <strong class="stat-value"><?= e((string) $resumo['pendentes']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pedidos registados</span>
                        <strong class="stat-value"><?= e((string) $resumo['total']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="page-card section-spacing">
                <h2>Pedidos Pendentes</h2>
                <p>Resumo dos pedidos que aguardam tratamento na sua equipa.</p>

                <?php if ($pedidosPendentes === []): ?>
                    <div class="empty-state">Não existem pedidos pendentes neste momento.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Dias úteis</th>
                                    <th>Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pedidosPendentes, 0, 5) as $pedido): ?>
                                    <tr>
                                        <td><?= e($pedido['nome_colaborador']) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_inicio'])) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_fim'])) ?></td>
                                        <td><?= e((string) $pedido['dias_uteis']) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['criado_em'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="page-card section-spacing">
                <h2>Atividade Recente</h2>
                <p>Pedidos mais recentes ligados à sua equipa.</p>

                <?php if ($ultimosPedidos === []): ?>
                    <div class="empty-state">Ainda não existem pedidos registados para a sua equipa.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimosPedidos as $pedido): ?>
                                    <tr>
                                        <td><?= e($pedido['nome_colaborador']) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_inicio'])) ?> a <?= e(formatarDataInterface($pedido['data_fim'])) ?></td>
                                        <td><span class="status-pill <?= strtolower($pedido['estado']) ?>"><?= e($pedido['estado']) ?></span></td>
                                        <td><?= e(formatarDataInterface($pedido['criado_em'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

</body>
</html>
