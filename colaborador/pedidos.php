<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

requireRole('colaborador');

$nome = $_SESSION['nome'] ?? 'Colaborador';
$email = $_SESSION['email'] ?? 'colaborador@ribas.pt';
$userId = getCurrentUserId();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'cancelar') {
        $pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        if ($pedidoId) {
            $resultado = cancelarPedidoColaborador($pdo, (int) $pedidoId, $userId);
            if ($resultado['sucesso']) {
                $mensagem = $resultado['mensagem'];
            } else {
                $erro = $resultado['mensagem'];
            }
        }
    } else {
        $resultado = criarPedidoFerias(
            $pdo,
            $userId,
            $_POST['data_inicio'] ?? '',
            $_POST['data_fim'] ?? '',
            $_POST['observacoes'] ?? null
        );

        if (($resultado['valido'] ?? false) === true) {
            $mensagem = $resultado['mensagem'];
            $_POST = [];
        } else {
            $erro = $resultado['mensagem'] ?? 'Não foi possível registar o pedido.';
        }
    }
}

$porPagina = 8;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$totalPedidos = contarPedidosColaborador($pdo, $userId);
$totalPaginas = max(1, (int) ceil($totalPedidos / $porPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

$relacaoAtual = obterRelacaoAtivaColaborador($pdo, $userId);
$pedidos = listarPedidosColaboradorPaginado($pdo, $userId, $paginaAtual, $porPagina);
$historicosPedidos = listarHistoricosPedidos($pedidos, $pdo);
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
    <title>Pedidos de Férias</title>
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
            <section class="page-card">
                <h2>Pedidos de Férias</h2>
                <p>Submeta novos pedidos de férias e acompanhe o histórico das suas solicitações.</p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-label">Total de pedidos</span>
                        <strong class="stat-value"><?= e((string) $resumo['total']) ?></strong>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pendentes</span>
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

                <div class="info-banner">
                    <?php if ($relacaoAtual !== null): ?>
                        Responsável atual: <strong><?= e($relacaoAtual['nome_responsavel']) ?></strong>
                    <?php else: ?>
                        Não existe um responsável ativo associado. Contacte a administração antes de submeter pedidos.
                    <?php endif; ?>
                </div>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-error"><?= e($erro) ?></div>
                <?php endif; ?>

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-success"><?= e($mensagem) ?></div>
                <?php endif; ?>

                <form method="POST" class="request-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data_inicio">Data de início</label>
                            <input type="date" name="data_inicio" id="data_inicio" value="<?= e($_POST['data_inicio'] ?? '') ?>" <?= $relacaoAtual === null ? 'disabled' : '' ?> required>
                        </div>

                        <div class="form-group">
                            <label for="data_fim">Data de fim</label>
                            <input type="date" name="data_fim" id="data_fim" value="<?= e($_POST['data_fim'] ?? '') ?>" <?= $relacaoAtual === null ? 'disabled' : '' ?> required>
                        </div>

                        <div class="form-group full-width">
                            <label for="observacoes">Observações</label>
                            <textarea name="observacoes" id="observacoes" rows="4" maxlength="500" placeholder="Indique informação adicional relevante para o responsável." <?= $relacaoAtual === null ? 'disabled' : '' ?>><?= e($_POST['observacoes'] ?? '') ?></textarea>
                        </div>

                        <div class="full-width button-row">
                            <button type="submit" class="primary-btn" <?= $relacaoAtual === null ? 'disabled' : '' ?>>Submeter pedido</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="page-card section-spacing">
                <h2>Histórico de Pedidos</h2>
                <p>Lista cronológica dos seus pedidos. Clique numa linha para ver os detalhes.</p>

                <?php if ($totalPedidos === 0): ?>
                    <div class="empty-state">Ainda não existem pedidos de férias registados.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table collapsible-table">
                            <thead>
                                <tr>
                                    <th class="col-toggle"></th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Dias úteis</th>
                                    <th>Responsável</th>
                                    <th>Estado</th>
                                    <th>Submetido em</th>
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
                                        <td><?= e(formatarDataInterface($pedido['data_inicio'])) ?></td>
                                        <td><?= e(formatarDataInterface($pedido['data_fim'])) ?></td>
                                        <td><?= e((string) $pedido['dias_uteis']) ?></td>
                                        <td><?= e($pedido['nome_responsavel']) ?></td>
                                        <td><span class="status-pill <?= strtolower($pedido['estado']) ?>"><?= e($pedido['estado']) ?></span></td>
                                        <td><?= e(formatarDataInterface($pedido['criado_em'])) ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <?php if ($pedido['estado'] === 'PENDENTE'): ?>
                                                <button type="button"
                                                    class="small-action-btn warning-btn"
                                                    data-pedido-id="<?= $pid ?>"
                                                    data-inicio="<?= e(formatarDataInterface($pedido['data_inicio'])) ?>"
                                                    data-fim="<?= e(formatarDataInterface($pedido['data_fim'])) ?>"
                                                    onclick="abrirModalCancelar(this)">
                                                    <i class="fa-solid fa-ban"></i> Cancelar
                                                </button>
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
                                                                        <?php if (!empty($h['nome_alterado_por'])): ?>
                                                                            <span>por <?= e($h['nome_alterado_por']) ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($h['observacoes'])): ?>
                                                                            <span><?= e($h['observacoes']) ?></span>
                                                                        <?php endif; ?>
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

<div class="modal-overlay" id="cancelarModal">
    <div class="modal-box">
        <div class="modal-title">Cancelar Pedido</div>
        <div class="modal-info" id="cancelarModalInfo"></div>
        <form method="POST" action="pedidos.php">
            <input type="hidden" name="acao" value="cancelar">
            <input type="hidden" name="pedido_id" id="cancelarPedidoId">
            <div class="modal-actions">
                <button type="button" class="modal-cancel-btn" onclick="fecharModalCancelar()">Voltar</button>
                <button type="submit" class="modal-confirm-btn rejeitar">Confirmar Cancelamento</button>
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

function abrirModalCancelar(btn) {
    document.getElementById('cancelarPedidoId').value = btn.dataset.pedidoId;
    document.getElementById('cancelarModalInfo').innerHTML =
        'Tem a certeza que deseja cancelar o pedido de <strong>' + btn.dataset.inicio + '</strong> a <strong>' + btn.dataset.fim + '</strong>?';
    document.getElementById('cancelarModal').classList.add('active');
}

function fecharModalCancelar() {
    document.getElementById('cancelarModal').classList.remove('active');
}

document.getElementById('cancelarModal').addEventListener('click', function (e) {
    if (e.target === this) fecharModalCancelar();
});
</script>

</body>
</html>
