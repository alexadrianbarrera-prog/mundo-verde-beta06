-- ════════════════════════════════════════════════════════════════
--  Migración: registro corto (usuarios que entran por el Newsletter)
--  Mundo Verde — correr en phpMyAdmin (pestaña SQL de la base mundo_verde)
--
--  ANTES: exportá la base como respaldo.
--  Correr ESTO ANTES de subir el nuevo auth.php y newsletter.php.
--  (Las columnas telefono, fecha_nacimiento, domicilio_completo, localidad y
--   codigo_postal ya aceptan NULL: no hay que tocarlas.)
-- ════════════════════════════════════════════════════════════════

-- 1 = perfil completo (todos los usuarios que ya existen quedan así)
-- 0 = registro corto  (se suscribió por el newsletter, faltan datos)
ALTER TABLE usuarios
    ADD COLUMN perfil_completo TINYINT(1) NOT NULL DEFAULT 1;

-- Verificación: tiene que aparecer perfil_completo con default 1
SHOW COLUMNS FROM usuarios;

-- Deshacer (solo si hace falta):
-- ALTER TABLE usuarios DROP COLUMN perfil_completo;
