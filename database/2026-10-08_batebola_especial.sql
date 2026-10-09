-- Eventos independentes: nenhuma alteração nas inscrições do domingo tradicional.
CREATE TABLE IF NOT EXISTS batebola_especiais (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(120) NOT NULL,
 inicio DATETIME NOT NULL,
 fim DATETIME NOT NULL,
 valor DECIMAL(10,2) NOT NULL,
 vagas INT NOT NULL DEFAULT 24,
 status ENUM('aberto','fechado','encerrado') NOT NULL DEFAULT 'aberto',
 seed INT NULL,
 trocas TEXT NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_especial_agenda (status,inicio,fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batebola_especial_inscricoes (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 evento_id INT NOT NULL,
 jogador_id INT NOT NULL,
 valor DECIMAL(10,2) NOT NULL,
 status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
 presente TINYINT NOT NULL DEFAULT 0,
 mp_payment_id VARCHAR(50) NULL,
 pix_qr_code TEXT NULL,
 pix_qr_code_base64 LONGTEXT NULL,
 chave_pagamento VARCHAR(80) NOT NULL,
 pago_em DATETIME NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_especial_jogador (evento_id,jogador_id),
 UNIQUE KEY uq_especial_pagamento (mp_payment_id),
 CONSTRAINT fk_especial_evento FOREIGN KEY (evento_id) REFERENCES batebola_especiais(id),
 CONSTRAINT fk_especial_jogador FOREIGN KEY (jogador_id) REFERENCES jogadores_batebola(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
