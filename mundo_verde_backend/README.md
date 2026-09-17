# Backend de Mundo Verde — PHP + MySQL (XAMPP)

Backend completo para el carrito de compras de Mundo Verde: catálogo de
productos/servicios, registro/login, checkout de pedidos, newsletter con
programa de referidos y un panel superadmin con CRUD genérico. Está armado
para conectar 1 a 1 con `js/api.js` de tu frontend, sin tener que tocar el
HTML/CSS existente.

## 1. Ubicar el proyecto dentro de XAMPP

Copiá **todo tu proyecto** (tu HTML/CSS/JS/img actuales) + esta carpeta
`mundo_verde_backend` dentro de `htdocs`:

- Windows: `C:\xampp\htdocs\mundo_verde\`
- Mac: `/Applications/XAMPP/htdocs/mundo_verde/`
- Linux: `/opt/lampp/htdocs/mundo_verde/`

Estructura final esperada:
```
htdocs/mundo_verde/
├── index.html, css/, js/, img/, login.html, registro.html, ...  ← tu frontend
└── mundo_verde_backend/
    ├── config/conexion.php
    ├── sql/mundo_verde.sql
    └── api/
        ├── .htaccess
        ├── index.php
        ├── helpers.php
        └── controllers/
            ├── auth.php
            ├── productos.php
            ├── newsletter.php
            ├── pedidos.php
            └── admin.php
```

> El nombre de la carpeta raíz (`mundo_verde` en el ejemplo) puede ser
> cualquiera; `js/api.js` ya usa una ruta **relativa** (`mundo_verde_backend/api`)
> así que no hay que tocar nada más.

## 2. Levantar Apache y MySQL

Abrí el Panel de Control de XAMPP y arrancá **Apache** y **MySQL** (verde).

## 3. Importar la base de datos

1. Andá a `http://localhost/phpmyadmin`.
2. Pestaña **Importar** → elegí `sql/mundo_verde.sql` → **Continuar**.
3. Esto crea la base `mundo_verde` con 7 tablas y los datos de ejemplo:
   - **81 productos/servicios reales**, extraídos de tus páginas de
     categorías (arbustos, aromáticas, exterior, interior, insumos,
     servicios) con sus códigos, precios e imágenes actuales.
   - 2 usuarios demo:
     - `admin@mundoverde.com` / `admin1234` (rol admin, panel superadmin)
     - `cliente@mundoverde.com` / `cliente1234` (rol cliente)
   - 1 suscriptor demo al newsletter con código de referido `MVDEMO1`.

> **Si ya tenías la base `mundo_verde` importada de antes** (no es una
> instalación nueva), no hace falta reimportar todo: corré una sola vez
> `sql/alter_pedidos.sql` desde phpMyAdmin/consola MySQL para sumar el
> comprobante de pago y el estado `pagado` a la tabla `pedidos`.

## 4. Habilitar mod_rewrite (URLs limpias)

XAMPP trae `mod_rewrite` habilitado por defecto, así que normalmente no hay
que hacer nada. Si al probar un endpoint te da error 404, revisá en
`xampp/apache/conf/httpd.conf` que estas líneas no estén comentadas:
```
LoadModule rewrite_module modules/mod_rewrite.so
...
AllowOverride All
```
(dentro del `<Directory "C:/xampp/htdocs">`) y reiniciá Apache.

## 5. Ajustar la conexión si hace falta

Por defecto XAMPP usa usuario `root` sin contraseña. Si le pusiste
contraseña a tu MySQL, editá `config/conexion.php` y cambiá `$pass`.

## 6. Probar los endpoints

Con Apache y MySQL corriendo:
```
http://localhost/mundo_verde/mundo_verde_backend/api/productos
```
Deberías ver un JSON con los 81 productos. Si ves un error de conexión,
revisá que la base se haya importado y que `conexion.php` tenga los datos
correctos.

## 7. Conectar con tu frontend

Ya está conectado: `js/api.js` (el que ya tenías) apunta a
`mundo_verde_backend/api` con ruta relativa, y `js/script.js` ya llama a
`mvAuth`, `mvNewsletter`, `mvProductos`, `mvPedidos` y `mvAdmin` en los
lugares correctos (login, registro, checkout, newsletter, panel admin). No
hace falta tocar el frontend, solo copiar esta carpeta al lado.

## 8. Endpoints disponibles

