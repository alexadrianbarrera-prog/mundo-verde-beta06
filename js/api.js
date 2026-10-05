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

/* ── Sincroniza precios y stock de productos en la página ──
   (para que lo cargado desde el Admin aparezca solo, sin tocar ningún
   archivo HTML). Se ejecuta al cargar la página y también se puede
   llamar manualmente desde la consola del navegador. ── */
async function mvSincronizarPrecios() {
    const catalogo = document.getElementById('catalogo');
    const categoriaPagina = catalogo?.dataset.categoria;
    const tarjetasExistentes = document.querySelectorAll('.producto[id], .producto[data-id]');

    if (!tarjetasExistentes.length && !categoriaPagina) return;

    let productos;
    try {
        productos = await mvApi('/productos');
    } catch (err) {
        console.warn('[precios] No se pudieron sincronizar:', err.message);
        return;
    }

    const porCodigo = {};
    productos.forEach(p => { porCodigo[p.codigo.toUpperCase()] = p; });

    // 1) Sincroniza precio/estado de las tarjetas ya escritas a mano
    tarjetasExistentes.forEach(div => {
        const codigo = div.id || div.dataset.id;
        const prod = porCodigo[(codigo || '').toUpperCase()];
        if (prod) aplicarProductoATarjeta(div, prod);
    });

    // 2) Genera la tarjeta de cualquier producto activo de esta categoría
    // que todavía no exista en el HTML — así, lo cargado desde el Admin
    // aparece solo, sin tocar ningún archivo.
    if (categoriaPagina) {
        const idsExistentes = new Set(
            Array.from(tarjetasExistentes).map(div => (div.id || div.dataset.id || '').toUpperCase())
        );
        productos
            .filter(p => p.categoria === categoriaPagina && p.activo && !idsExistentes.has(p.codigo.toUpperCase()))
            .forEach(prod => catalogo.appendChild(crearTarjetaProducto(prod)));
    }
}

function aplicarProductoATarjeta(div, prod) {
    const elPrecio = div.querySelector('.precio');
    const boton = div.querySelector('button');

    if (!prod.activo) {
        if (elPrecio) { elPrecio.textContent = 'SIN STOCK'; elPrecio.classList.add('sin-stock'); }
        if (boton) { boton.disabled = true; boton.textContent = 'Sin stock'; boton.onclick = null; }
        return;
    }

    const precioReal = Number(prod.precio);
    if (elPrecio) {
        elPrecio.classList.remove('sin-stock');
        elPrecio.textContent = '$' + precioReal.toLocaleString('es-AR');
    }
    if (boton) {
        boton.disabled = false;
        const nombre = (div.querySelector('h3:not(.precio)')?.textContent || prod.nombre || '').trim();
        const img = div.querySelector('img');
        let imgSrc = img ? img.getAttribute('src') : '';
        if (!imgSrc) {
            const onclickOriginal = boton.getAttribute('onclick') || '';
            const match = onclickOriginal.match(/agregarCarrito\([^,]+,[^,]+,\s*'([^']*)'/);
            if (match) imgSrc = match[1];
        }
        boton.onclick = () => agregarCarrito(nombre, precioReal, imgSrc, prod.codigo);
    }
}

function crearTarjetaProducto(prod) {
    const precioReal = Number(prod.precio);
    const div = document.createElement('div');
    div.className = 'producto';
    div.dataset.id = prod.codigo.toLowerCase();

    const img = document.createElement('img');
    img.src = prod.imagen;
    img.alt = prod.nombre;

    const h3 = document.createElement('h3');
    const a = document.createElement('a');
    a.target = '_blank';
    a.textContent = prod.nombre;
    h3.appendChild(a);

    const p = document.createElement('p');
    p.className = 'precio';
    p.textContent = '$' + precioReal.toLocaleString('es-AR');

    const boton = document.createElement('button');
    boton.textContent = 'Agregar al carrito';
    boton.onclick = () => agregarCarrito(prod.nombre, precioReal, prod.imagen, prod.codigo);

    div.append(img, h3, p, boton);
    return div;
}

document.addEventListener('DOMContentLoaded', mvSincronizarPrecios);

