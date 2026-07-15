<?php

require_once __DIR__ . '/functions.php';

function listarResponsaveisAtivos(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, nome
         FROM utilizadores
         WHERE perfil IN ('admin', 'responsavel')
           AND ativo = 1
         ORDER BY nome"
    );

    return $stmt->fetchAll();
}

function obterRelacaoAtivaColaborador(PDO $pdo, int $colaboradorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            rc.id,
            rc.colaborador_id,
            rc.responsavel_id,
            rc.data_inicio,
            u.nome AS nome_responsavel,
            u.email AS email_responsavel,
            u.username AS username_responsavel
         FROM responsaveis_colaboradores rc
         INNER JOIN utilizadores u ON u.id = rc.responsavel_id
         WHERE rc.colaborador_id = :colaborador_id
           AND rc.ativo = 1
         LIMIT 1"
    );
    $stmt->execute(['colaborador_id' => $colaboradorId]);

    $relacao = $stmt->fetch();

    return $relacao ?: null;
}

function criarUtilizador(PDO $pdo, array $dados, int $criadoPor): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO utilizadores (nome, username, email, password, perfil, ativo)
             VALUES (:nome, :username, :email, :password, :perfil, 1)"
        );
        $stmt->execute([
            'nome' => $dados['nome'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'password' => $dados['password'],
            'perfil' => $dados['perfil'],
        ]);

        $novoUtilizadorId = (int) $pdo->lastInsertId();

        if ($dados['perfil'] === 'colaborador' && $dados['responsavel_id'] !== null) {
            $stmtRelacao = $pdo->prepare(
                "INSERT INTO responsaveis_colaboradores (
                    colaborador_id,
                    responsavel_id,
                    ativo,
                    data_inicio,
                    criado_por
                 ) VALUES (
                    :colaborador_id,
                    :responsavel_id,
                    1,
                    CURDATE(),
                    :criado_por
                 )"
            );
            $stmtRelacao->execute([
                'colaborador_id' => $novoUtilizadorId,
                'responsavel_id' => $dados['responsavel_id'],
                'criado_por' => $criadoPor,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

function listarUtilizadoresComRelacao(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            u.id,
            u.nome,
            u.username,
            u.email,
            u.perfil,
            u.ativo,
            u.criado_em,
            rc.responsavel_id,
            r.nome AS nome_responsavel
         FROM utilizadores u
         LEFT JOIN responsaveis_colaboradores rc
            ON rc.colaborador_id = u.id
           AND rc.ativo = 1
         LEFT JOIN utilizadores r
            ON r.id = rc.responsavel_id
         ORDER BY
            u.ativo DESC,
            FIELD(u.perfil, 'admin', 'responsavel', 'colaborador'),
            u.nome ASC"
    );

    return $stmt->fetchAll();
}

function obterUtilizadorPorId(PDO $pdo, int $utilizadorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT
            u.id,
            u.nome,
            u.username,
            u.email,
            u.perfil,
            u.ativo,
            rc.responsavel_id
         FROM utilizadores u
         LEFT JOIN responsaveis_colaboradores rc
            ON rc.colaborador_id = u.id
           AND rc.ativo = 1
         WHERE u.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $utilizadorId]);

    $utilizador = $stmt->fetch();

    return $utilizador ?: null;
}

