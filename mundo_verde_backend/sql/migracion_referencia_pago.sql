-- Guarda el Nº de pedido (MV-DDMM-NNNN) que ve el cliente al transferir.
-- Ejecutar UNA vez en la base de mundo_verde antes de subir el nuevo pedidos.php.
ALTER TABLE pedidos
  ADD COLUMN referencia_pago VARCHAR(20) NULL AFTER forma_pago,
  ADD INDEX idx_pedidos_referencia_pago (referencia_pago);
