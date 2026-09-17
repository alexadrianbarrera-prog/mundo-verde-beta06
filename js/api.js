/* =====================================================
   API CLIENT — Mundo Verde
   Centraliza todas las llamadas al backend (PHP + MySQL vía XAMPP).
   Cambiar API_BASE si el backend corre en otra URL/puerto o si
   cambiás el nombre de la carpeta del proyecto dentro de htdocs.
   ===================================================== */

// Ruta relativa: funciona sin importar cómo se llame la carpeta del
// proyecto dentro de htdocs, siempre que mundo_verde_backend esté
// al lado de este index.html/login.html/etc (ver README_BACKEND.md).
const API_BASE = 'mundo_verde_backend/api';

function mvGetToken() {
    return localStorage.getItem('mv_token');
}
function mvSetSesion(token, usuario) {
    localStorage.setItem('mv_token', token);
    localStorage.setItem('mv_usuario', JSON.stringify(usuario));
}
function mvCerrarSesion() {
    localStorage.removeItem('mv_token');
    localStorage.removeItem('mv_usuario');
}
function mvUsuarioActual() {
    try { return JSON.parse(localStorage.getItem('mv_usuario')) || null; }
    catch { return null; }
}

async function mvApi(path, { method = 'GET', body, auth = false } = {}) {
    // FormData (usado para mandar el comprobante de pago junto con el
    // pedido) no se debe serializar como JSON ni llevar Content-Type
    // manual: el navegador arma el boundary del multipart solo.
    const esFormData = (typeof FormData !== 'undefined') && body instanceof FormData;

    const headers = {};
    if (!esFormData) headers['Content-Type'] = 'application/json';
    if (auth) {
        const token = mvGetToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
    }
    let res;
    try {
        res = await fetch(API_BASE + path, {
            method,
            headers,
            body: esFormData ? body : (body ? JSON.stringify(body) : undefined),
        });
    } catch (err) {
        // El backend no está corriendo / no responde
        throw new Error('No se pudo conectar con el servidor. ¿Está corriendo el backend?');
    }
    let data = null;
    try { data = await res.json(); } catch { /* respuesta vacía o no-JSON */ }
    if (!res.ok) {
        throw new Error((data && data.error) || 'Ocurrió un error inesperado');
    }
    if (data === null) {
        throw new Error('El servidor respondió algo inesperado. Probá de nuevo en unos segundos.');
    }
    return data;
}

/* ── Usuarios ─────────────────────────────────────────── */
const mvAuth = {
    registro: (datos) => mvApi('/registro', { method: 'POST', body: datos }),

    login: (email, password) =>
        mvApi('/login', { method: 'POST', body: { email, password } }),

    logout: () => mvApi('/logout', { method: 'POST', auth: true }),

    me: () => mvApi('/usuario/me', { auth: true }),

    recuperar: (email) => mvApi('/recuperar', { method: 'POST', body: { email } }),

    restablecer: (token, password) =>
        mvApi('/restablecer', { method: 'POST', body: { token, password } }),
};

/* ── Newsletter ───────────────────────────────────────── */
const mvNewsletter = {
    suscribir: (payload) => mvApi('/newsletter', { method: 'POST', body: payload }),
    listar: () => mvApi('/newsletter', { auth: true }),
    toggle: (id) => mvApi(`/newsletter/${id}/toggle`, { method: 'PATCH', auth: true }),
    eliminar: (id) => mvApi(`/newsletter/${id}`, { method: 'DELETE', auth: true }),
    miEstado: (mail) => mvApi(`/newsletter/mi-estado?mail=${encodeURIComponent(mail)}`),
};

/* ── Productos ────────────────────────────────────────── */
const mvProductos = {
    listar: (categoria) => mvApi('/productos' + (categoria ? `?categoria=${encodeURIComponent(categoria)}` : '')),
};