function atualizarUtilizador(PDO $pdo, int $utilizadorId, array $dados, int $alteradoPor): void
{
    $utilizadorAtual = obterUtilizadorPorId($pdo, $utilizadorId);

    if ($utilizadorAtual === null) {
        throw new RuntimeException('Utilizador não encontrado.');
    }

    if ((int) $utilizadorAtual['ativo'] !== 1) {
        throw new RuntimeException('Só é possível editar utilizadores ativos.');
    }

    $pdo->beginTransaction();

    try {
        $campos = [
            'nome = :nome',
            'username = :username',
            'email = :email',
            'perfil = :perfil',
        ];

        $params = [
            'id' => $utilizadorId,
            'nome' => $dados['nome'],
            'username' => $dados['username'],
            'email' => $dados['email'],
            'perfil' => $dados['perfil'],
        ];

        if (!empty($dados['password'])) {
            $campos[] = 'password = :password';
            $params['password'] = $dados['password'];
        }

        $stmt = $pdo->prepare(
            "UPDATE utilizadores
             SET " . implode(', ', $campos) . "
             WHERE id = :id"
        );
        $stmt->execute($params);

        if ($dados['perfil'] === 'colaborador') {
            if ($dados['responsavel_id'] === null) {
                throw new RuntimeException('Um colaborador tem de ficar associado a um responsável.');
            }

            $relacaoAtual = obterRelacaoAtivaColaborador($pdo, $utilizadorId);

            if ($relacaoAtual === null) {
                $stmtRelacao = $pdo->prepare(
                    "INSERT INTO responsaveis_colaboradores (
                        colaborador_id,
                        responsavel_id,
                        ativo,
                        data_inicio,
                        criado_por
                     ) VALUES (
                        :colaborador_id,
                        :responsavel_id,
                        1,
                        CURDATE(),
                        :criado_por
                     )"
                );
                $stmtRelacao->execute([
                    'colaborador_id' => $utilizadorId,
                    'responsavel_id' => $dados['responsavel_id'],
                    'criado_por' => $alteradoPor,
                ]);
            } elseif ((int) $relacaoAtual['responsavel_id'] !== (int) $dados['responsavel_id']) {
                $stmtRelacao = $pdo->prepare(
                    "UPDATE responsaveis_colaboradores
                     SET responsavel_id = :responsavel_id,
                         data_inicio = CURDATE(),
                         criado_por = :criado_por
                     WHERE id = :id"
                );
                $stmtRelacao->execute([
                    'responsavel_id' => $dados['responsavel_id'],
                    'criado_por' => $alteradoPor,
                    'id' => $relacaoAtual['id'],
                ]);
            }
        } else {
            $relacaoAtual = obterRelacaoAtivaColaborador($pdo, $utilizadorId);

            if ($relacaoAtual !== null) {
                $stmtRelacao = $pdo->prepare(
                    "UPDATE responsaveis_colaboradores
                     SET ativo = 0,
                         criado_por = :criado_por
                     WHERE id = :id"
                );
                $stmtRelacao->execute([
                    'criado_por' => $alteradoPor,
                    'id' => $relacaoAtual['id'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

function desativarUtilizador(PDO $pdo, int $utilizadorId, int $alteradoPor): void
{
    $utilizador = obterUtilizadorPorId($pdo, $utilizadorId);

    if ($utilizador === null) {
        throw new RuntimeException('Utilizador não encontrado.');
    }

    if ((int) $utilizador['ativo'] !== 1) {
        throw new RuntimeException('O utilizador já se encontra desativado.');
    }

    if ($utilizador['perfil'] !== 'colaborador') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM responsaveis_colaboradores
             WHERE responsavel_id = :responsavel_id
               AND ativo = 1"
        );
        $stmt->execute(['responsavel_id' => $utilizadorId]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Não é possível desativar este utilizador enquanto existirem colaboradores ativos associados.'
            );
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE utilizadores
         SET ativo = 0
         WHERE id = :id"
    );
    $stmt->execute(['id' => $utilizadorId]);
}

function normalizarDataPedido(string $data): ?DateTimeImmutable
{
    $data = trim($data);

    if ($data === '') {
        return null;
    }

    $objeto = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    $erros = DateTimeImmutable::getLastErrors();

    if (
        !$objeto ||
        ($erros !== false && (($erros['warning_count'] ?? 0) > 0 || ($erros['error_count'] ?? 0) > 0))
    ) {
        return null;
    }

    return $objeto;
}

function calcularDiasUteisFerias(DateTimeImmutable $inicio, DateTimeImmutable $fim): int
{
    $dias = 0;
    $cursor = $inicio;

    while ($cursor <= $fim) {
        if ((int) $cursor->format('N') < 6) {
            $dias++;
        }

        $cursor = $cursor->modify('+1 day');
    }

    return $dias;
}

function validarPedidoFerias(PDO $pdo, int $colaboradorId, string $dataInicio, string $dataFim): array
{
    $inicio = normalizarDataPedido($dataInicio);
    $fim = normalizarDataPedido($dataFim);

    if ($inicio === null || $fim === null) {
        return [
            'valido' => false,
            'mensagem' => 'Indique datas válidas para o pedido.',
        ];
    }

    $hoje = new DateTimeImmutable('today');

    if ($inicio < $hoje) {
        return [
            'valido' => false,
            'mensagem' => 'A data de início não pode ser anterior à data atual.',
        ];
    }

    if ($fim < $inicio) {
        return [
            'valido' => false,
            'mensagem' => 'A data de fim tem de ser igual ou posterior à data de início.',
        ];
    }

    $diasUteis = calcularDiasUteisFerias($inicio, $fim);

    if ($diasUteis <= 0) {
        return [
            'valido' => false,
            'mensagem' => 'O período selecionado não contém dias úteis.',
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM pedidos_ferias
         WHERE colaborador_id = :colaborador_id
           AND estado IN ('PENDENTE', 'APROVADO')
           AND data_inicio <= :data_fim
           AND data_fim >= :data_inicio"
    );
    $stmt->execute([
        'colaborador_id' => $colaboradorId,
        'data_inicio' => $inicio->format('Y-m-d'),
        'data_fim' => $fim->format('Y-m-d'),
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        return [
            'valido' => false,
            'mensagem' => 'Já existe um pedido pendente ou aprovado para esse intervalo.',
        ];
    }

    return [
        'valido' => true,
        'inicio' => $inicio,
        'fim' => $fim,
        'dias_uteis' => $diasUteis,
    ];
}

function criarPedidoFerias(PDO $pdo, int $colaboradorId, string $dataInicio, string $dataFim, ?string $observacoes): array
{
    $validacao = validarPedidoFerias($pdo, $colaboradorId, $dataInicio, $dataFim);

    if (($validacao['valido'] ?? false) !== true) {
        return $validacao;
    }

    $relacao = obterRelacaoAtivaColaborador($pdo, $colaboradorId);

    if ($relacao === null) {
        return [
            'valido' => false,
            'mensagem' => 'Não existe um responsável ativo associado ao colaborador.',
        ];
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos_ferias (
                colaborador_id,
                responsavel_id,
                data_inicio,
                data_fim,
                dias_uteis,
                observacoes,
                estado
             ) VALUES (
                :colaborador_id,
                :responsavel_id,
                :data_inicio,
                :data_fim,
                :dias_uteis,
                :observacoes,
                'PENDENTE'
             )"
        );
        $stmt->execute([
            'colaborador_id' => $colaboradorId,
            'responsavel_id' => $relacao['responsavel_id'],
            'data_inicio' => $validacao['inicio']->format('Y-m-d'),
            'data_fim' => $validacao['fim']->format('Y-m-d'),
            'dias_uteis' => $validacao['dias_uteis'],
            'observacoes' => $observacoes !== null && trim($observacoes) !== '' ? trim($observacoes) : null,
        ]);

        $pedidoId = (int) $pdo->lastInsertId();

        $stmtHistorico = $pdo->prepare(
            "INSERT INTO historico_pedidos_ferias (
                pedido_id,
                colaborador_id,
                estado_anterior,
                estado_novo,
                alterado_por,
                observacoes
             ) VALUES (
                :pedido_id,
                :colaborador_id,
                NULL,
                'PENDENTE',
                :alterado_por,
                :observacoes
             )"
        );
        $stmtHistorico->execute([
            'pedido_id' => $pedidoId,
            'colaborador_id' => $colaboradorId,
            'alterado_por' => $colaboradorId,
            'observacoes' => 'Pedido de férias criado.',
        ]);

        $pdo->commit();

        $nomeColaborador = $_SESSION['nome'] ?? 'Colaborador';
        $dataInicioFmt = $validacao['inicio']->format('d/m/Y');
        $dataFimFmt = $validacao['fim']->format('d/m/Y');
        $msgNovoPedido = "Novo pedido de férias de {$nomeColaborador}: {$dataInicioFmt} a {$dataFimFmt}.";

        // Notificar o responsável direto
        criarNotificacao($pdo, $relacao['responsavel_id'], $pedidoId, $msgNovoPedido);

        // Notificar todos os admins ativos (exceto se o responsável já é admin)
        $stmtAdmins = $pdo->prepare(
            "SELECT id FROM utilizadores
             WHERE perfil = 'admin'
               AND ativo = 1
               AND id <> :responsavel_id"
        );
        $stmtAdmins->execute(['responsavel_id' => $relacao['responsavel_id']]);
        foreach ($stmtAdmins->fetchAll() as $admin) {
            criarNotificacao($pdo, (int) $admin['id'], $pedidoId, $msgNovoPedido);
        }

        return [
            'valido' => true,
            'mensagem' => 'Pedido de férias registado com sucesso e enviado para aprovação.',
        ];
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'valido' => false,
            'mensagem' => 'Não foi possível guardar o pedido de férias.',
        ];
    }
}

function listarPedidosColaborador(PDO $pdo, int $colaboradorId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            pf.*,
            u.nome AS nome_responsavel
         FROM pedidos_ferias pf
         INNER JOIN utilizadores u ON u.id = pf.responsavel_id
         WHERE pf.colaborador_id = :colaborador_id
         ORDER BY pf.criado_em DESC, pf.id DESC"
    );
    $stmt->execute(['colaborador_id' => $colaboradorId]);

    return $stmt->fetchAll();
}

function listarHistoricoPedido(PDO $pdo, int $pedidoId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            hpf.*,
            u.nome AS nome_alterado_por
         FROM historico_pedidos_ferias hpf
         LEFT JOIN utilizadores u ON u.id = hpf.alterado_por
         WHERE hpf.pedido_id = :pedido_id
         ORDER BY hpf.criado_em DESC, hpf.id DESC"
    );
    $stmt->execute(['pedido_id' => $pedidoId]);

    return $stmt->fetchAll();
}

function listarHistoricosPedidos(array $pedidos, PDO $pdo): array
{
    if ($pedidos === []) {
        return [];
    }

    $ids = array_map(fn(array $p): int => (int) $p['id'], $pedidos);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT
            hpf.*,
            u.nome AS nome_alterado_por
         FROM historico_pedidos_ferias hpf
         LEFT JOIN utilizadores u ON u.id = hpf.alterado_por
         WHERE hpf.pedido_id IN ($placeholders)
         ORDER BY hpf.pedido_id ASC, hpf.criado_em DESC, hpf.id DESC"
    );
    $stmt->execute($ids);

    $historicos = [];

    foreach ($stmt->fetchAll() as $row) {
        $historicos[(int) $row['pedido_id']][] = $row;
    }

    return $historicos;
}

