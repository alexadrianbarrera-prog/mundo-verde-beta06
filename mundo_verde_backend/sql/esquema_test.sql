-- Solo para el banco de pruebas: completa lo que mundo_verde.sql (del zip) no trae pero el código usa.
-- Compará esto con tu base real: si falta alguna columna/tabla, pedidos_crear falla en producción.
ALTER TABLE pedidos
  ADD COLUMN retiro_nombre VARCHAR(100) NULL,
  ADD COLUMN retiro_dni VARCHAR(15) NULL,
  ADD COLUMN codigo_verde VARCHAR(20) NULL,
  ADD COLUMN descuento_inauguracion DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN descuento_newsletter DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE newsletter_suscriptores
  ADD COLUMN codigo_verde VARCHAR(12) NULL UNIQUE,
  ADD COLUMN codigo_usado_en DATETIME NULL,
  ADD COLUMN codigo_usado_pedido_id INT UNSIGNED NULL;
CREATE TABLE IF NOT EXISTS configuracion (clave VARCHAR(60) PRIMARY KEY, valor VARCHAR(255) NOT NULL);
