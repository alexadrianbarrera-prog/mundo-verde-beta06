-- Guarda el ID de la transacción de Getnet en cada pedido pagado con tarjeta.
-- Sirve para que el webhook encuentre el pedido y para conciliar contra el
-- portal de Getnet. Correr una sola vez en CADA base (local mundo_verde y la
-- de Hostinger) desde phpMyAdmin o consola MySQL.

ALTER TABLE pedidos
    ADD COLUMN getnet_id VARCHAR(100) NULL,
    ADD INDEX idx_pedidos_getnet_id (getnet_id);