function listarPedidosEquipa(PDO $pdo, int $responsavelId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            pf.*,
            u.nome AS nome_colaborador
         FROM pedidos_ferias pf
         INNER JOIN utilizadores u ON u.id = pf.colaborador_id
         INNER JOIN responsaveis_colaboradores rc
            ON rc.colaborador_id = pf.colaborador_id
           AND rc.responsavel_id = :responsavel_id
           AND rc.ativo = 1
         WHERE pf.responsavel_id = :responsavel_id
         ORDER BY
            CASE pf.estado
                WHEN 'PENDENTE' THEN 0
                WHEN 'APROVADO' THEN 1
                WHEN 'REJEITADO' THEN 2
                WHEN 'CANCELADO' THEN 3
                ELSE 4
            END,
            pf.data_inicio ASC,
            pf.id DESC"
    );
    $stmt->execute(['responsavel_id' => $responsavelId]);

    return $stmt->fetchAll();
}

function obterResumoPedidosColaborador(PDO $pdo, int $colaboradorId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'PENDENTE' THEN 1 ELSE 0 END) AS pendentes,
            SUM(CASE WHEN estado = 'APROVADO' THEN dias_uteis ELSE 0 END) AS dias_aprovados
         FROM pedidos_ferias
         WHERE colaborador_id = :colaborador_id"
    );
    $stmt->execute(['colaborador_id' => $colaboradorId]);

    $resumo = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($resumo['total'] ?? 0),
        'pendentes' => (int) ($resumo['pendentes'] ?? 0),
        'dias_aprovados' => (int) ($resumo['dias_aprovados'] ?? 0),
    ];
}

