<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

requireRole('colaborador');

$nome = $_SESSION['nome'] ?? 'Colaborador';
$email = $_SESSION['email'] ?? 'colaborador@ribas.pt';
$userId = getCurrentUserId();
$resumo = obterResumoPedidosColaborador($pdo, $userId);
$saldo = obterSaldoFerias($pdo, $userId, (int) date('Y'));
$relacaoAtual = obterRelacaoAtivaColaborador($pdo, $userId);
$proximoPedido = obterProximoPedidoColaborador($pdo, $userId);
$pedidos = listarPedidosColaborador($pdo, $userId);
$ultimosPedidos = array_slice($pedidos, 0, 3);
$notifNaoLidas = contarNotificacoesNaoLidas($pdo, $userId);
$notificacoes = listarNotificacoesRecentes($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Colaborador</title>
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
                <span>Os Meus Pedidos</span>
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
                        <strong>Colaborador</strong>
                        <span><?= e($email) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <section class="welcome-block">
                <h1>Bem-vindo, <?= e($nome) ?></h1>
                <p>Resumo atual dos seus pedidos de férias e da relação com o responsável associado.</p>
            </section>

            <section class="summary-grid">
                <div class="summary-card">
                    <h3>Pedidos Registados</h3>
                    <div class="summary-big"><?= e((string) $resumo['total']) ?></div>
                    <p><?= e((string) $resumo['pendentes']) ?> pendentes neste momento</p>
                </div>

                <div class="summary-card">
                    <div class="summary-title-icon">
                        <i class="fa-regular fa-calendar-check"></i>
                        <h3>Próximo Período</h3>
                    </div>

                    <?php if ($proximoPedido !== null): ?>
                        <div class="next-list">
                            <div class="next-item">
                                <div class="next-left">
                                    <i class="fa-solid fa-calendar-day blue"></i>
                                    <span><?= e(formatarDataInterface($proximoPedido['data_inicio'])) ?> a <?= e(formatarDataInterface($proximoPedido['data_fim'])) ?></span>
                                </div>
                                <span class="next-tag"><?= e($proximoPedido['estado']) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Não existem pedidos futuros pendentes ou aprovados.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="page-card">
                <h2>Informação do Colaborador</h2>
                <p>Dados atuais da sua área pessoal.</p>

                <div class="info-grid">
                    <div class="info-tile">
                        <span class="info-label">Responsável atual</span>
                        <strong><?= e($relacaoAtual['nome_responsavel'] ?? 'Não associado') ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Dias aprovados</span>
                        <strong><?= e((string) $resumo['dias_aprovados']) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label">Acesso rápido</span>
                        <strong><a href="pedidos.php" class="admin-link">Abrir pedidos de férias</a></strong>
                    </div>
                </div>

                <div class="saldo-ferias-bar">
                    <div class="saldo-ferias-titulo">
                        <i class="fa-solid fa-calendar-check"></i>
                        <strong>Banco de Férias <?= date('Y') ?></strong>
                    </div>
                    <div class="saldo-ferias-nums">
                        <span class="saldo-item disponivel">
                            <span class="saldo-valor"><?= e((string) $saldo['disponiveis']) ?></span>
                            <span class="saldo-label">disponíveis</span>
                        </span>
                        <span class="saldo-sep">/</span>
                        <span class="saldo-item total">
                            <span class="saldo-valor"><?= e((string) $saldo['total']) ?></span>
                            <span class="saldo-label">total de dias</span>
                        </span>
                        <span class="saldo-item usado">
                            <span class="saldo-valor"><?= e((string) $saldo['usados']) ?></span>
                            <span class="saldo-label">utilizados</span>
                        </span>
                    </div>
                    <div class="saldo-barra-wrap">
                        <div class="saldo-barra-fill" style="width: <?= $saldo['total'] > 0 ? round(($saldo['usados'] / $saldo['total']) * 100) : 0 ?>%"></div>
                    </div>
                </div>
            </section>

            <section class="page-card section-spacing">
                <h2>Últimos Pedidos</h2>
                <p>Visualização rápida dos pedidos mais recentes submetidos por si.</p>

                <?php if ($ultimosPedidos === []): ?>
                    <div class="empty-state">Ainda não existem pedidos de férias registados.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Dias úteis</th>
                                    <th>Estado</th>
                                    <th>Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimosPedidos as $pedido): ?>
                                    <tr>
                                        <td><?= e(formatarDataInterface($pedido['data_inicio'])) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_fim'])) ?></td>
                                        <td><?= e((string) $pedido['dias_uteis']) ?></td>
                                        <td><span class="status-pill <?= strtolower($pedido['estado']) ?>"><?= e($pedido['estado']) ?></span></td>
                                        <td><?= e(formatarDataInterface($pedido['criado_em'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

</body>
</html>
