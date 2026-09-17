-- =====================================================================
-- Mundo Verde — Base de datos completa (MySQL 5.7+ / MariaDB, XAMPP)
-- Importar desde phpMyAdmin: Importar > elegir este archivo > Continuar
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS mundo_verde
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mundo_verde;

-- ---------------------------------------------------------------------
-- 1. usuarios — clientes registrados y administradores
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(100)    NOT NULL,
    email             VARCHAR(150)    NOT NULL UNIQUE,
    telefono          VARCHAR(30)     NULL,
    fecha_nacimiento  DATE            NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    rol               ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    activo            TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. sesiones — tokens de acceso (Authorization: Bearer <token>)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS sesiones;
CREATE TABLE sesiones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    token       CHAR(64)     NOT NULL UNIQUE,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en   DATETIME     NOT NULL,
    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. password_resets — tokens de un solo uso para "olvidé mi contraseña"
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    token       CHAR(64)     NOT NULL UNIQUE,
    usado       TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en   DATETIME     NOT NULL,
    CONSTRAINT fk_resets_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. productos — catálogo (plantas, insumos y servicios)
--    "codigo" es el mismo valor que usa el HTML/JS del front
--    (id="ARB-01" o data-id="serv-jar") para sincronizar precios.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS productos;
CREATE TABLE productos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(30)   NOT NULL UNIQUE,
    nombre      VARCHAR(150)  NOT NULL,
    categoria   VARCHAR(50)   NOT NULL,
    tipo        ENUM('producto','servicio') NOT NULL DEFAULT 'producto',
    precio      DECIMAL(10,2) NOT NULL,
    imagen      VARCHAR(255)  NULL,
    activo      TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. newsletter_suscriptores — suscripción + programa de referidos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS newsletter_suscriptores;