function obterResumoPedidosResponsavel(PDO $pdo, int $responsavelId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(pf.id) AS total,
            SUM(CASE WHEN pf.estado = 'PENDENTE' THEN 1 ELSE 0 END) AS pendentes,
            COUNT(DISTINCT rc.colaborador_id) AS elementos_equipa
         FROM responsaveis_colaboradores rc
         LEFT JOIN pedidos_ferias pf
            ON pf.colaborador_id = rc.colaborador_id
           AND pf.responsavel_id = rc.responsavel_id
         WHERE rc.responsavel_id = :responsavel_id
           AND rc.ativo = 1"
    );
    $stmt->execute(['responsavel_id' => $responsavelId]);

    $resumo = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($resumo['total'] ?? 0),
        'pendentes' => (int) ($resumo['pendentes'] ?? 0),
        'elementos_equipa' => (int) ($resumo['elementos_equipa'] ?? 0),
    ];
}

function obterResumoAdmin(PDO $pdo): array
{
    $resumoUtilizadores = $pdo->query(
        "SELECT
            COUNT(*) AS total_ativos,
            SUM(CASE WHEN perfil = 'admin' AND ativo = 1 THEN 1 ELSE 0 END) AS admins_ativos,
            SUM(CASE WHEN perfil = 'responsavel' AND ativo = 1 THEN 1 ELSE 0 END) AS responsaveis_ativos,
            SUM(CASE WHEN perfil = 'colaborador' AND ativo = 1 THEN 1 ELSE 0 END) AS colaboradores_ativos
         FROM utilizadores"
    )->fetch() ?: [];

    $resumoPedidos = $pdo->query(
        "SELECT
            COUNT(*) AS total_pedidos,
            SUM(CASE WHEN estado = 'PENDENTE' THEN 1 ELSE 0 END) AS pedidos_pendentes,
            SUM(CASE WHEN estado = 'APROVADO' THEN dias_uteis ELSE 0 END) AS dias_aprovados
         FROM pedidos_ferias"
    )->fetch() ?: [];

    return [
        'total_ativos' => (int) ($resumoUtilizadores['total_ativos'] ?? 0),
        'admins_ativos' => (int) ($resumoUtilizadores['admins_ativos'] ?? 0),
        'responsaveis_ativos' => (int) ($resumoUtilizadores['responsaveis_ativos'] ?? 0),
        'colaboradores_ativos' => (int) ($resumoUtilizadores['colaboradores_ativos'] ?? 0),
        'total_pedidos' => (int) ($resumoPedidos['total_pedidos'] ?? 0),
        'pedidos_pendentes' => (int) ($resumoPedidos['pedidos_pendentes'] ?? 0),
        'dias_aprovados' => (int) ($resumoPedidos['dias_aprovados'] ?? 0),
    ];
}

