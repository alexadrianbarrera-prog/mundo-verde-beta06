-- Agrega los campos de domicilio que ya pide registro.html
-- pero que hoy no se guardan en la base.
-- Correr una sola vez en phpMyAdmin o consola MySQL.

ALTER TABLE usuarios
    ADD COLUMN domicilio_completo VARCHAR(150) NULL AFTER fecha_nacimiento,
    ADD COLUMN localidad          VARCHAR(100) NULL AFTER domicilio_completo,
    ADD COLUMN codigo_postal      VARCHAR(10)  NULL AFTER localidad;
