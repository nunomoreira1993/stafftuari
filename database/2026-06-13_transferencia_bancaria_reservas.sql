ALTER TABLE `rps`
    ADD COLUMN `permite_transferencia_bancaria` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `permite_reservar_privados`;

ALTER TABLE `privados_salas_mesas_disponibilidade`
    ADD COLUMN `metodo_pagamento_caucao` VARCHAR(30) NOT NULL DEFAULT 'mbway'
    AFTER `valor_caucao_reserva`;