CREATE TABLE newsletter_suscriptores (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL,
    mail                VARCHAR(150) NOT NULL UNIQUE,
    origen              VARCHAR(50)  NULL,
    codigo_mio          VARCHAR(10)  NOT NULL UNIQUE,
    referido_por_id     INT UNSIGNED NULL,
    referidos_exitosos  INT UNSIGNED NOT NULL DEFAULT 0,
    acepta_tyc          TINYINT(1)   NOT NULL DEFAULT 1,
    activo              TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_newsletter_referido FOREIGN KEY (referido_por_id)
        REFERENCES newsletter_suscriptores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. pedidos — cabecera del pedido generado desde el checkout
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS pedidos;
CREATE TABLE pedidos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT UNSIGNED NULL,
    cliente_nombre   VARCHAR(100) NULL,
    cliente_email    VARCHAR(150) NULL,
    cliente_celular  VARCHAR(30)  NULL,
    forma_entrega    ENUM('retiro','envio') NOT NULL DEFAULT 'retiro',
    forma_pago       ENUM('efectivo','transferencia','tarjeta') NOT NULL DEFAULT 'efectivo',
    envio_domicilio  VARCHAR(200) NULL,
    envio_localidad  VARCHAR(100) NULL,
    envio_cp         VARCHAR(15)  NULL,
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL DEFAULT 0,
    comprobante_url  VARCHAR(255) NULL,
    estado           ENUM('pendiente','pagado','confirmado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. pedido_items — detalle de productos/servicios de cada pedido
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS pedido_items;
CREATE TABLE pedido_items (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id        INT UNSIGNED NOT NULL,
    producto_id      INT UNSIGNED NULL,
    codigo           VARCHAR(30)  NOT NULL,
    nombre           VARCHAR(150) NOT NULL,
    precio_unitario  DECIMAL(10,2) NOT NULL,
    cantidad         INT UNSIGNED NOT NULL,
    subtotal         DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- DATOS DE EJEMPLO
-- =====================================================================

-- Usuarios demo (contraseñas: admin -> "admin1234", cliente -> "cliente1234")
INSERT INTO usuarios (nombre, email, telefono, password_hash, rol) VALUES
('Admin Mundo Verde', 'admin@mundoverde.com', '5491100000000',
 '$2y$10$f2vmtH3Z6yqMQ7iUoeI9ce76/e3n3qDQi29iMSMgDdVsThR624zly', 'admin'),
('Cliente Demo', 'cliente@mundoverde.com', '5491111111111',
 '$2y$10$RASE37mDd9fxmltVAPmUYubXuSH2N5hX58YWukJ7JBAB7btKNQeB.', 'cliente');

-- Catálogo: 77 plantas/insumos + 4 servicios, extraídos de las páginas
-- reales del sitio (código, nombre, categoría, precio e imagen).
INSERT INTO productos (codigo, nombre, categoria, tipo, precio, imagen, activo) VALUES
('ARB-01', 'Salvia Guaranítica', 'arbustos', 'producto', 5000.00, 'img/arbustos/salvia-guaraniticaw.webp', 1),
('ARB-02', 'Salvia Leucantha', 'arbustos', 'producto', 2000.00, 'img/arbustos/salvia-leucantha.webp', 1),
('ARB-03', 'Salvia Greggii', 'arbustos', 'producto', 3000.00, 'img/arbustos/salvia-gregii.webp', 1),
('ARB-04', 'Rosa China', 'arbustos', 'producto', 5000.00, 'img/arbustos/rosa-china.webp', 1),
('ARB-04-2', 'Santa Rita', 'arbustos', 'producto', 2000.00, 'img/arbustos/santa-rita.webp', 1),
('ARB-05', 'Laurel de Jardín', 'arbustos', 'producto', 3000.00, 'img/arbustos/laurel-de-jardin.webp', 1),
('ARB-06', 'Abelia', 'arbustos', 'producto', 5000.00, 'img/arbustos/abeliaw.webp', 1),
('ARB-07', 'Ficus Blanco', 'arbustos', 'producto', 2000.00, 'img/arbustos/ficus_blancow.webp', 1),
('ARB-08', 'Ficus Negro', 'arbustos', 'producto', 3000.00, 'img/arbustos/ficus-negro.webp', 1),
('ARB-09', 'Azarero', 'arbustos', 'producto', 2000.00, 'img/arbustos/azarero.webp', 1),
('ARB-10', 'Aralia', 'arbustos', 'producto', 3000.00, 'img/arbustos/aralia.webp', 1),
('ARB-11', 'Evónimo', 'arbustos', 'producto', 5000.00, 'img/arbustos/evonimus.webp', 1),
('ARB-12', 'Polígala', 'arbustos', 'producto', 2000.00, 'img/arbustos/poligolaw.webp', 1),
('ARB-13', 'Corona de Novia', 'arbustos', 'producto', 3000.00, 'img/arbustos/corona-de-novia.webp', 1),
('ARO-01', 'Lavanda', 'aromaticas', 'producto', 5000.00, 'img/aromaticas/1_Lavandaw.webp', 1),
('ARO-02', 'Ruda', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/2_Ruda.webp', 1),
('ARO-03', 'Citronela', 'aromaticas', 'producto', 3000.00, 'img/aromaticas/3_Citronela.webp', 1),
('ARO-04', 'Orégano', 'aromaticas', 'producto', 5000.00, 'img/aromaticas/4_Oregano.webp', 1),
('ARO-05', 'Tomillo', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/5_Tomillow.webp', 1),
('ARO-06', 'Ciboulette', 'aromaticas', 'producto', 3000.00, 'img/aromaticas/6_Ciboulette.webp', 1),
('ARO-07', 'Albahaca', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/7_Albahacaw.webp', 1),
('ARO-08', 'Perejil', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/8_Perejilw.webp', 1),
('ARO-09', 'Romero', 'aromaticas', 'producto', 3000.00, 'img/aromaticas/9_Romero.webp', 1),
('ARO-10', 'Curry', 'aromaticas', 'producto', 4000.00, 'img/aromaticas/10_Curry.webp', 1),
('ARO-11', 'Salvia', 'aromaticas', 'producto', 3000.00, 'img/aromaticas/11_Salviaw.webp', 1),
('ARO-12', 'Menta', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/12_Menta-marinaw.webp', 1),
('ARO-13', 'Menta', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/13_Menta-melissaw.webp', 1),
('ARO-14', 'Hierba Buena', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/14_Hierba-buena.webp', 1),
('ARO-15', 'Menta Negra', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/15_Menta-negraw.webp', 1),
('ARO-16', 'Cedron', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/16_cedronw.webp', 1),
('ARO-17', 'Burrito', 'aromaticas', 'producto', 2000.00, 'img/aromaticas/17_Burritow.webp', 1),
('EXT-01', 'Alegrías', 'exterior', 'producto', 2000.00, 'img/exterior/02_Alegrias.webp', 1),
('EXT-02', 'Petunias', 'exterior', 'producto', 2000.00, 'img/exterior/03_Petunias.webp', 1),
('EXT-03', 'Clavelinas', 'exterior', 'producto', 2000.00, 'img/exterior/06_Clavelinas.webp', 1),
('EXT-04', 'Erika', 'exterior', 'producto', 2000.00, 'img/exterior/08_Erika.webp', 1),
('EXT-05', 'Crisantemos', 'exterior', 'producto', 2000.00, 'img/exterior/25_Crisantemos.webp', 1),
('EXT-06', 'Lazo de amor', 'exterior', 'producto', 2000.00, 'img/exterior/28_Lazo_de_amor.webp', 1),
('EXT-07', 'Cretonas', 'exterior', 'producto', 2000.00, 'img/exterior/29_Cretonas.webp', 1),
('EXT-08', 'Pileaw', 'exterior', 'producto', 2000.00, 'img/exterior/33_Pileaw.webp', 1),
('EXT-09', 'Paleta de pinoew', 'exterior', 'producto', 2000.00, 'img/exterior/37_Paleta_de_pinoew.webp', 1),
('EXT-10', 'Zephyranthus', 'exterior', 'producto', 2000.00, 'img/exterior/38_Zephyranthusw.webp', 1),
('INT-01', 'Potus', 'interior', 'producto', 25000.00, 'img/interior/potusw.webp', 1),
('INT-02', 'Potus Blanco', 'interior', 'producto', 22000.00, 'img/interior/potus-blanco.webp', 1),
('INT-03', 'Philodendro Brasil', 'interior', 'producto', 3000.00, 'img/interior/Philodendro-Brasilw.webp', 1),
('INT-04', 'Philodendro Limón', 'interior', 'producto', 5000.00, 'img/interior/philodendro-Limonw.webp', 1),
('INT-05', 'Philodendro Sanguíneo', 'interior', 'producto', 3000.00, 'img/interior/philodendro-sanguineo.webp', 1),
('INT-06', 'Aglonemas', 'interior', 'producto', 3000.00, 'img/interior/aglonemas.webp', 1),
('INT-07', 'Monsteras', 'interior', 'producto', 5000.00, 'img/interior/monsterasw.webp', 1),
('INT-08', 'Raphidospora', 'interior', 'producto', 4000.00, 'img/interior/raphidosporaw.webp', 1),
('INT-09', 'Orejas de Elefante', 'interior', 'producto', 5000.00, 'img/interior/orejas-de-elefantew.webp', 1),
('INT-10', 'Palo de Agua', 'interior', 'producto', 4000.00, 'img/interior/palo-de-aguaw.webp', 1),
('INT-11', 'Robusta', 'interior', 'producto', 6000.00, 'img/interior/robusta.webp', 1),
('QUI-01', 'Acondicionador', 'insumos', 'producto', 5000.00, 'img/articulos/01.acondici-biolo.webp', 1),
('QUI-02', 'Enraizador', 'insumos', 'producto', 4000.00, 'img/articulos/02.enreiza-natural.webp', 1),
('QUI-18', 'Jabón Potasico', 'insumos', 'producto', 4000.00, 'img/articulos/18.vene-jabonPotasico.webp', 1),
('QUI-13', 'Hortal', 'insumos', 'producto', 4000.00, 'img/articulos/13..Vene-Horm.webp', 1),
('QUI-03', 'Fertifox', 'insumos', 'producto', 4000.00, 'img/articulos/03.Fertilizantes.webp', 1),
('QUI-19', 'Babosil', 'insumos', 'producto', 5000.00, 'img/articulos/19.Babosil.webp', 1),
('QUI-20', 'Veneno', 'insumos', 'producto', 4000.00, 'img/articulos/20.VeloxanMosqui-moscas.webp', 1),
('QUI-04', 'Perlita', 'insumos', 'producto', 5000.00, 'img/articulos/04.perlita.webp', 1),
('QUI-07', 'Sustrato', 'insumos', 'producto', 5000.00, 'img/articulos/07.sustr-cul-prof.webp', 1),
('QUI-05', 'Terrafertil Cactus', 'insumos', 'producto', 5000.00, 'img/articulos/05.sustr-cac.webp', 1),
('QUI-06', 'Terrafertil Lombri', 'insumos', 'producto', 4000.00, 'img/articulos/06.mejo_suel.webp', 1),
('QUI-08', 'Terrafertil Orquideas', 'insumos', 'producto', 5000.00, 'img/articulos/08.sustr-orqui.webp', 1),
('QUI-09', 'Terrafertil Plantas', 'insumos', 'producto', 4000.00, 'img/articulos/09.sustr-pl-Int.webp', 1),
('QUI-10', 'Grow mix Pro', 'insumos', 'producto', 4000.00, 'img/articulos/10.sustr-prof.webp', 1),
('QUI-12', 'Glacoxan Herbicida', 'insumos', 'producto', 5000.00, 'img/articulos/12.herbicida.webp', 1),
('QUI-14', 'Glacoxan Homiguicida', 'insumos', 'producto', 4000.00, 'img/articulos/14.vene-hormg.webp', 1),
('QUI-17', 'Glacoxan Insecticida', 'insumos', 'producto', 5000.00, 'img/articulos/17.vene-intgusanos.webp', 1),
('QUI-22', 'Glacoxan Sistemico', 'insumos', 'producto', 4000.00, 'img/articulos/22.vene-acar-concentr.webp', 1),
('QUI-23', 'Glacoxan Acaricida', 'insumos', 'producto', 5000.00, 'img/articulos/23.vene-acaricida.webp', 1),
('QUI-24', 'Glacoxan Fungicida', 'insumos', 'producto', 4000.00, 'img/articulos/24.vene-fung.webp', 1),
('QUI-25', 'CapXan Insecticida', 'insumos', 'producto', 5000.00, 'img/articulos/25.vene-insec-caps.webp', 1),
('QUI-15', 'Myrmec Cebo', 'insumos', 'producto', 4000.00, 'img/articulos/15.vene-insecticida-hormidicida.webp', 1),
('QUI-16', 'DiatomiD', 'insumos', 'producto', 4000.00, 'img/articulos/16.vene-insecticida.webp', 1),
('QUI-11', 'Bobatox', 'insumos', 'producto', 4000.00, 'img/articulos/11.vene-BabosaYCarac.webp', 1),
('QUI-21', 'Terrafertil Vermiculita', 'insumos', 'producto', 5000.00, 'img/articulos/21.vermiculita-Acond-Suelos.webp', 1),
('SERV-VIR', 'Asesor. Virtual', 'servicios', 'servicio', 840000.00, 'img/servicios/1-asesoria-virtual/virtual-exterior.webp', 1),
('SERV-DOM', 'Asesor. Domicilio', 'servicios', 'servicio', 25000.00, 'img/servicios/2-asesoria-a-domicilio/domic4.webp', 1),
('SERV-COM', 'Asesor. Comercios', 'servicios', 'servicio', 840000.00, 'img/servicios/3-asesoria-locales-comerciales/Locales 1.webp', 1),
('SERV-JAR', 'Serv. Jardineria', 'servicios', 'servicio', 8000.00, 'img/servicios/4-service-jardineria/jardineria2.webp', 1);

-- Suscriptor demo (para probar /newsletter/mi-estado y el programa de referidos)
INSERT INTO newsletter_suscriptores (nombre, mail, origen, codigo_mio, acepta_tyc) VALUES
('Vecina Demo', 'vecina.demo@mail.com', 'Instagram', 'MVDEMO1', 1);

SET FOREIGN_KEY_CHECKS = 1;