function listarPedidosRecentesSistema(PDO $pdo, int $limite = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT
            pf.*,
            c.nome AS nome_colaborador,
            r.nome AS nome_responsavel
         FROM pedidos_ferias pf
         INNER JOIN utilizadores c ON c.id = pf.colaborador_id
         INNER JOIN utilizadores r ON r.id = pf.responsavel_id
         ORDER BY pf.criado_em DESC, pf.id DESC
         LIMIT :limite"
    );
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function listarPedidosProximosSistema(PDO $pdo, int $limite = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT
            pf.*,
            c.nome AS nome_colaborador
         FROM pedidos_ferias pf
         INNER JOIN utilizadores c ON c.id = pf.colaborador_id
         WHERE pf.data_inicio >= CURDATE()
           AND pf.estado IN ('PENDENTE', 'APROVADO')
         ORDER BY pf.data_inicio ASC, pf.id ASC
         LIMIT :limite"
    );
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function obterProximoPedidoColaborador(PDO $pdo, int $colaboradorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM pedidos_ferias
         WHERE colaborador_id = :colaborador_id
           AND data_inicio >= CURDATE()
           AND estado IN ('PENDENTE', 'APROVADO')
         ORDER BY data_inicio ASC, id ASC
         LIMIT 1"
    );
    $stmt->execute(['colaborador_id' => $colaboradorId]);

    $pedido = $stmt->fetch();

    return $pedido ?: null;
}

function formatarDataInterface(?string $data): string
{
    if ($data === null || $data === '') {
        return '-';
    }

    $objeto = DateTimeImmutable::createFromFormat('Y-m-d', substr($data, 0, 10));

    if (!$objeto) {
        return $data;
    }

    return $objeto->format('d/m/Y');
}

function formatarDataHoraInterface(?string $data): string
{
    if ($data === null || $data === '') {
        return '-';
    }

    $objeto = new DateTimeImmutable($data);

    return $objeto->format('d/m/Y H:i');
}

function traduzirPerfil(string $perfil): string
{
    return match ($perfil) {
        'admin' => 'Administrador',
        'responsavel' => 'Responsável',
        'colaborador' => 'Colaborador',
        default => $perfil,
    };
}

function cancelarPedidoColaborador(PDO $pdo, int $pedidoId, int $colaboradorId): array
{
    $stmt = $pdo->prepare("SELECT * FROM pedidos_ferias WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if ($pedido === false) {
        return ['sucesso' => false, 'mensagem' => 'Pedido não encontrado.'];
    }

    if ((int) $pedido['colaborador_id'] !== $colaboradorId) {
        return ['sucesso' => false, 'mensagem' => 'Não tem permissão para cancelar este pedido.'];
    }

    if ($pedido['estado'] !== 'PENDENTE') {
        return ['sucesso' => false, 'mensagem' => 'Só é possível cancelar pedidos pendentes.'];
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE pedidos_ferias
             SET estado = 'CANCELADO',
                 decidido_em = NOW(),
                 decidido_por = :decidido_por
             WHERE id = :id"
        );
        $stmt->execute(['decidido_por' => $colaboradorId, 'id' => $pedidoId]);

        $stmtHistorico = $pdo->prepare(
            "INSERT INTO historico_pedidos_ferias (
                pedido_id,
                colaborador_id,
                estado_anterior,
                estado_novo,
                alterado_por,
                observacoes
             ) VALUES (
                :pedido_id,
                :colaborador_id,
                'PENDENTE',
                'CANCELADO',
                :alterado_por,
                'Pedido cancelado pelo colaborador.'
             )"
        );
        $stmtHistorico->execute([
            'pedido_id' => $pedidoId,
            'colaborador_id' => $colaboradorId,
            'alterado_por' => $colaboradorId,
        ]);

        $pdo->commit();

        return ['sucesso' => true, 'mensagem' => 'Pedido cancelado com sucesso.'];
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['sucesso' => false, 'mensagem' => 'Não foi possível cancelar o pedido.'];
    }
}

