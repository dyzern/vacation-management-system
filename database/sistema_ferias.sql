CREATE DATABASE IF NOT EXISTS sistema_ferias
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistema_ferias;

CREATE TABLE IF NOT EXISTS utilizadores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'responsavel', 'colaborador') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS responsaveis_colaboradores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_inicio DATE NOT NULL,
    criado_por INT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rc_colaborador FOREIGN KEY (colaborador_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_rc_responsavel FOREIGN KEY (responsavel_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_rc_criado_por FOREIGN KEY (criado_por) REFERENCES utilizadores (id),
    INDEX idx_rc_responsavel_ativo (responsavel_id, ativo),
    INDEX idx_rc_colaborador_ativo (colaborador_id, ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_responsaveis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    nome_colaborador VARCHAR(150) NOT NULL,
    username_colaborador VARCHAR(80) NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    nome_responsavel VARCHAR(150) NOT NULL,
    username_responsavel VARCHAR(80) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE DEFAULT NULL,
    acao ENUM('ASSOCIADO', 'ALTERADO', 'REMOVIDO', 'UTILIZADOR_DESATIVADO', 'UTILIZADOR_APAGADO') NOT NULL,
    alterado_por INT UNSIGNED DEFAULT NULL,
    timestamp_alteracao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacoes VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_hr_colaborador FOREIGN KEY (colaborador_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_hr_responsavel FOREIGN KEY (responsavel_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_hr_alterado_por FOREIGN KEY (alterado_por) REFERENCES utilizadores (id),
    INDEX idx_hr_colaborador_data (colaborador_id, data_inicio, data_fim),
    INDEX idx_hr_responsavel_data (responsavel_id, data_inicio, data_fim)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pedidos_ferias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    dias_uteis INT UNSIGNED NOT NULL,
    observacoes VARCHAR(500) DEFAULT NULL,
    estado ENUM('PENDENTE', 'APROVADO', 'REJEITADO', 'CANCELADO') NOT NULL DEFAULT 'PENDENTE',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    decidido_em DATETIME DEFAULT NULL,
    decidido_por INT UNSIGNED DEFAULT NULL,
    justificacao_decisao VARCHAR(500) DEFAULT NULL,
    CONSTRAINT fk_pf_colaborador FOREIGN KEY (colaborador_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_pf_responsavel FOREIGN KEY (responsavel_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_pf_decidido_por FOREIGN KEY (decidido_por) REFERENCES utilizadores (id),
    INDEX idx_pf_colaborador_estado (colaborador_id, estado, criado_em),
    INDEX idx_pf_responsavel_estado (responsavel_id, estado, criado_em),
    INDEX idx_pf_periodo (data_inicio, data_fim)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historico_pedidos_ferias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    colaborador_id INT UNSIGNED NOT NULL,
    estado_anterior ENUM('PENDENTE', 'APROVADO', 'REJEITADO', 'CANCELADO') DEFAULT NULL,
    estado_novo ENUM('PENDENTE', 'APROVADO', 'REJEITADO', 'CANCELADO') NOT NULL,
    alterado_por INT UNSIGNED DEFAULT NULL,
    observacoes VARCHAR(500) DEFAULT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hpf_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos_ferias (id),
    CONSTRAINT fk_hpf_colaborador FOREIGN KEY (colaborador_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_hpf_alterado_por FOREIGN KEY (alterado_por) REFERENCES utilizadores (id),
    INDEX idx_hpf_pedido (pedido_id, criado_em),
    INDEX idx_hpf_colaborador (colaborador_id, criado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNSIGNED NOT NULL,
    pedido_id INT UNSIGNED DEFAULT NULL,
    mensagem VARCHAR(300) NOT NULL,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id),
    CONSTRAINT fk_notif_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos_ferias (id),
    INDEX idx_notif_utilizador_lida (utilizador_id, lida, criado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saldo_ferias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    colaborador_id INT UNSIGNED NOT NULL,
    ano YEAR NOT NULL,
    dias_total INT UNSIGNED NOT NULL DEFAULT 22,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sf_colaborador FOREIGN KEY (colaborador_id) REFERENCES utilizadores (id) ON DELETE CASCADE,
    UNIQUE KEY uq_sf_colaborador_ano (colaborador_id, ano),
    INDEX idx_sf_colaborador_ano (colaborador_id, ano)
) ENGINE=InnoDB;

DROP TRIGGER IF EXISTS trg_rc_bi_validar_relacao;
DELIMITER $$
CREATE TRIGGER trg_rc_bi_validar_relacao
BEFORE INSERT ON responsaveis_colaboradores
FOR EACH ROW
BEGIN
    DECLARE v_colaborador_perfil VARCHAR(20);
    DECLARE v_colaborador_ativo TINYINT;
    DECLARE v_responsavel_perfil VARCHAR(20);
    DECLARE v_responsavel_ativo TINYINT;
    DECLARE v_relacao_ativa INT DEFAULT 0;

    SELECT perfil, ativo
    INTO v_colaborador_perfil, v_colaborador_ativo
    FROM utilizadores
    WHERE id = NEW.colaborador_id;

    IF v_colaborador_perfil IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Colaborador inexistente.';
    END IF;

    SELECT perfil, ativo
    INTO v_responsavel_perfil, v_responsavel_ativo
    FROM utilizadores
    WHERE id = NEW.responsavel_id;

    IF v_responsavel_perfil IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Responsável inexistente.';
    END IF;

    IF NEW.colaborador_id = NEW.responsavel_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Não é permitida autorreferência.';
    END IF;

    IF v_colaborador_perfil <> 'colaborador' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O utilizador indicado não tem perfil colaborador.';
    END IF;

    IF v_responsavel_perfil NOT IN ('responsavel', 'admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O responsável tem de ser admin ou responsavel.';
    END IF;

    IF v_colaborador_ativo <> 1 OR v_responsavel_ativo <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ambos os utilizadores têm de estar ativos.';
    END IF;

    IF NEW.ativo = 1 THEN
        SELECT COUNT(*)
        INTO v_relacao_ativa
        FROM responsaveis_colaboradores
        WHERE colaborador_id = NEW.colaborador_id
          AND ativo = 1;

        IF v_relacao_ativa > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O colaborador já tem uma relação ativa.';
        END IF;
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_rc_ai_historico;
DELIMITER $$
CREATE TRIGGER trg_rc_ai_historico
AFTER INSERT ON responsaveis_colaboradores
FOR EACH ROW
BEGIN
    DECLARE v_nome_colaborador VARCHAR(150);
    DECLARE v_username_colaborador VARCHAR(80);
    DECLARE v_nome_responsavel VARCHAR(150);
    DECLARE v_username_responsavel VARCHAR(80);

    SELECT nome, username
    INTO v_nome_colaborador, v_username_colaborador
    FROM utilizadores
    WHERE id = NEW.colaborador_id;

    SELECT nome, username
    INTO v_nome_responsavel, v_username_responsavel
    FROM utilizadores
    WHERE id = NEW.responsavel_id;

    INSERT INTO historico_responsaveis (
        colaborador_id,
        nome_colaborador,
        username_colaborador,
        responsavel_id,
        nome_responsavel,
        username_responsavel,
        data_inicio,
        data_fim,
        acao,
        alterado_por,
        observacoes
    ) VALUES (
        NEW.colaborador_id,
        v_nome_colaborador,
        v_username_colaborador,
        NEW.responsavel_id,
        v_nome_responsavel,
        v_username_responsavel,
        NEW.data_inicio,
        NULL,
        'ASSOCIADO',
        NEW.criado_por,
        'Associação inicial criada.'
    );
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_rc_bu_validar_update;
DELIMITER $$
CREATE TRIGGER trg_rc_bu_validar_update
BEFORE UPDATE ON responsaveis_colaboradores
FOR EACH ROW
BEGIN
    DECLARE v_responsavel_perfil VARCHAR(20);
    DECLARE v_responsavel_ativo TINYINT;

    IF NEW.colaborador_id <> OLD.colaborador_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Não é permitido alterar o colaborador da relação.';
    END IF;

    IF NEW.colaborador_id = NEW.responsavel_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Não é permitida autorreferência.';
    END IF;

    SELECT perfil, ativo
    INTO v_responsavel_perfil, v_responsavel_ativo
    FROM utilizadores
    WHERE id = NEW.responsavel_id;

    IF v_responsavel_perfil IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Responsável inexistente.';
    END IF;

    IF v_responsavel_perfil NOT IN ('responsavel', 'admin') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O responsável tem de ser admin ou responsavel.';
    END IF;

    IF v_responsavel_ativo <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'O responsável tem de estar ativo.';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_rc_au_historico;
DELIMITER $$
CREATE TRIGGER trg_rc_au_historico
AFTER UPDATE ON responsaveis_colaboradores
FOR EACH ROW
BEGIN
    DECLARE v_nome_colaborador VARCHAR(150);
    DECLARE v_username_colaborador VARCHAR(80);
    DECLARE v_nome_responsavel VARCHAR(150);
    DECLARE v_username_responsavel VARCHAR(80);

    IF NEW.responsavel_id <> OLD.responsavel_id THEN
        UPDATE historico_responsaveis
        SET data_fim = CURDATE()
        WHERE colaborador_id = OLD.colaborador_id
          AND data_fim IS NULL;

        SELECT nome, username
        INTO v_nome_colaborador, v_username_colaborador
        FROM utilizadores
        WHERE id = NEW.colaborador_id;

        SELECT nome, username
        INTO v_nome_responsavel, v_username_responsavel
        FROM utilizadores
        WHERE id = NEW.responsavel_id;

        INSERT INTO historico_responsaveis (
            colaborador_id,
            nome_colaborador,
            username_colaborador,
            responsavel_id,
            nome_responsavel,
            username_responsavel,
            data_inicio,
            data_fim,
            acao,
            alterado_por,
            observacoes
        ) VALUES (
            NEW.colaborador_id,
            v_nome_colaborador,
            v_username_colaborador,
            NEW.responsavel_id,
            v_nome_responsavel,
            v_username_responsavel,
            NEW.data_inicio,
            NULL,
            'ALTERADO',
            NEW.criado_por,
            'Responsável alterado.'
        );
    END IF;

    IF OLD.ativo = 1 AND NEW.ativo = 0 THEN
        UPDATE historico_responsaveis
        SET data_fim = CURDATE()
        WHERE colaborador_id = OLD.colaborador_id
          AND data_fim IS NULL;

        SELECT nome, username
        INTO v_nome_colaborador, v_username_colaborador
        FROM utilizadores
        WHERE id = OLD.colaborador_id;

        SELECT nome, username
        INTO v_nome_responsavel, v_username_responsavel
        FROM utilizadores
        WHERE id = OLD.responsavel_id;

        INSERT INTO historico_responsaveis (
            colaborador_id,
            nome_colaborador,
            username_colaborador,
            responsavel_id,
            nome_responsavel,
            username_responsavel,
            data_inicio,
            data_fim,
            acao,
            alterado_por,
            observacoes
        ) VALUES (
            OLD.colaborador_id,
            v_nome_colaborador,
            v_username_colaborador,
            OLD.responsavel_id,
            v_nome_responsavel,
            v_username_responsavel,
            OLD.data_inicio,
            CURDATE(),
            'REMOVIDO',
            NEW.criado_por,
            'Relação desativada.'
        );
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_utilizadores_au_estado;
DELIMITER $$
CREATE TRIGGER trg_utilizadores_au_estado
AFTER UPDATE ON utilizadores
FOR EACH ROW
BEGIN
    DECLARE v_nome_responsavel VARCHAR(150);
    DECLARE v_username_responsavel VARCHAR(80);
    DECLARE v_responsavel_id INT UNSIGNED;

    IF OLD.ativo = 1
       AND NEW.ativo = 0
       AND OLD.perfil = 'colaborador' THEN
        SELECT responsavel_id
        INTO v_responsavel_id
        FROM responsaveis_colaboradores
        WHERE colaborador_id = NEW.id
          AND ativo = 1
        LIMIT 1;

        IF v_responsavel_id IS NOT NULL THEN
            SELECT nome, username
            INTO v_nome_responsavel, v_username_responsavel
            FROM utilizadores
            WHERE id = v_responsavel_id;

            UPDATE historico_responsaveis
            SET data_fim = CURDATE()
            WHERE colaborador_id = NEW.id
              AND data_fim IS NULL;

            INSERT INTO historico_responsaveis (
                colaborador_id,
                nome_colaborador,
                username_colaborador,
                responsavel_id,
                nome_responsavel,
                username_responsavel,
                data_inicio,
                data_fim,
                acao,
                alterado_por,
                observacoes
            ) VALUES (
                NEW.id,
                NEW.nome,
                NEW.username,
                v_responsavel_id,
                v_nome_responsavel,
                v_username_responsavel,
                CURDATE(),
                CURDATE(),
                'UTILIZADOR_DESATIVADO',
                NEW.id,
                'Colaborador desativado logicamente.'
            );

            UPDATE responsaveis_colaboradores
            SET ativo = 0
            WHERE colaborador_id = NEW.id
              AND ativo = 1;
        END IF;
    END IF;
END$$
DELIMITER ;
