<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

requireAnyRole(['responsavel', 'admin']);

$mensagemFeedback = null;
$tipoFeedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
    $acao = trim($_POST['acao'] ?? '');
    $justificacao = $_POST['justificacao'] ?? null;

    if ($pedidoId && in_array($acao, ['APROVADO', 'REJEITADO'], true)) {
        $resultado = processarDecisaoPedido($pdo, (int) $pedidoId, getCurrentUserId(), $acao, $justificacao);
        $_SESSION['feedback_mensagem'] = $resultado['mensagem'];
        $_SESSION['feedback_tipo'] = $resultado['sucesso'] ? 'success' : 'error';
    }

    header('Location: pedidos.php');
    exit;
}

$mensagemFeedback = $_SESSION['feedback_mensagem'] ?? null;
$tipoFeedback = $_SESSION['feedback_tipo'] ?? null;
unset($_SESSION['feedback_mensagem'], $_SESSION['feedback_tipo']);

$nome = $_SESSION['nome'] ?? 'Responsável';
$email = $_SESSION['email'] ?? 'responsavel@ribas.pt';
$responsavelId = getCurrentUserId();

$porPagina = 8;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$totalPedidos = contarPedidosEquipa($pdo, $responsavelId);
$totalPaginas = max(1, (int) ceil($totalPedidos / $porPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$pedidos = listarPedidosEquipaPaginado($pdo, $responsavelId, $paginaAtual, $porPagina);
$historicosPedidos = listarHistoricosPedidos($pedidos, $pdo);
$resumo = obterResumoPedidosResponsavel($pdo, $responsavelId);
$notifNaoLidas = contarNotificacoesNaoLidas($pdo, $responsavelId);
$notificacoes = listarNotificacoesRecentes($pdo, $responsavelId);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos da Equipa</title>
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

            <a href="pedidos.php" class="sidebar-link active">
                <span class="sidebar-icon"><i class="fa-regular fa-calendar-days"></i></span>
                <span>Pedidos da Equipa</span>
                <?php if (($resumo['pendentes'] ?? 0) > 0): ?>
                    <span class="sidebar-badge"><?= e((string) $resumo['pendentes']) ?></span>
                <?php endif; ?>
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
                        <strong><?= $_SESSION['perfil'] === 'admin' ? 'Administrador' : 'Responsável' ?></strong>
                        <span><?= e($email) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <section class="page-card">
                <h2>Pedidos da Equipa</h2>
                <p>Consulte e decida sobre os pedidos de férias dos colaboradores da sua equipa.</p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Elementos ativos</span>
                        <strong class="stat-value"><?= e((string) $resumo['elementos_equipa']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pedidos pendentes</span>
                        <strong class="stat-value"><?= e((string) $resumo['pendentes']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pedidos no total</span>
                        <strong class="stat-value"><?= e((string) $resumo['total']) ?></strong>
                    </div>
                </div>
            </section>

            <?php if ($mensagemFeedback !== null): ?>
                <div class="alert alert-<?= $tipoFeedback === 'success' ? 'success' : 'error' ?> section-spacing">
                    <?= e($mensagemFeedback) ?>
                </div>
            <?php endif; ?>

            <section class="page-card section-spacing">
                <h2>Lista de Pedidos</h2>
                <p>Os pedidos pendentes podem ser aprovados ou rejeitados diretamente nesta página.</p>

                <?php if ($totalPedidos === 0): ?>
                    <div class="empty-state">Ainda não existem pedidos de férias para os colaboradores da sua equipa.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table collapsible-table">
                            <thead>
                                <tr>
                                    <th class="col-toggle"></th>
                                    <th>Colaborador</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Dias úteis</th>
                                    <th>Estado</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php
                                        $pid = (int) $pedido['id'];
                                        $temDetalhes = !empty($pedido['observacoes']) || !empty($pedido['justificacao_decisao']) || !empty($historicosPedidos[$pid]);
                                    ?>
                                    <tr class="row-main <?= $temDetalhes ? 'row-togglable' : '' ?>"
                                        <?= $temDetalhes ? 'onclick="toggleDetalhes(' . $pid . ')"' : '' ?>>
                                        <td class="row-toggle-icon">
                                            <?php if ($temDetalhes): ?>
                                                <i class="fa-solid fa-chevron-right toggle-icon" id="icon-<?= $pid ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($pedido['nome_colaborador']) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_inicio'])) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_fim'])) ?></td>
                                        <td><?= e((string) $pedido['dias_uteis']) ?></td>
                                        <td><span class="status-pill <?= strtolower($pedido['estado']) ?>"><?= e($pedido['estado']) ?></span></td>
                                        <td><?= e(formatarDataInterface($pedido['criado_em'])) ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <?php if ($pedido['estado'] === 'PENDENTE'): ?>
                                                <div class="table-actions">
                                                    <button type="button"
                                                        class="small-action-btn success-btn"
                                                        data-acao="APROVADO"
                                                        data-pedido-id="<?= $pid ?>"
                                                        data-colaborador="<?= e($pedido['nome_colaborador']) ?>"
                                                        data-inicio="<?= e(formatarDataInterface($pedido['data_inicio'])) ?>"
                                                        data-fim="<?= e(formatarDataInterface($pedido['data_fim'])) ?>"
                                                        onclick="abrirModal(this)">
                                                        <i class="fa-solid fa-check"></i> Aprovar
                                                    </button>
                                                    <button type="button"
                                                        class="small-action-btn danger-btn"
                                                        data-acao="REJEITADO"
                                                        data-pedido-id="<?= $pid ?>"
                                                        data-colaborador="<?= e($pedido['nome_colaborador']) ?>"
                                                        data-inicio="<?= e(formatarDataInterface($pedido['data_inicio'])) ?>"
                                                        data-fim="<?= e(formatarDataInterface($pedido['data_fim'])) ?>"
                                                        onclick="abrirModal(this)">
                                                        <i class="fa-solid fa-xmark"></i> Rejeitar
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="muted-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($temDetalhes): ?>
                                        <tr class="row-details" id="details-<?= $pid ?>">
                                            <td colspan="8">
                                                <div class="row-details-inner">
                                                    <?php if (!empty($pedido['observacoes'])): ?>
                                                        <div class="detail-block">
                                                            <strong>Observações:</strong>
                                                            <span><?= e($pedido['observacoes']) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($pedido['justificacao_decisao'])): ?>
                                                        <div class="detail-block">
                                                            <strong>Justificação da decisão:</strong>
                                                            <span><?= e($pedido['justificacao_decisao']) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($historicosPedidos[$pid])): ?>
                                                        <div class="detail-block">
                                                            <strong>Histórico:</strong>
                                                            <div class="history-list">
                                                                <?php foreach ($historicosPedidos[$pid] as $h): ?>
                                                                    <div class="history-item">
                                                                        <span class="history-status"><?= e($h['estado_novo']) ?></span>
                                                                        <span><?= e(formatarDataHoraInterface($h['criado_em'])) ?></span>
                                                                        <?php if (!empty($h['nome_alterado_por'])): ?><span>por <?= e($h['nome_alterado_por']) ?></span><?php endif; ?>
                                                                        <?php if (!empty($h['observacoes'])): ?><span><?= e($h['observacoes']) ?></span><?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php if ($paginaAtual > 1): ?>
                                <a href="?pagina=<?= $paginaAtual - 1 ?>" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <a href="?pagina=<?= $i ?>" class="page-btn <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <a href="?pagina=<?= $paginaAtual + 1 ?>" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<div class="modal-overlay" id="decisaoModal">
    <div class="modal-box">
        <div class="modal-title" id="modalTitulo">Aprovar Pedido</div>
        <div class="modal-info" id="modalInfo"></div>
        <form method="POST" action="pedidos.php">
            <input type="hidden" name="pedido_id" id="modalPedidoId">
            <input type="hidden" name="acao" id="modalAcao">
            <label class="modal-label" id="modalJustLabel" for="justificacao">Justificação (opcional):</label>
            <textarea class="modal-textarea" name="justificacao" id="justificacao" placeholder="Escreva aqui a justificação da decisão..."></textarea>
            <div class="modal-actions">
                <button type="button" class="modal-cancel-btn" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="modal-confirm-btn aprovar" id="modalBtnConfirmar">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDetalhes(id) {
    var row = document.getElementById('details-' + id);
    var icon = document.getElementById('icon-' + id);
    if (!row) return;
    var aberto = row.classList.toggle('open');
    if (icon) icon.classList.toggle('rotated', aberto);
}

function abrirModal(btn) {
    const acao = btn.dataset.acao;
    const pedidoId = btn.dataset.pedidoId;
    const colaborador = btn.dataset.colaborador;
    const inicio = btn.dataset.inicio;
    const fim = btn.dataset.fim;

    document.getElementById('modalPedidoId').value = pedidoId;
    document.getElementById('modalAcao').value = acao;
    document.getElementById('modalInfo').innerHTML =
        '<strong>Colaborador:</strong> ' + colaborador +
        '<br><strong>Período:</strong> ' + inicio + ' → ' + fim;

    const btnConfirmar = document.getElementById('modalBtnConfirmar');

    if (acao === 'APROVADO') {
        document.getElementById('modalTitulo').textContent = 'Aprovar Pedido';
        document.getElementById('modalJustLabel').textContent = 'Justificação (opcional):';
        btnConfirmar.className = 'modal-confirm-btn aprovar';
        btnConfirmar.textContent = 'Aprovar';
    } else {
        document.getElementById('modalTitulo').textContent = 'Rejeitar Pedido';
        document.getElementById('modalJustLabel').textContent = 'Justificação da rejeição (opcional):';
        btnConfirmar.className = 'modal-confirm-btn rejeitar';
        btnConfirmar.textContent = 'Rejeitar';
    }

    document.getElementById('decisaoModal').classList.add('active');
}

function fecharModal() {
    document.getElementById('decisaoModal').classList.remove('active');
    document.getElementById('justificacao').value = '';
}

document.getElementById('decisaoModal').addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
});
</script>

</body>
</html>
