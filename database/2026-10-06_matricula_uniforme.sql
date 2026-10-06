-- Taxa de matrícula com uniforme incluso.
-- Seguro para executar uma única vez em produção.

INSERT INTO configuracoes (chave, valor)
VALUES
    ('matricula_ativa', '1'),
    ('valor_matricula', '150.00'),
    ('matricula_uniforme_valor', '115.00')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

ALTER TABLE mensalidades
    ADD COLUMN matricula_uniforme_valor DECIMAL(10,2) NULL AFTER matricula_valor;

ALTER TABLE pedidos_uniforme
    ADD COLUMN origem_cobranca ENUM('avulso','matricula') NOT NULL DEFAULT 'avulso' AFTER valor,
    ADD COLUMN mensalidade_id INT NULL AFTER origem_cobranca,
    ADD INDEX idx_uniforme_mensalidade (mensalidade_id),
    ADD CONSTRAINT fk_uniforme_mensalidade
        FOREIGN KEY (mensalidade_id) REFERENCES mensalidades(id) ON DELETE SET NULL;

CREATE TABLE cadastro_convites (
    id INT NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    turma_id INT NOT NULL,
    nome VARCHAR(150) NULL,
    whatsapp VARCHAR(20) NOT NULL,
    enviado_por_usuario_id INT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    aluno_id INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cadastro_convite_token (token_hash),
    KEY idx_cadastro_convite_turma (turma_id),
    KEY idx_cadastro_convite_aluno (aluno_id),
    CONSTRAINT fk_cadastro_convite_turma FOREIGN KEY (turma_id) REFERENCES turmas(id),
    CONSTRAINT fk_cadastro_convite_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
