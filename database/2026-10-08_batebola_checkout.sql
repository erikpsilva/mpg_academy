CREATE TABLE IF NOT EXISTS batebola_pedidos (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 jogador_id INT NOT NULL,
 valor DECIMAL(10,2) NOT NULL,
 status ENUM('reservado','pendente','pago','cancelado') NOT NULL DEFAULT 'reservado',
 chave_pagamento VARCHAR(80) NOT NULL,
 mp_payment_id VARCHAR(50) NULL,
 pix_qr_code TEXT NULL,
 pix_qr_code_base64 LONGTEXT NULL,
 criado_em DATETIME NOT NULL,
 pago_em DATETIME NULL,
 UNIQUE KEY uq_bb_pedido_mp (mp_payment_id),
 INDEX idx_bb_pedido_jogador (jogador_id,status),
 CONSTRAINT fk_bb_pedido_jogador FOREIGN KEY(jogador_id) REFERENCES jogadores_batebola(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batebola_pedido_itens (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pedido_id INT NOT NULL,
 evento_chave VARCHAR(60) NOT NULL,
 tipo ENUM('domingo','especial') NOT NULL,
 data_evento DATE NOT NULL,
 especial_id INT NULL,
 titulo VARCHAR(160) NOT NULL,
 horario VARCHAR(160) NOT NULL,
 valor DECIMAL(10,2) NOT NULL,
 inscricao_id INT NULL,
 UNIQUE KEY uq_bb_pedido_evento(pedido_id,evento_chave),
 INDEX idx_bb_item_evento(evento_chave),
 CONSTRAINT fk_bb_item_pedido FOREIGN KEY(pedido_id) REFERENCES batebola_pedidos(id),
 CONSTRAINT fk_bb_item_especial FOREIGN KEY(especial_id) REFERENCES batebola_especiais(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
