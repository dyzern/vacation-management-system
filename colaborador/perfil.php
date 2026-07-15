<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

requireRole('colaborador');

$userId = getCurrentUserId();
$nome = $_SESSION['nome'] ?? 'Colaborador';
$email = $_SESSION['email'] ?? '';

$utilizador = obterDadosColaborador($pdo, $userId);
$relacaoAtual = obterRelacaoAtivaColaborador($pdo, $userId);
$resumo = obterResumoPedidosColaborador($pdo, $userId);
$saldo = obterSaldoFerias($pdo, $userId, (int) date('Y'));
$notifNaoLidas = contarNotificacoesNaoLidas($pdo, $userId);
$notificacoes = listarNotificacoesRecentes($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área Pessoal</title>
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
            <a href="dashboard.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-solid fa-house"></i></span>
                <span>Dashboard</span>
            </a>

            <a href="pedidos.php" class="sidebar-link">
                <span class="sidebar-icon"><i class="fa-regular fa-calendar-days"></i></span>
                <span>Os Meus Pedidos</span>
            </a>

            <a href="perfil.php" class="sidebar-link active">
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
            <section class="page-card">
                <div class="perfil-header">
                    <div class="perfil-avatar"><?= e(getUserInitials($utilizador['nome'] ?? $nome)) ?></div>
                    <div class="perfil-header-info">
                        <h2><?= e($utilizador['nome'] ?? $nome) ?></h2>
                        <span class="perfil-role-badge"><?= e(traduzirPerfil($utilizador['perfil'] ?? 'colaborador')) ?></span>
                    </div>
                </div>
            </section>

            <section class="page-card section-spacing">
                <h2>Dados Pessoais</h2>
                <p>Informação da sua conta no sistema.</p>

                <div class="info-grid perfil-info-grid">
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-user"></i> Nome</span>
                        <strong><?= e($utilizador['nome'] ?? '-') ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-at"></i> Username</span>
                        <strong><?= e($utilizador['username'] ?? '-') ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-envelope"></i> Email</span>
                        <strong><?= e($utilizador['email'] ?? '-') ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-id-badge"></i> Perfil</span>
                        <strong><?= e(traduzirPerfil($utilizador['perfil'] ?? 'colaborador')) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-calendar-plus"></i> Membro desde</span>
                        <strong><?= e(formatarDataInterface($utilizador['criado_em'] ?? null)) ?></strong>
                    </div>
                    <div class="info-tile">
                        <span class="info-label"><i class="fa-solid fa-circle-check"></i> Estado da conta</span>
                        <strong><?= ((int) ($utilizador['ativo'] ?? 0)) === 1 ? 'Ativa' : 'Inativa' ?></strong>
                    </div>
                </div>
            </section>

            <section class="page-card section-spacing">
                <h2>Responsável Associado</h2>
                <p>Responsável atual pela aprovação dos seus pedidos de férias.</p>

                <?php if ($relacaoAtual !== null): ?>
                    <div class="info-grid perfil-info-grid">
                        <div class="info-tile">
                            <span class="info-label"><i class="fa-solid fa-user-tie"></i> Nome</span>
                            <strong><?= e($relacaoAtual['nome_responsavel']) ?></strong>
                        </div>
                        <div class="info-tile">
                            <span class="info-label"><i class="fa-solid fa-at"></i> Username</span>
                            <strong><?= e($relacaoAtual['username_responsavel']) ?></strong>
                        </div>
                        <div class="info-tile">
                            <span class="info-label"><i class="fa-solid fa-envelope"></i> Email</span>
                            <strong><?= e($relacaoAtual['email_responsavel']) ?></strong>
                        </div>
                        <div class="info-tile">
                            <span class="info-label"><i class="fa-solid fa-calendar-day"></i> Associado desde</span>
                            <strong><?= e(formatarDataInterface($relacaoAtual['data_inicio'])) ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        Não existe um responsável ativo associado à sua conta. Contacte a administração.
                    </div>
                <?php endif; ?>
            </section>

            <section class="page-card section-spacing">
                <h2>Resumo de Férias</h2>
                <p>Estado atual dos seus pedidos de férias.</p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Total de pedidos</span>
                        <strong class="stat-value"><?= e((string) $resumo['total']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pedidos pendentes</span>
                        <strong class="stat-value"><?= e((string) $resumo['pendentes']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Dias aprovados</span>
                        <strong class="stat-value"><?= e((string) $resumo['dias_aprovados']) ?></strong>
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

                <div class="perfil-actions">
                    <a href="pedidos.php" class="primary-btn">
                        <i class="fa-regular fa-calendar-days"></i> Ver todos os pedidos
                    </a>
                </div>
            </section>
        </main>
    </div>
</div>

</body>
</html>
