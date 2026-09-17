-- Agrega el comprobante de pago adjuntado por el cliente y el estado
-- "pagado" (se setea solo cuando se sube el comprobante, ver
-- pedidos_subir_comprobante() en api/controllers/pedidos.php).
-- Correr una sola vez en phpMyAdmin o consola MySQL.

ALTER TABLE pedidos
    MODIFY COLUMN estado ENUM('pendiente','pagado','confirmado','entregado','cancelado')
        NOT NULL DEFAULT 'pendiente',
    ADD COLUMN comprobante_url VARCHAR(255) NULL AFTER total;
