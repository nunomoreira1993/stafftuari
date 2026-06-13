ALTER TABLE `privados_salas_mesas_disponibilidade`
    ADD COLUMN `valor_transferencia_bancaria_adiantado` DOUBLE NOT NULL DEFAULT 0
    AFTER `valor_mbway_adiantado`;

ALTER TABLE `venda_privados`
    ADD COLUMN `valor_transferencia_bancaria_adiantado` DOUBLE NOT NULL DEFAULT 0
    AFTER `valor_mbway_adiantado`;

UPDATE `privados_salas_mesas_disponibilidade`
SET `valor_transferencia_bancaria_adiantado` = `valor_caucao_reserva`
WHERE `metodo_pagamento_caucao` = 'transferencia_bancaria'
  AND `valor_transferencia_bancaria_adiantado` = 0;
