-- Demo data for local testing (fictional accounts). Password for all: 123456
-- Run after importing sistema_ferias.sql

INSERT INTO utilizadores (nome, username, email, password, perfil, ativo) VALUES
('Administrador Ribas', 'admin',              'admin@ribas.pt',              '$2y$10$1jh6VcYu6jRK78.Ydf.OFepQV7LOVhHOgZn.9fnf6Y2iERJcaXU7.', 'admin',       1),
('Maria Responsavel',   'maria.responsavel',  'maria.responsavel@ribas.pt',  '$2y$10$1jh6VcYu6jRK78.Ydf.OFepQV7LOVhHOgZn.9fnf6Y2iERJcaXU7.', 'responsavel', 1),
('Joao Colaborador',    'joao.colaborador',   'joao.colaborador@ribas.pt',   '$2y$10$1jh6VcYu6jRK78.Ydf.OFepQV7LOVhHOgZn.9fnf6Y2iERJcaXU7.', 'colaborador', 1),
('Rui Colaborador',     'rui.colaborador',    'rui.colaborador@ribas.pt',    '$2y$10$1jh6VcYu6jRK78.Ydf.OFepQV7LOVhHOgZn.9fnf6Y2iERJcaXU7.', 'colaborador', 1),
('Sofia Colaborador',   'sofia.colaborador',  'sofia.colaborador@ribas.pt',  '$2y$10$1jh6VcYu6jRK78.Ydf.OFepQV7LOVhHOgZn.9fnf6Y2iERJcaXU7.', 'colaborador', 1);

-- Link collaborators (ids 3,4,5) to the responsible user (id 2)
INSERT INTO responsaveis_colaboradores (colaborador_id, responsavel_id, ativo, data_inicio, criado_por)
SELECT c.id,
       (SELECT id FROM utilizadores WHERE username = 'maria.responsavel'),
       1, CURDATE(),
       (SELECT id FROM utilizadores WHERE username = 'admin')
FROM utilizadores c
WHERE c.username IN ('joao.colaborador', 'rui.colaborador', 'sofia.colaborador');
