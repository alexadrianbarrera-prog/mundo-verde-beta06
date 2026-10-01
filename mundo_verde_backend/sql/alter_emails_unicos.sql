-- Garantiza que no se repitan mails:
--   * usuarios.email                 -> un mail por usuario registrado
--   * newsletter_suscriptores.mail   -> un mail por suscriptor
-- Son dos listas independientes: el mismo mail puede estar en ambas,
-- pero nunca dos veces en la misma.
-- Correr en phpMyAdmin / consola MySQL, en este orden.

-- 1) Buscar duplicados existentes (ambas consultas deben devolver 0 filas).
SELECT LOWER(TRIM(email)) AS mail, COUNT(*) AS veces
FROM usuarios GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1;

SELECT LOWER(TRIM(mail)) AS mail, COUNT(*) AS veces
FROM newsletter_suscriptores GROUP BY LOWER(TRIM(mail)) HAVING COUNT(*) > 1;

-- Si alguna devuelve filas, borrá o corregí esos registros ANTES de seguir.

-- 2) Ver si el índice único ya existe (mundo_verde.sql lo crea con UNIQUE).
SHOW INDEX FROM usuarios WHERE Column_name = 'email';
SHOW INDEX FROM newsletter_suscriptores WHERE Column_name = 'mail';
-- Si "Non_unique" = 0 en ambas, ya está todo y no hay nada más que correr.

-- 3) SOLO si alguna tabla NO tenía índice único:
-- ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_email (email);
-- ALTER TABLE newsletter_suscriptores ADD UNIQUE KEY uq_newsletter_mail (mail);