function listarTodosPedidosSistema(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            pf.*,
            c.nome AS nome_colaborador,
            r.nome AS nome_responsavel
         FROM pedidos_ferias pf
         INNER JOIN utilizadores c ON c.id = pf.colaborador_id
         INNER JOIN utilizadores r ON r.id = pf.responsavel_id
         ORDER BY
            CASE pf.estado
                WHEN 'PENDENTE' THEN 0
                WHEN 'APROVADO' THEN 1
                WHEN 'REJEITADO' THEN 2
                WHEN 'CANCELADO' THEN 3
                ELSE 4
            END,
            pf.data_inicio ASC,
            pf.id DESC"
    );

    return $stmt->fetchAll();
}

function processarDecisaoPedidoAdmin(PDO $pdo, int $pedidoId, int $adminId, string $decisao, ?string $justificacao): array
{
    if (!in_array($decisao, ['APROVADO', 'REJEITADO', 'CANCELADO'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Decisão inválida.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM pedidos_ferias WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if ($pedido === false) {
        return ['sucesso' => false, 'mensagem' => 'Pedido não encontrado.'];
    }

    $estadosPermitidos = match ($decisao) {
        'APROVADO', 'REJEITADO' => ['PENDENTE'],
        'CANCELADO' => ['PENDENTE', 'APROVADO'],
        default => [],
    };

    if (!in_array($pedido['estado'], $estadosPermitidos, true)) {
        return ['sucesso' => false, 'mensagem' => 'Não é possível alterar o estado deste pedido.'];
    }

    $justificacaoFinal = $justificacao !== null && trim($justificacao) !== '' ? trim($justificacao) : null;
    $estadoAnterior = $pedido['estado'];

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE pedidos_ferias
             SET estado = :estado,
                 decidido_em = NOW(),
                 decidido_por = :decidido_por,
                 justificacao_decisao = :justificacao
             WHERE id = :id"
        );
        $stmt->execute([
            'estado' => $decisao,
            'decidido_por' => $adminId,
            'justificacao' => $justificacaoFinal,
            'id' => $pedidoId,
        ]);

        $stmtHistorico = $pdo->prepare(
            "INSERT INTO historico_pedidos_ferias (
                pedido_id,
                colaborador_id,
                estado_anterior,
                estado_novo,
                alterado_por,
                observacoes
             ) VALUES (
                :pedido_id,
                :colaborador_id,
                :estado_anterior,
                :estado_novo,
                :alterado_por,
                :observacoes
             )"
        );
        $stmtHistorico->execute([
            'pedido_id' => $pedidoId,
            'colaborador_id' => (int) $pedido['colaborador_id'],
            'estado_anterior' => $estadoAnterior,
            'estado_novo' => $decisao,
            'alterado_por' => $adminId,
            'observacoes' => $justificacaoFinal,
        ]);

        $pdo->commit();

        $dataInicioFmt = (new DateTimeImmutable($pedido['data_inicio']))->format('d/m/Y');
        $dataFimFmt = (new DateTimeImmutable($pedido['data_fim']))->format('d/m/Y');
        $msgNotif = match ($decisao) {
            'APROVADO'  => "O seu pedido de férias de {$dataInicioFmt} a {$dataFimFmt} foi aprovado.",
            'REJEITADO' => "O seu pedido de férias de {$dataInicioFmt} a {$dataFimFmt} foi rejeitado.",
            'CANCELADO' => "O seu pedido de férias de {$dataInicioFmt} a {$dataFimFmt} foi cancelado pelo administrador.",
            default     => "O estado do seu pedido de {$dataInicioFmt} a {$dataFimFmt} foi alterado.",
        };

        criarNotificacao($pdo, (int) $pedido['colaborador_id'], (int) $pedido['id'], $msgNotif);

        $mensagem = match ($decisao) {
            'APROVADO' => 'Pedido aprovado com sucesso.',
            'REJEITADO' => 'Pedido rejeitado.',
            'CANCELADO' => 'Pedido cancelado.',
            default => 'Decisão registada.',
        };

        return ['sucesso' => true, 'mensagem' => $mensagem];
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['sucesso' => false, 'mensagem' => 'Não foi possível processar a decisão.'];
    }
}

function criarNotificacao(PDO $pdo, int $utilizadorId, int $pedidoId, string $mensagem): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notificacoes (utilizador_id, pedido_id, mensagem)
         VALUES (:utilizador_id, :pedido_id, :mensagem)"
    );
    $stmt->execute([
        'utilizador_id' => $utilizadorId,
        'pedido_id' => $pedidoId,
        'mensagem' => $mensagem,
    ]);
}