/* ── Anuncios del banner superior (administrados desde Admin → Anuncios) ──
   Todas las hojas .html tienen el mismo banner (.anuncios-track). Este
   bloque lo rellena con los anuncios cargados en el panel que correspondan
   a la hoja actual, ordenados por la columna ORDEN.

   · El identificador de la hoja sale de <body data-pagina="..."> si existe;
     si no, se deduce del nombre del archivo con MV_PAGINA_POR_ARCHIVO.
     Los valores tienen que coincidir con PAGINAS_ANUNCIO de admin.html.
   · Requiere el endpoint público GET /anuncios?pagina=<id> en el backend,
     que devuelva los anuncios activos de esa hoja (los que digan "todas"
     o incluyan esa hoja). Si el backend devolviera todos, acá se vuelve
     a filtrar por las dudas.
   · Si el endpoint falla o no devuelve nada, queda el banner escrito a
     mano en el HTML (nunca se rompe la página). ── */
const MV_PAGINA_POR_ARCHIVO = {
    '': 'inicio',
    'index.html': 'inicio',
    '1_0_vivero.html': 'inicio',
    '1_1_plantas.html': 'plantas',
    '1_2_arbustos.html': 'arbustos',
    '1_2_aromaticas.html': 'aromaticas',
    '1_2_interior.html': 'interior',
    '1_2_plantines.html': 'plantines',
    '1_2_productos.html': 'productos',
    '2_0_talleres.html': 'talleres',
    '2_1_tall_exc.html': 'excursiones',
    '2_1_tall_tall.html': 'espacio',
    '2_1_tall_estcog.html': 'estimulacion',
    '2_1_tall_cer.html': 'ceramica',
    '2_1_tall_mos.html': 'mosaiquismo',
    '3_0_servicios.html': 'servicios',
    '3_1_serv_dom.html': 'serv_domicilio',
    '3_1_serv_com.html': 'serv_comercial',
    '3_1_serv_vir.html': 'serv_virtual',
    '3_1_serv_jar.html': 'serv_jardineria',
    '4_0_ramos.html': 'ramos',
    '5_0_newsletter.html': 'newsletter',
    'login.html': 'login',
    'recuperar.html': 'login',
    'registro.html': 'login',
    'restablecer.html': 'login',
};

function mvPaginaActual() {
    const deBody = document.body && document.body.dataset.pagina;
    if (deBody) return deBody;
    const archivo = (window.location.pathname.split('/').pop() || '').toLowerCase();
    return MV_PAGINA_POR_ARCHIVO[archivo] || null;
}

async function mvCargarAnuncios() {
    const track = document.querySelector('.anuncios-track');
    if (!track) return;
    const pagina = mvPaginaActual();
    if (!pagina) return;

    let lista;
    try {
        lista = await mvApi('/anuncios?pagina=' + encodeURIComponent(pagina));
    } catch (err) {
        console.warn('[anuncios] Se deja el banner del HTML:', err.message);
        return;
    }
    if (!Array.isArray(lista)) return;

    const aplica = (a) => {
        if (a.activo !== undefined && !Number(a.activo)) return false;
        if (typeof a.paginas !== 'string') return true; // el backend ya filtró
        const sel = a.paginas.split(',').map(x => x.trim());
        return sel.includes('todas') || sel.includes(pagina);
    };
    const textos = lista
        .filter(a => a && a.texto && aplica(a))
        .sort((x, y) => (Number(x.orden) || 0) - (Number(y.orden) || 0))
        .map(a => String(a.texto).trim())
        .filter(Boolean);

    if (!textos.length) return; // sin anuncios para esta hoja: queda el del HTML

    // Misma estructura que el HTML original: la lista y una copia oculta a
    // lectores de pantalla para que el loop de la animación no tenga corte.
    track.innerHTML = '';
    [false, true].forEach(copia => {
        textos.forEach(t => {
            const p = document.createElement('p');
            p.className = 'anuncios';
            if (copia) p.setAttribute('aria-hidden', 'true');
            p.textContent = t;
            track.appendChild(p);
        });
    });
}

document.addEventListener('DOMContentLoaded', mvCargarAnuncios);


