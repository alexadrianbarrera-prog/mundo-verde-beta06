-- =====================================================================
-- Roles de personal: vendedor y mantenimiento
-- Correr una sola vez en phpMyAdmin o consola MySQL (después de
-- alter_usuarios.sql y alter_pedidos.sql, que ya deberías tener corridos).
-- =====================================================================

-- 1) Nuevos roles de personal, además de cliente/admin que ya existían.
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('cliente','admin','vendedor','mantenimiento')
        NOT NULL DEFAULT 'cliente';

-- 2) Quién fue el último en cambiar el estado de cada pedido (vendedor o
--    admin). Sirve para saber qué vendedor atendió a cada cliente.
ALTER TABLE pedidos
    ADD COLUMN atendido_por INT UNSIGNED NULL AFTER estado,
    ADD CONSTRAINT fk_pedidos_atendido_por FOREIGN KEY (atendido_por)
        REFERENCES usuarios(id) ON DELETE SET NULL;