/* ── Pedidos ──────────────────────────────────────────── */
const mvPedidos = {
    // Si viene archivoComprobante, el pedido se manda como multipart/form-data
    // (campo "datos" con el JSON de siempre + campo "comprobante" con el
    // archivo). Sin archivo, se manda como JSON puro (ej: pago en efectivo).
    crear: (payload, archivoComprobante) => {
        if (archivoComprobante) {
            const fd = new FormData();
            fd.append('datos', JSON.stringify(payload));
            fd.append('comprobante', archivoComprobante);
            return mvApi('/pedidos', { method: 'POST', body: fd, auth: !!mvGetToken() });
        }
        return mvApi('/pedidos', { method: 'POST', body: payload, auth: !!mvGetToken() });
    },
    listar: () => mvApi('/pedidos', { auth: true }),
};

/* ── Pagos con tarjeta (Mercado Pago) ─────────────────────
   El Card Payment Brick ya generó el token de la tarjeta en el
   navegador del cliente (ver script.js → procesarPagoConTarjeta).
   Acá solo se lo mandamos al backend, que es quien de verdad
   cobra usando el Access Token PRIVADO de Mercado Pago del lado
   del servidor — a este archivo nunca le llega el número de
   tarjeta completo ni el CVV.
   Requiere el endpoint POST /pagos/tarjeta en mundo_verde_backend
   (ver ejemplo de referencia en pagos.php). ── */
const mvPagos = {
    pagarConTarjeta: (datos) => mvApi('/pagos/tarjeta', { method: 'POST', body: datos, auth: !!mvGetToken() }),
};

/* ── Super Admin (CRUD genérico sobre las tablas de la BD) ── */
const mvAdmin = {
    tablas: () => mvApi('/admin/tablas', { auth: true }),
    listar: (tabla) => mvApi(`/admin/${tabla}`, { auth: true }),
    crear: (tabla, datos) =>
        mvApi(`/admin/${tabla}`, { method: 'POST', body: datos, auth: true }),
    editar: (tabla, pk, cambios) =>
        mvApi(`/admin/${tabla}/${encodeURIComponent(pk)}`, { method: 'PATCH', body: cambios, auth: true }),
    toggle: (tabla, pk) =>
        mvApi(`/admin/${tabla}/${encodeURIComponent(pk)}/toggle`, { method: 'PATCH', auth: true }),
    eliminar: (tabla, pk) =>
        mvApi(`/admin/${tabla}/${encodeURIComponent(pk)}`, { method: 'DELETE', auth: true }),
};

/* ── Actualiza el botón de login del header según sesión ── */
document.addEventListener('DOMContentLoaded', () => {
    const btnLogin = document.querySelector('.btn-login');
    if (!btnLogin) return;
    const usuario = mvUsuarioActual();

    if (usuario) {
        // Envolvemos el botón para poder anclar el menú desplegable debajo
        const wrapper = document.createElement('div');
        wrapper.className = 'user-menu-wrapper';
        btnLogin.parentNode.insertBefore(wrapper, btnLogin);
        wrapper.appendChild(btnLogin);

        btnLogin.textContent = usuario.nombre;
        btnLogin.title = usuario.nombre;
        btnLogin.classList.add('logueado');

        const dropdown = document.createElement('div');
        dropdown.className = 'user-dropdown';
        dropdown.innerHTML =
            (usuario.rol === 'admin' ? '<a href="admin.html">Panel Superadmin</a>' : '') +
            '<a href="#" id="mv-btn-logout">Cerrar sesión</a>';
        wrapper.appendChild(dropdown);

        btnLogin.onclick = (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('abierto');
        };
        document.addEventListener('click', () => dropdown.classList.remove('abierto'));

        dropdown.querySelector('#mv-btn-logout').addEventListener('click', async (e) => {
            e.preventDefault();
            try { await mvAuth.logout(); } catch (err) { /* token vencido: no importa, igual cerramos local */ }
            mvCerrarSesion();

            // Importante: NO se borra el carrito del usuario (mvCarrito_<email>).
            // Solo "cerramos" lo que se ve: el panel y el contador vuelven al
            // estado de invitado (mvCarrito_guest), que normalmente está vacío.
            // Los artículos del usuario quedan guardados y se recuperan solos
            // la próxima vez que inicie sesión, siempre que no haya
            // completado la compra (en ese caso el carrito ya se vació al
            // confirmar el pedido).
            if (typeof cerrarCarrito === 'function') cerrarCarrito();
            if (typeof actualizarBadge === 'function') actualizarBadge();

            window.location.href = 'index.html';
        });
    } else {
        btnLogin.textContent = '👤';
        btnLogin.title = 'Iniciar sesión';
        btnLogin.onclick = () => { window.location.href = 'login.html'; };
    }
});