function contarNotificacoesNaoLidas(PDO $pdo, int $utilizadorId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM notificacoes
         WHERE utilizador_id = :utilizador_id
           AND lida = 0"
    );
    $stmt->execute(['utilizador_id' => $utilizadorId]);

    return (int) $stmt->fetchColumn();
}

function listarNotificacoesRecentes(PDO $pdo, int $utilizadorId, int $limite = 10): array
{
    $stmt = $pdo->prepare(
        "SELECT
            n.*,
            pf.data_inicio,
            pf.data_fim
         FROM notificacoes n
         LEFT JOIN pedidos_ferias pf ON pf.id = n.pedido_id
         WHERE n.utilizador_id = :utilizador_id
         ORDER BY n.criado_em DESC, n.id DESC
         LIMIT :limite"
    );
    $stmt->bindValue(':utilizador_id', $utilizadorId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function marcarTodasNotificacoesLidas(PDO $pdo, int $utilizadorId): void
{
    $stmt = $pdo->prepare(
        "UPDATE notificacoes
         SET lida = 1
         WHERE utilizador_id = :utilizador_id
           AND lida = 0"
    );
    $stmt->execute(['utilizador_id' => $utilizadorId]);
}

function obterSaldoFerias(PDO $pdo, int $colaboradorId, int $ano): array
{
    $stmt = $pdo->prepare(
        "SELECT dias_total FROM saldo_ferias WHERE colaborador_id = :cid AND ano = :ano LIMIT 1"
    );
    $stmt->execute(['cid' => $colaboradorId, 'ano' => $ano]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->prepare(
            "INSERT IGNORE INTO saldo_ferias (colaborador_id, ano, dias_total) VALUES (:cid, :ano, 22)"
        )->execute(['cid' => $colaboradorId, 'ano' => $ano]);
        $diasTotal = 22;
    } else {
        $diasTotal = (int) $row['dias_total'];
    }

    $stmtUsados = $pdo->prepare(
        "SELECT COALESCE(SUM(dias_uteis), 0)
         FROM pedidos_ferias
         WHERE colaborador_id = :cid
           AND estado = 'APROVADO'
           AND YEAR(data_inicio) = :ano"
    );
    $stmtUsados->execute(['cid' => $colaboradorId, 'ano' => $ano]);
    $usados = (int) $stmtUsados->fetchColumn();

    return [
        'total'       => $diasTotal,
        'usados'      => $usados,
        'disponiveis' => max(0, $diasTotal - $usados),
    ];
}

function atualizarDiasTotalSaldo(PDO $pdo, int $colaboradorId, int $ano, int $diasTotal): void
{
    $pdo->prepare(
        "INSERT INTO saldo_ferias (colaborador_id, ano, dias_total)
         VALUES (:cid, :ano, :total)
         ON DUPLICATE KEY UPDATE dias_total = :total"
    )->execute(['cid' => $colaboradorId, 'ano' => $ano, 'total' => $diasTotal]);
}

function contarPedidosColaborador(PDO $pdo, int $colaboradorId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_ferias WHERE colaborador_id = :cid");
    $stmt->execute(['cid' => $colaboradorId]);
    return (int) $stmt->fetchColumn();
}

function listarPedidosColaboradorPaginado(PDO $pdo, int $colaboradorId, int $pagina, int $porPagina): array
{
    $offset = ($pagina - 1) * $porPagina;
    $stmt = $pdo->prepare(
        "SELECT pf.*, u.nome AS nome_responsavel
         FROM pedidos_ferias pf
         INNER JOIN utilizadores u ON u.id = pf.responsavel_id
         WHERE pf.colaborador_id = :cid
         ORDER BY pf.criado_em DESC, pf.id DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':cid', $colaboradorId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function contarPedidosEquipa(PDO $pdo, int $responsavelId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(pf.id)
         FROM pedidos_ferias pf
         INNER JOIN responsaveis_colaboradores rc
            ON rc.colaborador_id = pf.colaborador_id
           AND rc.responsavel_id = :rid
           AND rc.ativo = 1
         WHERE pf.responsavel_id = :rid"
    );
    $stmt->execute(['rid' => $responsavelId]);
    return (int) $stmt->fetchColumn();
}

function listarPedidosEquipaPaginado(PDO $pdo, int $responsavelId, int $pagina, int $porPagina): array
{
    $offset = ($pagina - 1) * $porPagina;
    $stmt = $pdo->prepare(
        "SELECT pf.*, u.nome AS nome_colaborador
         FROM pedidos_ferias pf
         INNER JOIN utilizadores u ON u.id = pf.colaborador_id
         INNER JOIN responsaveis_colaboradores rc
            ON rc.colaborador_id = pf.colaborador_id
           AND rc.responsavel_id = :rid
           AND rc.ativo = 1
         WHERE pf.responsavel_id = :rid
         ORDER BY
            CASE pf.estado WHEN 'PENDENTE' THEN 0 WHEN 'APROVADO' THEN 1 WHEN 'REJEITADO' THEN 2 ELSE 3 END,
            pf.data_inicio ASC, pf.id DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':rid', $responsavelId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function contarTodosPedidosSistema(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM pedidos_ferias")->fetchColumn();
}

function listarTodosPedidosSistemaPaginado(PDO $pdo, int $pagina, int $porPagina): array
{
    $offset = ($pagina - 1) * $porPagina;
    $stmt = $pdo->prepare(
        "SELECT pf.*, c.nome AS nome_colaborador, r.nome AS nome_responsavel
         FROM pedidos_ferias pf
         INNER JOIN utilizadores c ON c.id = pf.colaborador_id
         INNER JOIN utilizadores r ON r.id = pf.responsavel_id
         ORDER BY
            CASE pf.estado WHEN 'PENDENTE' THEN 0 WHEN 'APROVADO' THEN 1 WHEN 'REJEITADO' THEN 2 ELSE 3 END,
            pf.data_inicio ASC, pf.id DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function obterDadosColaborador(PDO $pdo, int $colaboradorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, nome, username, email, perfil, ativo, criado_em
         FROM utilizadores
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $colaboradorId]);

    $utilizador = $stmt->fetch();

    return $utilizador ?: null;
}

function processarDecisaoPedido(PDO $pdo, int $pedidoId, int $responsavelId, string $decisao, ?string $justificacao): array
{
    if (!in_array($decisao, ['APROVADO', 'REJEITADO'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Decisão inválida.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM pedidos_ferias WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if ($pedido === false) {
        return ['sucesso' => false, 'mensagem' => 'Pedido não encontrado.'];
    }

    if ($pedido['estado'] !== 'PENDENTE') {
        return ['sucesso' => false, 'mensagem' => 'Só é possível decidir sobre pedidos pendentes.'];
    }

    if ((int) $pedido['responsavel_id'] !== $responsavelId) {
        return ['sucesso' => false, 'mensagem' => 'Não tem permissão para decidir sobre este pedido.'];
    }

    $justificacaoFinal = $justificacao !== null && trim($justificacao) !== '' ? trim($justificacao) : null;

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "UPDATE pedidos_ferias
             SET estado = :estado,
                 decidido_em = NOW(),
                 decidido_por = :decidido_por,
                 justificacao_decisao = :justificacao
             WHERE id = :id"
        );
        $stmt->execute([
            'estado' => $decisao,
            'decidido_por' => $responsavelId,
            'justificacao' => $justificacaoFinal,
            'id' => $pedidoId,
        ]);

        $stmtHistorico = $pdo->prepare(
            "INSERT INTO historico_pedidos_ferias (
                pedido_id,
                colaborador_id,
                estado_anterior,
                estado_novo,
                alterado_por,
                observacoes
             ) VALUES (
                :pedido_id,
                :colaborador_id,
                'PENDENTE',
                :estado_novo,
                :alterado_por,
                :observacoes
             )"
        );
        $stmtHistorico->execute([
            'pedido_id' => $pedidoId,
            'colaborador_id' => (int) $pedido['colaborador_id'],
            'estado_novo' => $decisao,
            'alterado_por' => $responsavelId,
            'observacoes' => $justificacaoFinal,
        ]);

        $pdo->commit();

        $dataInicioFmt = (new DateTimeImmutable($pedido['data_inicio']))->format('d/m/Y');
        $dataFimFmt = (new DateTimeImmutable($pedido['data_fim']))->format('d/m/Y');
        $msgNotif = $decisao === 'APROVADO'
            ? "O seu pedido de férias de {$dataInicioFmt} a {$dataFimFmt} foi aprovado."
            : "O seu pedido de férias de {$dataInicioFmt} a {$dataFimFmt} foi rejeitado.";

        criarNotificacao($pdo, (int) $pedido['colaborador_id'], (int) $pedido['id'], $msgNotif);

        $mensagem = $decisao === 'APROVADO'
            ? 'Pedido aprovado com sucesso.'
            : 'Pedido rejeitado.';

        return ['sucesso' => true, 'mensagem' => $mensagem];
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['sucesso' => false, 'mensagem' => 'Não foi possível processar a decisão.'];
    }
}