| Método | Ruta                                | Auth        | Descripción |
|--------|--------------------------------------|-------------|-------------|
| POST   | `/registro`                          | -           | Alta de cliente, devuelve `{token, usuario}` |
| POST   | `/login`                             | -           | Login, devuelve `{token, usuario}` |
| POST   | `/logout`                            | Bearer      | Invalida el token actual |
| GET    | `/usuario/me`                        | Bearer      | Datos del usuario logueado |
| POST   | `/recuperar`                         | -           | Genera link de recuperación (`dev_link` en la respuesta mientras no haya mailer configurado) |
| POST   | `/restablecer`                       | -           | Cambia la contraseña con el token del link |
| GET    | `/productos?categoria=`              | -           | Catálogo público (filtro opcional) |
| POST   | `/pedidos`                           | Bearer opc. | Crea un pedido (checkout); admite invitados |
| POST   | `/pedidos/{id}/comprobante`          | Bearer opc. | Adjunta el ticket de pago (multipart, campo `comprobante`); el pedido pasa a `pagado` automáticamente |
| GET    | `/pedidos`                           | Bearer      | Pedidos propios (o todos, si sos admin) |
| POST   | `/newsletter`                        | -           | Suscripción + programa de referidos |
| GET    | `/newsletter/mi-estado?mail=`        | -           | Código propio y referidos exitosos |
| GET    | `/newsletter`                        | Admin       | Listado completo |
| PATCH  | `/newsletter/{id}/toggle`            | Admin       | Activa/desactiva un suscriptor |
| DELETE | `/newsletter/{id}`                   | Admin       | Elimina un suscriptor |
| GET    | `/admin/tablas`                      | Admin       | Tablas disponibles para el panel |
| GET    | `/admin/{tabla}`                     | Admin       | Lista filas de una tabla whitelisteada |
| PATCH  | `/admin/{tabla}/{pk}`                | Admin       | Edita columnas editables de una fila |
| PATCH  | `/admin/{tabla}/{pk}/toggle`         | Admin       | Activa/desactiva (si la tabla tiene `activo`) |
| DELETE | `/admin/{tabla}/{pk}`                | Admin       | Elimina una fila |

Tablas habilitadas en el panel admin: `usuarios`, `productos`,
`newsletter_suscriptores`, `pedidos`, `pedido_items`.

## 9. Cómo está resuelto cada punto importante

- **Precios seguros**: al crear un pedido, el precio de cada ítem se toma
  siempre de la base de datos (nunca del que mande el navegador), así nadie
  puede alterar el total editando el JS del cliente.
- **Contraseñas**: se guardan con `password_hash()` (bcrypt) y nunca se
  devuelven en ninguna respuesta JSON.
- **SQL injection**: todas las queries usan `PDO::prepare()` con parámetros
  con nombre; el panel admin solo opera sobre una whitelist fija de tablas y
  columnas (nunca arma queries con nombres que vengan del usuario).
- **Sesiones**: en vez de sesiones PHP con cookies (que traen problemas de
  CORS entre dominios/puertos), se usa un token propio (`Authorization:
  Bearer <token>`) guardado en la tabla `sesiones`, tal como ya espera
  `js/api.js`.
- **Newsletter con referidos**: cada suscriptor recibe un `codigo_mio`
  único de 6 caracteres. Si alguien se suscribe usando el código de otra
  persona (`cod_ref`), se valida que exista y se le suma un referido
  exitoso a quien lo compartió.
- **Descuento en efectivo**: se replica en el backend la misma regla del
  frontend (10% off pagando en efectivo + retiro en local, desde $30.000),
  para que el total que queda guardado en la base sea siempre confiable.
- **Comprobante de pago → estado "pagado" automático**: `POST
  /pedidos/{id}/comprobante` recibe el ticket como `multipart/form-data`
  (campo `comprobante`, jpg/png/webp/pdf, hasta 5 MB), lo guarda en
  `mundo_verde_backend/uploads/comprobantes/` y actualiza el pedido con
  `comprobante_url` + `estado = 'pagado'` en la misma operación — no hace
  falta que el admin lo confirme a mano. Si el pedido ya estaba
  `confirmado`, `entregado` o `cancelado`, el comprobante se guarda igual
  pero el estado no se toca (no se pisan estados posteriores/finales). El
  archivo se valida por extensión **y** por `mime_content_type()` real
  (no solo lo que declara el navegador), y la carpeta `uploads/` tiene un
  `.htaccess` que desactiva la ejecución de PHP, para que nadie pueda
  subir un script disfrazado de imagen.

## 10. Próximos pasos sugeridos

- Configurar un mailer real (PHPMailer + SMTP, por ejemplo) para
  `/recuperar` y sacar el `dev_link` de la respuesta.
- Sumar `productos_crear` / `productos_editar` con subida de imágenes si
  después querés cargar productos nuevos con foto desde el panel (hoy el
  panel admin edita precio/nombre/categoría/estado de los que ya existen).
- Si el frontend termina sirviéndose desde otro dominio/puerto que el
  backend, restringir `Access-Control-Allow-Origin: *` (en `api/index.php`)
  a tu dominio real.

## 11. Buenas prácticas ya aplicadas (para tenerlas presentes al extender esto)

- Nunca armar queries concatenando strings con datos del usuario — siempre
  `prepare()` + parámetros con nombre.
- Nunca devolver `password_hash` en las respuestas JSON.
- Validar todos los datos que llegan del frontend antes de insertarlos.
- Las rutas de administración verifican el rol del token en cada request
  (`requerirAdmin()`), no confían en nada que mande el frontend.