/* ═══════════════════════════════════════════════════════════
   SINCRONIZAR PRECIOS con la base de datos
   Cada tarjeta de producto en el HTML tiene un precio escrito
   como texto fijo (para que la página cargue rápido y funcione
   aunque el backend esté apagado). Esta función pisa ese precio
   con el valor real de /api/productos apenas carga la página,
   así lo que se edita en el panel Admin se refleja automáticamente
   sin tener que tocar el HTML de cada categoría a mano.
   ═══════════════════════════════════════════════════════════ */
async function mvSincronizarPrecios() {
    // Algunas páginas usan <div class="producto" id="QUI-01">,
    // otras (servicios) usan <div class="producto" data-id="serv-jar">.
    const tarjetas = document.querySelectorAll('.producto[id], .producto[data-id]');
    if (!tarjetas.length) return; // esta página no tiene productos (ej: login, admin)

    let productos;
    try {
        productos = await mvApi('/productos');
    } catch (err) {
        // Backend apagado o sin conexión: se queda con los precios
        // escritos en el HTML en vez de romper la página.
        console.warn('[precios] No se pudieron sincronizar (se muestran los precios por defecto):', err.message);
        return;
    }

    const porCodigo = {};
    productos.forEach(p => { porCodigo[p.codigo.toUpperCase()] = p; });

    tarjetas.forEach(div => {
        const codigo = div.id || div.dataset.id;
        // El código en la base siempre se guarda en mayúsculas (ARB-01), pero
        // varias páginas escriben el atributo en minúsculas (arb-01, serv-jar).
        // Sin este .toUpperCase() la búsqueda fallaba en silencio para esas
        // categorías y el precio nunca se actualizaba.
        const prod = porCodigo[(codigo || '').toUpperCase()];
        if (!prod) return; // código de la tarjeta no existe (o está inactivo) en la base

        const elPrecio = div.querySelector('.precio');
        const boton = div.querySelector('button');

        // Producto marcado como inactivo/oculto en el admin: se muestra
        // "SIN STOCK" en vez del precio y se bloquea el botón de compra.
        if (!prod.activo) {
            if (elPrecio) {
                elPrecio.textContent = 'SIN STOCK';
                elPrecio.classList.add('sin-stock');
            }
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Sin stock';
                boton.onclick = null;
            }
            return;
        }

        const precioReal = prod.precio;

        // 1) Actualiza el precio visible, si esta tarjeta lo muestra
        if (elPrecio) {
            elPrecio.classList.remove('sin-stock');
            elPrecio.textContent = '$' + precioReal.toLocaleString('es-AR');
        }

        // 2) Actualiza qué precio se manda al carrito al hacer clic.
        // Reemplazamos el onclick inline por un listener propio (más
        // seguro que intentar reescribir el string del atributo HTML).
        if (boton) {
            boton.disabled = false;
            const nombre = (div.querySelector('h3')?.textContent || prod.nombre || '').trim();
            const img = div.querySelector('img');
            const imgSrc = img ? img.getAttribute('src') : '';
            boton.onclick = () => agregarCarrito(nombre, precioReal, imgSrc, prod.codigo);
        }
    });
}

document.addEventListener('DOMContentLoaded', mvSincronizarPrecios);
