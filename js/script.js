/* =====================================================
   VIVERO MUNDO VERDE — script.js
   ===================================================== */

/* ── Botón logo (solo en index.html) ────────────────── */
setTimeout(() => {
    const btnLogo = document.getElementById('btn-logo');
    if (btnLogo) btnLogo.classList.add('visible');
}, 5000);

/* ── Marcar link activo en nav ───────────────────────── */
document.querySelectorAll('nav a').forEach(link => {
    if (link.href === window.location.href) link.classList.add('activo');
});

/* Si la subcategoría activa (ej: Arbustos) pertenece a un desplegable
   (Plantas, Talleres, Servicios), el link padre del desplegable
   también se marca como activo, para que persista en todas las
   páginas de esa sección. */
document.querySelectorAll('.nav_item--dropdown').forEach(item => {
    const subActivo = item.querySelector('.nav_sublink.activo');
    if (subActivo) {
        const padre = item.querySelector('.nav_row > .nav_link');
        if (padre) padre.classList.add('activo');
    }
});

/* ── Audio de fondo (index.html) ───────────────────────── */
window.addEventListener('load', () => {
  const audio   = document.getElementById('bgAudio');
  const btnLogo = document.getElementById('btn-logo');
  if (!audio) return;  // si no estamos en index.html, no hace nada

  audio.volume = 0.9;

  // Los navegadores bloquean el audio CON sonido si no hubo interacción
  // previa del usuario, pero sí permiten autoplay muted. Por eso arranca
  // muteado junto con el video, y en el primer gesto del usuario (click,
  // touch o scroll) se le saca el mute para que la música "ya esté sonando".
  audio.muted = true;
  audio.play().catch(() => {});

  const avisoSonido = document.getElementById('aviso-sonido');

  const activarSonido = () => {
    audio.muted = false;
    audio.play().catch(() => {});
    if (avisoSonido) {
        avisoSonido.classList.add('oculto');
        setTimeout(() => avisoSonido.remove(), 700);
    }
  };
  document.addEventListener('click', activarSonido, { once: true });
  document.addEventListener('touchstart', activarSonido, { once: true });
  document.addEventListener('scroll', activarSonido, { once: true });

  // El cartel ahora SÍ captura el toque (para no dejarlo pasar al logo
  // que puede estar debajo). Se le corta la propagación para que ese
  // toque active el sonido pero nunca dispare la navegación del logo.
  if (avisoSonido) {
    avisoSonido.addEventListener('click', (e) => {
        e.stopPropagation();
        activarSonido();
    });
    avisoSonido.addEventListener('touchstart', (e) => {
        e.stopPropagation();
        activarSonido();
    });
  }

  // Al hacer click en el logo, detener y navegar
  if (btnLogo) {
    btnLogo.addEventListener('click', (e) => {
      e.preventDefault();
      audio.pause();
      audio.currentTime = 0;
      window.location.href = btnLogo.href;
    });
  }
});



/* ══════════════════════════════════════════════════════
   Popup promocional (solo en 1.0.vivero.html)
   ══════════════════════════════════════════════════════ */
const promoPopup = document.getElementById('promoPopup');
const closePromo = document.getElementById('closePromo');

// Devuelve true si hay que mostrar el popup, false si el mail ya figura
// en la tabla de newsletter. Se ignora por completo el mail de la
// cuenta/login: son cosas distintas (loguearse no es estar suscripto),
// así que solo se consulta contra la columna de newsletter en la base.
async function mvDebeMostrarPopup() {
    // Mail que usó este visitante la última vez que se suscribió desde
    // este navegador (lo guardamos nosotros al mandar el formulario).
    const mail = localStorage.getItem('mv_newsletter_mail');
    if (!mail) return true; // nunca se suscribió desde acá: mostrar popup

    try {
        // Pregunta directo a la tabla de newsletter si ese mail sigue ahí.
        await mvNewsletter.miEstado(mail);
        return false; // figura en la base: no mostrar
    } catch (err) {
        // Ya no figura (lo borró el admin, etc.): se limpia el localStorage
        // guardado y se vuelve a mostrar el popup.
        localStorage.removeItem('mv_newsletter_mail');
        return true;
    }
}

if (promoPopup) {
    mvDebeMostrarPopup().then((mostrar) => {
        if (mostrar) setTimeout(() => promoPopup.classList.add('show'), 1000);
    });
}
if (closePromo) {
    closePromo.addEventListener('click', () => promoPopup.classList.remove('show'));
}

/* ========================================================
   Buscador: ver js/buscador-global.js
   (ese script reemplaza #searchBtn/#searchInput por clones y
   agrega sus propios listeners; cualquier listener puesto acá
   quedaría atado a nodos ya descartados del DOM)
   ══════════════════════════════════════════════════════ */


/* ══════════════════════════════════════════════════════
   CARRITO — localStorage ****HACER BD****
   (Forma parte del HEADER de todas las páginas para facilitar la navegación)
   ══════════════════════════════════════════════════════ */
/*const STORAGE_KEY = 'mvCarrito';

function cargarCarrito() {
    try { 
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
    catch { 
        return []; }
}

function guardarCarrito(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}*/
/* ══════════════════════════════════════════════════════
   CARRITO POR USUARIO
   Cada cliente tiene su propio carrito
   ══════════════════════════════════════════════════════ */

const STORAGE_KEY_GUEST = 'mvCarrito_guest';


/* ------------------------------------------------------
   Obtener la clave del carrito actual
   ------------------------------------------------------ */

function obtenerClaveCarrito() {
    let usuario = null;
    try {
        if (typeof mvUsuarioActual === 'function') {
            usuario = mvUsuarioActual();
        }
    } catch (error) {
        usuario = null;
    }

    /*
     * Usuario logueado:
     * usamos el email como identificador único.
     */

    if (usuario && usuario.email) {
        const identificador = usuario.email
            .trim()
            .toLowerCase();
        return 'mvCarrito_' + identificador;
    }

    /*
     * Usuario no logueado:
     * carrito temporal de invitado.
     */

    return STORAGE_KEY_GUEST;
}

/* ------------------------------------------------------
   Cargar carrito
   ------------------------------------------------------ */
function cargarCarrito() {
    try {
        const clave = obtenerClaveCarrito();
        return JSON.parse(
            localStorage.getItem(clave)
        ) || [];
    } catch (error) {
        console.error(
            'Error cargando carrito:',
            error
        );
        return [];
    }
}


/* ------------------------------------------------------
   Guardar carrito
   ------------------------------------------------------ */
function guardarCarrito(items) {

    try {
        const clave = obtenerClaveCarrito();
        localStorage.setItem(
            clave,
            JSON.stringify(items)
        );
    } catch (error) {
        console.error(
            'Error guardando carrito:',
            error
        );
    }
}
/*----------------------------------------------------------*/

function calcularTotal(items) {
    return items.reduce((s, i) => s + i.precio * i.qty, 0);
}
function actualizarBadge() {
    const total = cargarCarrito().reduce((s, i) => s + i.qty, 0);
    document.querySelectorAll('.carrito-badge').forEach(b => {
        b.textContent = total;
        b.style.display = total > 0 ? 'flex' : 'none';
    });
}

function mostrarToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('visible');
    setTimeout(() => t.classList.remove('visible'), 2200);
}

function agregarCarrito(nombre, precio, imgSrc, codigo) {
    const items = cargarCarrito();
    const idx = items.findIndex(i => i.nombre === nombre);
    if (idx >= 0) {
        items[idx].qty++;
    } else {
        items.push({ nombre, precio, img: imgSrc || '', codigo: codigo || '', qty: 1, fragil: esFragil(codigo) });
    }
    guardarCarrito(items);
    actualizarBadge();
    mostrarToast('🌿 ' + nombre + ' agregado al carrito');
    if (document.getElementById('carrito-panel')?.classList.contains('abierto')) {
        renderPaso1();
    }
}

function cambiarCantidad(nombre, delta) {
    const items = cargarCarrito();
    const idx = items.findIndex(i => i.nombre === nombre);
    if (idx < 0) return;
    items[idx].qty += delta;
    if (items[idx].qty <= 0) items.splice(idx, 1);
    guardarCarrito(items);
    actualizarBadge();
    renderPaso1();
}

function vaciarCarrito() {
    guardarCarrito([]);
    actualizarBadge();
    renderPaso1();
}

/* ------------------------------------------------------
   Migrar carrito de invitado al carrito del usuario
   Se llama justo después de loguearse/registrarse, ANTES
   de redirigir. Sin esto, lo que el usuario agregó antes
   de loguearse "desaparece" (queda guardado bajo la clave
   guest, pero el contador ahora lee la clave del usuario).
   ------------------------------------------------------ */
function migrarCarritoGuest() {
    try {
        const guest = JSON.parse(localStorage.getItem(STORAGE_KEY_GUEST)) || [];
        if (!guest.length) return;

        const items = cargarCarrito(); // ya lee la clave del usuario recién logueado
        guest.forEach(gItem => {
            const idx = items.findIndex(i => i.nombre === gItem.nombre);
            if (idx >= 0) {
                items[idx].qty += gItem.qty;
            } else {
                items.push(gItem);
            }
        });
        guardarCarrito(items);
        localStorage.removeItem(STORAGE_KEY_GUEST);
    } catch (error) {
        console.error('Error migrando carrito de invitado:', error);
    }
}

/* ------------------------------------------------------
   Recordatorio de carrito pendiente al loguearse
   Se llama justo después de mvSetSesion()/migrarCarritoGuest().
   Como el login redirige a otra página (1_0_vivero.html), no se
   puede mostrar el aviso en el mismo momento: se guarda un flag
   en sessionStorage con la cantidad, y la página de destino lo
   lee una sola vez al cargar (ver verificarRecordatorioCarrito).
   Si el carrito está vacío (o la compra ya se completó antes),
   no se guarda nada y por lo tanto no aparece ningún aviso.
   ------------------------------------------------------ */
const RECORDATORIO_KEY = 'mv_recordatorio_carrito';

function marcarRecordatorioCarrito() {
    try {
        const cantidad = cargarCarrito().reduce((s, i) => s + i.qty, 0);
        if (cantidad > 0) {
            sessionStorage.setItem(RECORDATORIO_KEY, String(cantidad));
        } else {
            sessionStorage.removeItem(RECORDATORIO_KEY);
        }
    } catch (error) {
        console.error('Error marcando recordatorio de carrito:', error);
    }
}

function verificarRecordatorioCarrito() {
    let cantidad = 0;
    try {
        cantidad = parseInt(sessionStorage.getItem(RECORDATORIO_KEY), 10) || 0;
        sessionStorage.removeItem(RECORDATORIO_KEY); // se muestra una sola vez
    } catch (error) {
        return;
    }
    if (cantidad > 0) mostrarRecordatorioCarrito(cantidad);
}

function mostrarRecordatorioCarrito(cantidad) {
    // Crea el aviso si todavía no existe en esta página
    let aviso = document.getElementById('recordatorio-carrito');
    if (!aviso) {
        aviso = document.createElement('div');
        aviso.id = 'recordatorio-carrito';
        aviso.className = 'recordatorio-carrito';
        aviso.innerHTML = `
            <span class="recordatorio-carrito-icono">🛒</span>
            <span class="recordatorio-carrito-texto"></span>
            <button type="button" class="recordatorio-carrito-btn">Ver carrito</button>
            <button type="button" class="recordatorio-carrito-cerrar" aria-label="Cerrar">✕</button>
        `;
        document.body.appendChild(aviso);

        aviso.querySelector('.recordatorio-carrito-btn').addEventListener('click', () => {
            aviso.classList.remove('visible');
            if (typeof abrirCarrito === 'function') abrirCarrito();
        });
        aviso.querySelector('.recordatorio-carrito-cerrar').addEventListener('click', () => {
            aviso.classList.remove('visible');
        });
    }

    const plural = cantidad === 1 ? 'artículo' : 'artículos';
    aviso.querySelector('.recordatorio-carrito-texto').textContent =
        `Tenés ${cantidad} ${plural} guardado${cantidad === 1 ? '' : 's'} en tu carrito`;

    requestAnimationFrame(() => aviso.classList.add('visible'));
    clearTimeout(aviso._timeoutId);
    aviso._timeoutId = setTimeout(() => aviso.classList.remove('visible'), 7000);
}

/* ──==============================================
 Abrir / cerrar panel del carrito (checkout)
=================================================== */
function abrirCarrito() {
    irAPaso(1);
    document.getElementById('carrito-panel')?.classList.add('abierto');
    document.getElementById('carrito-overlay')?.classList.add('abierto');
}

function cerrarCarrito() {
    document.getElementById('carrito-panel')?.classList.remove('abierto');
    document.getElementById('carrito-overlay')?.classList.remove('abierto');
}

document.getElementById('carrito-overlay')?.addEventListener('click', cerrarCarrito);

/* ══════════════════════════════════════════════════════
   INICIAR PAGO — CHECKOUT — stepper de 4 pasos
   ══════════════════════════════════════════════════════

   Estructura HTML esperada en el carrito-panel:

   <div id="checkout-paso-1">…</div>   ← items + total
   <div id="checkout-paso-2">…</div>   ← forma de entrega (retiro / envío)
   <div id="checkout-paso-3">…</div>   ← forma de pago (transferencia / tarjeta, con comprobante obligatorio)
   <div id="checkout-paso-4">…</div>   ← confirmación / resumen
   <div id="checkout-paso-5">…</div>   ← pantalla "pedido enviado"

   El panel ya tiene:
     .carrito-header  →  se conserva (título cambia por paso)
     #checkout-steps  →  barra de pasos (pasos 2, 3 y 4)
   ══════════════════════════════════════════════════════ */

const ZONAS_ENVIO_GRATIS = ['quilmes', 'berazategui', 'quilmes centro', 'berazategui centro'];
const UMBRAL_ENVIO_GRATIS = 150000;
const WA_NUMBER = '5491168316730';
const MAIL_DESTINO = 'viveunmundoverde@gmail.com.ar'; // ← reemplazar con el mail real
const MERCADOPAGO_LINK = 'https://mpago.la/TU-LINK-AQUI'; // ← reemplazar con el link real de Mercado Pago

/* ── Productos frágiles / plantas vivas ──────────────────
   Códigos (los mismos que usa cada tarjeta de producto:
   <div class="producto" id="ARB-01"> o data-id="serv-jar")
   que, si están en el carrito, disparan el aviso recomendando
   retiro en local en vez de envío a domicilio. Completar con
   los códigos reales del catálogo (plantas vivas, macetas de
   cerámica/barro, etc). Mejor a futuro: que /api/productos
   devuelva un campo "fragil" y sacar este hardcodeo de acá. ── */
const CODIGOS_FRAGILES = new Set([
    // Ejemplos — reemplazar por los códigos reales:
    // 'ARB-01', 'QUI-01', 'PROD-MACBAR-01',
]);

function esFragil(codigo) {
    return CODIGOS_FRAGILES.has((codigo || '').toUpperCase());
}

/* ── Mercado Pago — Card Payment Brick (pago con tarjeta) ──
   MP_PUBLIC_KEY es la clave PÚBLICA (no la privada/Access Token,
   esa va solo en el backend). Se consigue en:
   https://www.mercadopago.com.ar/developers/panel/app
   Mientras esté vacía o con el valor de ejemplo, el paso 3
   muestra el aviso de "Vista previa" y no cobra nada de verdad. ── */
const MP_PUBLIC_KEY = ''; // ← pegar acá la Public Key real (empieza con APP_USR- o TEST-)

let mpBrickController = null;   // instancia del Brick ya montado (se reutiliza)
let pagoTarjetaAprobado = null; // {payment_id, status} una vez que Mercado Pago confirma el pago

let pasoActual = 1;

function irAPaso(n) {
    pasoActual = n;

    // Mostrar/ocultar pasos
    [1, 2, 3, 4].forEach(p => {
        const el = document.getElementById('checkout-paso-' + p);
        if (el) el.style.display = p === n ? 'flex' : 'none';
    });

    // Actualizar barra de pasos
    actualizarBarraPasos(n);

    // Actualizar título del header
    const titulo = document.querySelector('.carrito-header h2');
    if (titulo) {
        const titulos = { 1: '🛒 Tu carrito', 2: 'Forma de entrega', 3: 'Forma de pago', 4: 'Confirmar pedido' };
        titulo.textContent = titulos[n] || '🛒 Tu carrito';
    }

    // Botón volver: solo visible del paso 2 en adelante
    const btnVolver = document.getElementById('checkout-volver');
    if (btnVolver) btnVolver.style.display = n > 1 ? 'block' : 'none';

    // Renderizar contenido del paso
    if (n === 1) renderPaso1();
    if (n === 2) renderPaso2Entrega();
    if (n === 3) renderPaso3Pago();
    if (n === 4) renderPaso4Confirmar();
}

function actualizarBarraPasos(pasoActivo) {
    const barra = document.getElementById('checkout-steps');
    if (!barra) return;
    barra.style.display = pasoActivo > 1 ? 'flex' : 'none';

    [1, 2, 3, 4].forEach(p => {
        const dot = document.getElementById('step-dot-' + p);
        if (!dot) return;
        dot.classList.remove('step-done', 'step-active', 'step-pending');
        if (p < pasoActivo) dot.classList.add('step-done');
        else if (p === pasoActivo) dot.classList.add('step-active');
        else dot.classList.add('step-pending');
    });
}

/* ── PASO 1: Carrito ─────────────────────────────────── */
function renderPaso1() {
    const lista = document.getElementById('carrito-lista');
    const totalEl = document.getElementById('carrito-total');
    const footerEl = document.getElementById('carrito-footer-p1');
    if (!lista) return;

    const items = cargarCarrito();

    if (items.length === 0) {
        lista.innerHTML = '<div class="carrito-vacio">Tu carrito está vacío 🌱</div>';
        if (totalEl) totalEl.textContent = '$0';
        if (footerEl) footerEl.style.display = 'none';
        return;
    }

    if (footerEl) footerEl.style.display = 'block';

    lista.innerHTML = items.map(item => `
        <div class="carrito-item">
            <img src="${item.img}" alt="${item.nombre}" onerror="this.style.opacity='0'">
            <div class="carrito-item-info">
                <div class="carrito-item-nombre">
                    ${item.nombre}
                    ${item.fragil ? '<span class="tag-fragil">🌱 Frágil</span>' : ''}
                </div>
                <div class="carrito-item-precio">$${(item.precio * item.qty).toLocaleString('es-AR')}</div>
            </div>
            <div class="carrito-item-controles">
                <button onclick="cambiarCantidad('${item.nombre.replace(/'/g, "\\'")}', -1)">−</button>
                <span class="carrito-item-qty">${item.qty}</span>
                <button onclick="cambiarCantidad('${item.nombre.replace(/'/g, "\\'")}', 1)">+</button>
                <button class="btn-eliminar" onclick="cambiarCantidad('${item.nombre.replace(/'/g, "\\'")}', -${item.qty})" title="Eliminar">×</button>
            </div>
        </div>
    `).join('');

    const total = calcularTotal(items);
    if (totalEl) totalEl.textContent = '$' + total.toLocaleString('es-AR');

    // Mostrar aviso de envío gratis si el total está cerca del umbral
    const avisoEnvio = document.getElementById('aviso-envio-gratis');
    if (avisoEnvio) {
        const falta = UMBRAL_ENVIO_GRATIS - total;
        if (total >= UMBRAL_ENVIO_GRATIS) {
            avisoEnvio.textContent = '🚚 ¡Llegaste al envío gratis si estas en Quilmes Centro o Berazategui!';
            avisoEnvio.style.display = 'block';
        } else if (falta <= 20000) {
            avisoEnvio.textContent = `🚚 Te faltan $${falta.toLocaleString('es-AR')} para envío gratis`;
            avisoEnvio.style.display = 'block';
        } else {
            avisoEnvio.style.display = 'none';
        }
    }

    // Aviso de productos frágiles / plantas vivas
    const avisoFragil = document.getElementById('aviso-fragil-carrito');
    if (avisoFragil) {
        avisoFragil.style.display = items.some(i => i.fragil) ? 'block' : 'none';
    }
}

/* ── PASO 2: Forma de entrega ────────────────────────── */
function renderPaso2Entrega() {
    const entregaSeleccionada = document.querySelector('input[name="formaEntrega"]:checked');
    aplicarVisibilidadEntrega(entregaSeleccionada ? entregaSeleccionada.value : null);
}

function aplicarVisibilidadEntrega(valor) {
    const datosEnvio = document.getElementById('datosEnvio');
    if (datosEnvio) datosEnvio.style.display = valor === 'envio' ? 'block' : 'none';

    const datosRetiro = document.getElementById('datosRetiro');
    if (datosRetiro) datosRetiro.style.display = valor === 'retiro' ? 'block' : 'none';

    const avisoFragilEnvio = document.getElementById('aviso-fragil-envio');
    if (avisoFragilEnvio) {
        const hayFragiles = cargarCarrito().some(i => i.fragil);
        avisoFragilEnvio.style.display = (valor === 'envio' && hayFragiles) ? 'block' : 'none';
    }
}

function validarFormularioEnvio() {
    const nombre = document.getElementById('envio-nombre')?.value.trim();
    const celular = document.getElementById('envio-celular')?.value.trim();
    const domicilio = document.getElementById('envio-domicilio')?.value.trim();
    const localidad = document.getElementById('envio-localidad')?.value.trim();
    const cp = document.getElementById('envio-cp')?.value.trim();

    if (!nombre || !celular || !domicilio || !localidad || !cp) {
        mostrarToast('⚠️ Por favor completá todos los campos de envío');
        return false;
    }

    // El nombre solo puede contener letras y espacios
    const soloLetras = /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/;
    if (!soloLetras.test(nombre)) {
        mostrarToast('⚠️ El nombre solo puede contener letras');
        return false;
    }

    // El celular debe ser numérico (entero)
    if (!/^\d+$/.test(celular)) {
        mostrarToast('⚠️ El celular solo puede contener números');
        return false;
    }
    if (celular.length < 8) {
        mostrarToast('⚠️ Ingresá un número de celular válido');
        return false;
    }

    return true;
}

/* ── Validación: quién retira en local ───────────────────
   Se pide siempre que la forma de entrega sea "retiro", sin
   importar si el comprador está registrado, logueado o es
   invitado — porque quien retira puede ser una persona distinta
   de quien pagó. ── */
function validarFormularioRetiro() {
    const nombre = document.getElementById('retiro-nombre')?.value.trim();
    const dni = document.getElementById('retiro-dni')?.value.trim();

    if (!nombre || !dni) {
        mostrarToast('⚠️ Completá el nombre y DNI de quien retira la planta');
        return false;
    }

    const soloLetras = /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/;
    if (!soloLetras.test(nombre)) {
        mostrarToast('⚠️ El nombre solo puede contener letras');
        return false;
    }

    if (!/^\d+$/.test(dni)) {
        mostrarToast('⚠️ El DNI solo puede contener números');
        return false;
    }
    if (dni.length < 7 || dni.length > 8) {
        mostrarToast('⚠️ Ingresá un DNI válido (7 u 8 dígitos, sin puntos)');
        return false;
    }

    return true;
}

// Saneo en tiempo real: nombre solo letras/espacios
document.addEventListener('input', e => {
    if (e.target.id === 'envio-nombre' || e.target.id === 'retiro-nombre') {
        e.target.value = e.target.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, '');
    }
});

// Saneo en tiempo real: celular / DNI solo dígitos (se ingresan como entero)
document.addEventListener('input', e => {
    if (e.target.id === 'envio-celular' || e.target.id === 'retiro-dni') {
        e.target.value = e.target.value.replace(/\D/g, '');
    }
});

function validarZonaEnvio(localidad) {
    const l = localidad.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return ZONAS_ENVIO_GRATIS.some(z => l.includes(z));
}

// Listener para el select de localidad (validación en tiempo real)
document.addEventListener('change', e => {
    if (e.target.id === 'envio-localidad') {
        const aviso = document.getElementById('zona-cobertura-msg');
        if (!aviso) return;
        const val = e.target.value.trim();
        if (!val) { aviso.style.display = 'none'; return; }
        const enZona = validarZonaEnvio(val);
        aviso.textContent = enZona
            ? '✅ ¡Si estás en Quilmes Centro o Berazategui tú envío es gratis!'
            : '⚠️ Por ahora solo enviamos a Quilmes Centro y Berazategui. ¿Preferís retirar en tienda? o para otra localidad contectate con nosotros';
        aviso.style.display = 'block';
        aviso.style.color = enZona ? '#1b5e20' : '#e65100';
    }
});

// Listener cambio de forma de entrega
document.addEventListener('change', e => {
    if (e.target.name === 'formaEntrega') {
        aplicarVisibilidadEntrega(e.target.value);
    }
});

function avanzarAPaso3Pago() {
    const entrega = document.querySelector('input[name="formaEntrega"]:checked');
    if (!entrega) {
        mostrarToast('⚠️ Elegí una forma de entrega');
        return;
    }
    if (entrega.value === 'envio' && !validarFormularioEnvio()) return;
    if (entrega.value === 'retiro' && !validarFormularioRetiro()) return;
    irAPaso(3);
}

/* ── Opción alternativa en "Forma de entrega": coordinar todo
   directamente con un vendedor por WhatsApp, sin pasar por el
   flujo de retiro/envío automático. ── */
function coordinarConVendedor() {
    const mensaje =
        '¡Hola! 👋 Soy un cliente de Vivero Mundo Verde 🌿 y quiero coordinar ' +
        'directamente con la vendedora la entrega de mi pedido.';
    window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(mensaje)}`, '_blank');
}

/* ── PASO 3: Forma de pago ───────────────────────────── */
// Se paga por transferencia o tarjeta, sea cual sea la forma de entrega
// (retiro en local o envío a domicilio), y en ambos casos hay que
// adjuntar el comprobante de pago antes de poder continuar.
function renderPaso3Pago() {
    // Mostrar/ocultar secciones según método seleccionado
    const metodoSeleccionado = document.querySelector('input[name="metodoPago"]:checked');
    aplicarVisibilidadMetodo(metodoSeleccionado ? metodoSeleccionado.value : 'transferencia');

    // Link de Mercado Pago
    const btnMP = document.getElementById('btn-mercadopago');
    if (btnMP) btnMP.href = MERCADOPAGO_LINK;
}

let qrAliasGenerado = false; // el QR se genera una sola vez y se reutiliza

function generarQrAlias() {
    const contenedor = document.getElementById('qr-alias');
    if (!contenedor || qrAliasGenerado) return;
    if (typeof QRCode === 'undefined') {
        console.error('Librería qrcodejs no cargada. Revisá el <script> de qrcode.min.js en el HTML.');
        return;
    }
    const alias = document.getElementById('cbu-alias-value')?.textContent.trim() || 'AGUS.MUNDO.VERDE';
    new QRCode(contenedor, {
        text: alias,
        width: 120,
        height: 120,
        colorDark: '#1b5e20',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
    qrAliasGenerado = true;
}

// Métodos que ya están en la grilla del paso 3 pero todavía no tienen
// integración real del lado del backend (MODO, Mercado Pago billetera,
// Mercado Crédito, cuotas sin interés con débito). Se pueden abrir para
// ver el aviso, pero no dejan continuar el pedido hasta conectarlos.
const METODOS_PROXIMAMENTE = ['modo', 'mercadopago', 'mercadocredito', 'cuotasdebito'];

// Cada método de pago tiene su propio renglón de detalle debajo de la
// grilla de botones (ver #metodoDetalle en el HTML). Acá se mapea el
// value del radio al id de ese renglón.
const DETALLE_ID_POR_METODO = {
    transferencia: 'detalle-transferencia',
    tarjeta: 'detalle-tarjeta',
    modo: 'detalle-modo',
    mercadopago: 'detalle-mercadopago',
    mercadocredito: 'detalle-mercadocredito',
    cuotasdebito: 'detalle-cuotasdebito',
};

function aplicarVisibilidadMetodo(valor) {
    const footerPaso3 = document.querySelector('#checkout-paso-3 .checkout-footer');
    const btnContinuar = document.getElementById('btnContinuarPago');
    const esProximamente = METODOS_PROXIMAMENTE.includes(valor);

    // Mostrar solo el renglón de detalle del método elegido, ocultar el resto.
    Object.entries(DETALLE_ID_POR_METODO).forEach(([metodo, id]) => {
        const panel = document.getElementById(id);
        if (panel) panel.style.display = (metodo === valor) ? 'block' : 'none';
    });

    if (valor === 'transferencia') generarQrAlias();

    // El botón "Ver resumen del pedido →" del footer solo tiene sentido
    // para transferencia: con tarjeta, el propio Brick tiene su botón de
    // pago y avanza al paso 4 automáticamente cuando Mercado Pago aprueba.
    if (footerPaso3) footerPaso3.style.display = valor === 'tarjeta' ? 'none' : 'block';

    // Métodos todavía no conectados: se puede abrir el recuadro para ver
    // el aviso, pero el botón de continuar queda deshabilitado.
    if (btnContinuar) {
        btnContinuar.disabled = esProximamente;
        btnContinuar.textContent = esProximamente
            ? 'Método no disponible todavía'
            : 'Ver resumen del pedido →';
    }

    if (valor === 'tarjeta') inicializarBrickTarjeta();
}

/* ── Card Payment Brick de Mercado Pago ──────────────────
   Requiere:
   1) <script src="https://sdk.mercadopago.com/js/v2"></script>
      cargado ANTES de script.js en el HTML.
   2) MP_PUBLIC_KEY seteada más arriba en este archivo.
   3) El endpoint /pagos/tarjeta en mundo_verde_backend, que
      recibe el token generado acá y efectiviza el cobro del
      lado del servidor con el Access Token PRIVADO de Mercado
      Pago (ver mvPagos en api.js y el ejemplo pagos.php). ── */
async function inicializarBrickTarjeta() {
    const banner = document.getElementById('mp-preview-banner');
    const brickContainer = document.getElementById('cardPaymentBrick_container');
    if (!brickContainer) return;

    // Sin Public Key configurada: se queda en modo "vista previa" (como
    // estaba hasta ahora) y no intenta cobrar nada.
    if (!MP_PUBLIC_KEY) {
        if (banner) banner.style.display = 'block';
        return;
    }
    if (banner) banner.style.display = 'none';

    // Ya está montado de una vez anterior en esta misma sesión de checkout
    if (mpBrickController) return;

    if (typeof MercadoPago === 'undefined') {
        console.error('SDK de Mercado Pago no está cargado. Revisá el <script src="https://sdk.mercadopago.com/js/v2"></script> en el HTML.');
        mostrarToast('⚠️ No se pudo cargar el módulo de pago con tarjeta');
        return;
    }

    const mp = new MercadoPago(MP_PUBLIC_KEY, { locale: 'es-AR' });
    const total = calcularTotal(cargarCarrito());

    try {
        mpBrickController = await mp.bricks().create('cardPayment', 'cardPaymentBrick_container', {
            initialization: { amount: total },
            customization: { visual: { style: { theme: 'default' } } },
            callbacks: {
                onReady: () => {},
                onSubmit: (cardFormData) => procesarPagoConTarjeta(cardFormData),
                onError: (error) => {
                    console.error('Error en el Brick de tarjeta:', error);
                    mostrarToast('⚠️ Hubo un problema con los datos de la tarjeta');
                },
            },
        });
    } catch (error) {
        console.error('No se pudo inicializar el Brick de Mercado Pago:', error);
        mostrarToast('⚠️ No se pudo cargar el módulo de pago con tarjeta');
    }
}

/* Manda el token (generado acá, en el navegador) a TU backend, que es
   quien de verdad cobra con el Access Token privado. A este archivo del
   frontend NUNCA le llega ni pasa el número de tarjeta completo. */
async function procesarPagoConTarjeta(cardFormData) {
    try {
        const total = calcularTotal(cargarCarrito());
        const resultado = await mvPagos.pagarConTarjeta({
            ...cardFormData,
            transaction_amount: total,
        });

        if (resultado.status === 'approved') {
            pagoTarjetaAprobado = resultado;
            mostrarToast('✅ Pago aprobado');
            irAPaso(4);
        } else if (resultado.status === 'in_process' || resultado.status === 'pending') {
            pagoTarjetaAprobado = resultado;
            mostrarToast('⏳ Tu pago quedó en revisión');
            irAPaso(4);
        } else {
            mostrarToast('❌ El pago fue rechazado. Probá con otra tarjeta o elegí transferencia.');
        }
    } catch (err) {
        mostrarToast('⚠️ ' + err.message);
    }
}

// Listener cambio de método de pago
document.addEventListener('change', e => {
    if (e.target.name === 'metodoPago') {
        aplicarVisibilidadMetodo(e.target.value);
    }
});

function avanzarAPaso4() {
    const metodo = document.querySelector('input[name="metodoPago"]:checked');
    if (!metodo) {
        mostrarToast('⚠️ Elegí un método de pago');
        return;
    }

    if (METODOS_PROXIMAMENTE.includes(metodo.value)) {
        mostrarToast('⚠️ Ese método todavía no está disponible. Elegí Transferencia o Tarjeta.');
        return;
    }

    if (metodo.value === 'transferencia') {
        const comprobante = document.getElementById('comprobante-archivo');
        if (!comprobante || comprobante.files.length === 0) {
            mostrarToast('⚠️ Adjuntá el comprobante de pago para continuar');
            return;
        }
    }

    // Con tarjeta no se llega hasta acá por botón propio (el Brick tiene
    // su propio submit y ya te manda al paso 4 al aprobarse el pago), pero
    // se valida igual por las dudas de que se llame desde otro lado.
    if (metodo.value === 'tarjeta' && !pagoTarjetaAprobado) {
        mostrarToast('⚠️ Completá el pago con tu tarjeta para continuar');
        return;
    }

    irAPaso(4);
}

/* ── PASO 4: Confirmar ───────────────────────────────── */
const ENTREGA_LABEL = { retiro: 'Retiro en local', envio: 'Envío a domicilio' };
const PAGO_LABEL = {
    transferencia: 'Transferencia bancaria',
    tarjeta: 'Tarjeta (Mercado Pago)',
    modo: 'MODO',
    mercadopago: 'Mercado Pago',
    mercadocredito: 'Mercado Crédito',
    cuotasdebito: 'Cuotas sin interés con Débito',
};

function renderPaso4Confirmar() {
    const items = cargarCarrito();
    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';
    const total = calcularTotal(items);
    const totalFinal = total;

    // Resumen de productos
    const resumenEl = document.getElementById('confirm-productos');
    if (resumenEl) {
        resumenEl.innerHTML = items.map(i => `
            <div class="confirm-item">
                <span>${i.nombre} ×${i.qty}</span>
                <span>$${(i.precio * i.qty).toLocaleString('es-AR')}</span>
            </div>
        `).join('');
    }

    // Resumen de entrega
    const entregaEl = document.getElementById('confirm-entrega');
    if (entregaEl) {
        entregaEl.innerHTML = `
            <div class="confirm-item">
                <span>Forma de entrega</span>
                <span>${ENTREGA_LABEL[entrega] || entrega}</span>
            </div>
        `;
    }

    // Resumen de pago
    const pagoEl = document.getElementById('confirm-pago');
    if (pagoEl) {
        pagoEl.innerHTML = `
            <div class="confirm-item">
                <span>Método</span>
                <span>${PAGO_LABEL[metodo] || metodo}</span>
            </div>
        `;
    }

    // Datos de quién retira, si corresponde
    const retiroEl = document.getElementById('confirm-retiro');
    if (retiroEl) {
        if (entrega === 'retiro') {
            const nombreRetira = document.getElementById('retiro-nombre')?.value || '';
            const dniRetira = document.getElementById('retiro-dni')?.value || '';
            retiroEl.innerHTML = `
                <div class="confirm-section-title">Quién retira</div>
                <div class="confirm-item"><span>Nombre</span><span>${nombreRetira}</span></div>
                <div class="confirm-item"><span>DNI</span><span>${dniRetira}</span></div>
            `;
            retiroEl.style.display = 'block';
        } else {
            retiroEl.style.display = 'none';
        }
    }

    // Datos de envío si corresponde
    const envioEl = document.getElementById('confirm-envio');
    if (envioEl) {
        if (entrega === 'envio') {
            const nombre = document.getElementById('envio-nombre')?.value || '';
            const domicilio = document.getElementById('envio-domicilio')?.value || '';
            const localidad = document.getElementById('envio-localidad')?.value || '';
            const cp = document.getElementById('envio-cp')?.value || '';
            envioEl.innerHTML = `
                <div class="confirm-section-title">Datos de envío</div>
                <div class="confirm-item"><span>Destinatario</span><span>${nombre}</span></div>
                <div class="confirm-item"><span>Domicilio</span><span>${domicilio}, ${localidad} (${cp})</span></div>
            `;
            envioEl.style.display = 'block';
        } else {
            envioEl.style.display = 'none';
        }
    }

    // Total final
    const totalFinalEl = document.getElementById('confirm-total-final');
    if (totalFinalEl) {
        totalFinalEl.textContent = '$' + totalFinal.toLocaleString('es-AR');
    }
}

/* ── Guardar pedido en la base de datos (backend Flask + SQLite3) ──
   No bloquea el flujo de WhatsApp/mail: si el backend no está
   corriendo, el pedido igual se envía por WhatsApp con normalidad. ── */
async function guardarPedidoEnBackend() {
    if (typeof mvPedidos === 'undefined') return null; // js/api.js no está cargado en esta página

    const items = cargarCarrito();
    if (items.length === 0) return null;

    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';

    const payload = {
        items: items.map(i => ({ codigo: i.codigo || '', cantidad: i.qty })),
        forma_entrega: entrega,
        forma_pago: metodo,
        cliente: {
            nombre: document.getElementById('envio-nombre')?.value || (mvUsuarioActual()?.nombre ?? ''),
            email: mvUsuarioActual()?.email ?? '',
            celular: document.getElementById('envio-celular')?.value || '',
        },
        envio: entrega === 'envio' ? {
            domicilio: document.getElementById('envio-domicilio')?.value || '',
            localidad: document.getElementById('envio-localidad')?.value || '',
            cp: document.getElementById('envio-cp')?.value || '',
        } : {},
        // Quién retira en local: puede ser distinto de quien pagó
        // (usuario invitado, o alguien que manda a otra persona a buscar
        // la planta). Se pide siempre que la entrega sea "retiro".
        retiro: entrega === 'retiro' ? {
            nombre: document.getElementById('retiro-nombre')?.value || '',
            dni: document.getElementById('retiro-dni')?.value || '',
        } : {},
    };

    // Pago con tarjeta ya aprobado por el Brick: se manda el id de pago de
    // Mercado Pago para que el backend lo asocie al pedido (sin comprobante).
    if (metodo === 'tarjeta' && pagoTarjetaAprobado) {
        payload.pago_mp = {
            payment_id: pagoTarjetaAprobado.payment_id,
            status: pagoTarjetaAprobado.status,
        };
    }

    // Comprobante de pago (solo aplica a transferencia): si está adjunto,
    // el pedido se guarda directamente como "pagado".
    const comprobanteInput = document.getElementById('comprobante-archivo');
    const archivoComprobante = (metodo === 'transferencia' && comprobanteInput && comprobanteInput.files.length > 0)
        ? comprobanteInput.files[0]
        : null;

    try {
        return await mvPedidos.crear(payload, archivoComprobante);
    } catch (err) {
        console.warn('No se pudo guardar el pedido en el backend:', err.message);
        // ⚠️ Antes esto fallaba en silencio (solo consola): el pedido no
        // quedaba en la base pero igual se mostraba "¡Pedido enviado!" y
        // se abría WhatsApp, como si todo hubiera salido bien. Ahora se
        // avisa para poder detectarlo durante las pruebas.
        mostrarToast('⚠️ No se pudo registrar el pedido en el sistema (revisá la consola). El WhatsApp igual se envía.');
        return null;
    }
}

/* ── Enviar por WhatsApp ──────────────────────────────── */
async function enviarPedidoWhatsApp() {
    const items = cargarCarrito();
    if (items.length === 0) { mostrarToast('El carrito está vacío'); return; }

    await guardarPedidoEnBackend();

    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';
    const total = calcularTotal(items);
    const totalFinal = total;

    // Líneas de productos
    const lineasProductos = items
        .map(i => `• ${i.nombre} ×${i.qty} — $${(i.precio * i.qty).toLocaleString('es-AR')}`)
        .join('\n');

    // Líneas de quién retira / envío
    let lineasEnvio = '';
    if (entrega === 'retiro') {
        const nombreRetira = document.getElementById('retiro-nombre')?.value || '';
        const dniRetira = document.getElementById('retiro-dni')?.value || '';
        lineasEnvio = `\n\n🏬 *Retira*\nNombre: ${nombreRetira}\nDNI: ${dniRetira}`;
    } else if (entrega === 'envio') {
        const nombre = document.getElementById('envio-nombre')?.value || '';
        const celular = document.getElementById('envio-celular')?.value || '';
        const domicilio = document.getElementById('envio-domicilio')?.value || '';
        const localidad = document.getElementById('envio-localidad')?.value || '';
        const cp = document.getElementById('envio-cp')?.value || '';
        lineasEnvio = `\n\n📦 *Datos de envío*\nNombre: ${nombre}\nCelular: ${celular}\nDomicilio: ${domicilio}, ${localidad} (${cp})`;
    }

    const mensaje =
        `¡Hola! Quisiera hacer el siguiente pedido:\n\n` +
        `${lineasProductos}\n\n` +
        `🚚 *Forma de entrega:* ${ENTREGA_LABEL[entrega]}\n` +
        `💳 *Método de pago:* ${PAGO_LABEL[metodo]}\n` +
        `📎 Adjunto el comprobante de pago` +
        lineasEnvio +
        `\n\n*Total: $${totalFinal.toLocaleString('es-AR')}*`;

    window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(mensaje)}`, '_blank');

    // Mostrar confirmación y vaciar carrito
    mostrarConfirmacionPedido();
}

/* ── Enviar por email ─────────────────────────────────── */
async function enviarPedidoMail() {
    const items = cargarCarrito();
    if (items.length === 0) { mostrarToast('El carrito está vacío'); return; }

    await guardarPedidoEnBackend();

    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';
    const total = calcularTotal(items);
    const totalFinal = total;

    let lineasEnvio = '';
    if (entrega === 'retiro') {
        const nombreRetira = document.getElementById('retiro-nombre')?.value || '';
        const dniRetira = document.getElementById('retiro-dni')?.value || '';
        lineasEnvio = `\n\nRetira:\nNombre: ${nombreRetira}\nDNI: ${dniRetira}`;
    } else if (entrega === 'envio') {
        const nombre = document.getElementById('envio-nombre')?.value || '';
        const celular = document.getElementById('envio-celular')?.value || '';
        const domicilio = document.getElementById('envio-domicilio')?.value || '';
        const localidad = document.getElementById('envio-localidad')?.value || '';
        const cp = document.getElementById('envio-cp')?.value || '';
        lineasEnvio = `\n\nDatos de envío:\nNombre: ${nombre}\nCelular: ${celular}\nDomicilio: ${domicilio}, ${localidad} (${cp})`;
    }

    const asunto = encodeURIComponent('Nuevo pedido - Mundo Verde');
    const cuerpo = encodeURIComponent(
        `Hola! Quiero hacer el siguiente pedido:\n\n` +
        items.map(i => `${i.nombre} x${i.qty} - $${(i.precio * i.qty).toLocaleString('es-AR')}`).join('\n') +
        `\n\nForma de entrega: ${ENTREGA_LABEL[entrega]}` +
        `\nMétodo de pago: ${PAGO_LABEL[metodo]}` +
        `\n(No olvidar adjuntar el comprobante de pago a este mail)` +
        lineasEnvio +
        `\n\nTotal: $${totalFinal.toLocaleString('es-AR')}`
    );

    window.location.href = `mailto:${MAIL_DESTINO}?subject=${asunto}&body=${cuerpo}`;

    mostrarConfirmacionPedido();
}

/* ── Pantalla de confirmación post-pedido ────────────── */
function mostrarConfirmacionPedido() {
    const panel5 = document.getElementById('checkout-paso-5');
    if (panel5) {
        [1, 2, 3, 4].forEach(p => {
            const el = document.getElementById('checkout-paso-' + p);
            if (el) el.style.display = 'none';
        });
        panel5.style.display = 'flex';

        const barra = document.getElementById('checkout-steps');
        if (barra) barra.style.display = 'none';

        const titulo = document.querySelector('.carrito-header h2');
        if (titulo) titulo.textContent = '¡Pedido enviado!';

        const btnVolver = document.getElementById('checkout-volver');
        if (btnVolver) btnVolver.style.display = 'none';
    }

    // Reset del pago con tarjeta para que el próximo checkout arranque limpio
    pagoTarjetaAprobado = null;
    if (mpBrickController && typeof mpBrickController.unmount === 'function') {
        mpBrickController.unmount();
    }
    mpBrickController = null;

    vaciarCarrito();
}

/* ── Inicializar al cargar ───────────────────────────── */
actualizarBadge();
verificarRecordatorioCarrito();

/* ──==================================================
 Carrusel de talleres (Forma parte 2.0.talleres.html)
  ===================================================== */
const track = document.getElementById('track');
const btnCarrusel = document.getElementById('toggle');
if (track && btnCarrusel) {
    let isPaused = false;
    btnCarrusel.addEventListener('click', () => {
        isPaused = !isPaused;
        track.style.animationPlayState = isPaused ? 'paused' : 'running';
        btnCarrusel.textContent = isPaused ? '▶ Reanudar' : '⏸ Pausar';
    });
}



/* ══════════════════════════════════════════════════════
   AUTENTICACIÓN — login / registro / recuperar / restablecer
   (cada bloque se protege con "if" para no romper otras páginas
    que también cargan este script.js)
   ══════════════════════════════════════════════════════ */

/* ── Login ── */
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;
        const errEmail = document.getElementById('err-email');
        const errPassword = document.getElementById('err-password');
        const errGeneral = document.getElementById('err-general');

        errEmail.style.display = 'none';
        errPassword.style.display = 'none';
        errGeneral.style.display = 'none';

        let ok = true;
        if (!email.includes('@')) { errEmail.style.display = 'block'; ok = false; }
        if (!password) { errPassword.style.display = 'block'; ok = false; }
        if (!ok) return;

        try {
            const data = await mvAuth.login(email, password);
            mvSetSesion(data.token, data.usuario);
            migrarCarritoGuest();
            marcarRecordatorioCarrito();

            loginForm.style.display = 'none';
            document.getElementById('conf-nombre-texto').textContent = `¡Hola, ${data.usuario.nombre}!`;
            document.getElementById('msg-confirmacion').style.display = 'block';

            setTimeout(() => { window.location.href = '1_0_vivero.html'; }, 1500);
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
        }
    });
}

/*Chat GPT 11-08-2026*/
/*Para celulares LOGIN Logout*/


/* ── Registro ── */
const registroForm = document.getElementById('registroForm');
if (registroForm) {
    registroForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const nombre = document.getElementById('reg-nombre').value.trim();
        const email = document.getElementById('reg-email').value.trim();
        const telefono = document.getElementById('reg-telefono').value.trim();
        const domicilioCompleto = document.getElementById('reg-dom-completo').value.trim();
        const localidad = document.getElementById('reg-localidad').value.trim();
        const cp = document.getElementById('reg-cp').value.trim();
        const fechaNacimiento = document.getElementById('reg-fecha-nacimiento').value;
        const password = document.getElementById('reg-password').value;
        const password2 = document.getElementById('reg-password2').value;

        const errNombre = document.getElementById('err-nombre');
        const errEmail = document.getElementById('err-email');
        const errDomCompleto = document.getElementById('err-dom-completo');
        const errLocalidad = document.getElementById('err-localidad');
        const errPassword = document.getElementById('err-password');
        const errPassword2 = document.getElementById('err-password2');
        const errGeneral = document.getElementById('err-general');

        [errNombre, errEmail, errDomCompleto, errLocalidad, errPassword, errPassword2, errGeneral]
            .forEach(el => el.style.display = 'none');

        let ok = true;
        if (!nombre) { errNombre.style.display = 'block'; ok = false; }
        if (!email.includes('@')) { errEmail.style.display = 'block'; ok = false; }
        if (!domicilioCompleto) {
            errDomCompleto.textContent = 'Ingresá tu domicilio completo.';
            errDomCompleto.style.display = 'block';
            ok = false;
        }
        if (!localidad) {
            errLocalidad.textContent = 'Ingresá tu localidad.';
            errLocalidad.style.display = 'block';
            ok = false;
        }
        if (password.length < 6) { errPassword.style.display = 'block'; ok = false; }
        if (password !== password2) { errPassword2.style.display = 'block'; ok = false; }
        if (!ok) return;

        try {
            const data = await mvAuth.registro({
                nombre, email, telefono,
                domicilio_completo: domicilioCompleto,
                localidad,
                codigo_postal: cp || undefined,
                fecha_nacimiento: fechaNacimiento || undefined,
                password,
            });
            mvSetSesion(data.token, data.usuario);
            migrarCarritoGuest();
            marcarRecordatorioCarrito();

            registroForm.style.display = 'none';
            document.getElementById('conf-nombre-texto').textContent = `¡Hola, ${data.usuario.nombre}!`;
            document.getElementById('msg-confirmacion').style.display = 'block';

            setTimeout(() => { window.location.href = '1_0_vivero.html'; }, 1500);
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
        }
    });
}

/* ── Recuperar contraseña ── */
const recuperarForm = document.getElementById('recuperarForm');
if (recuperarForm) {
    recuperarForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('rec-email').value.trim();
        const errEmail = document.getElementById('err-email');
        const errGeneral = document.getElementById('err-general');

        errEmail.style.display = 'none';
        errGeneral.style.display = 'none';

        if (!email.includes('@')) { errEmail.style.display = 'block'; return; }

        try {
            const data = await mvAuth.recuperar(email);

            recuperarForm.style.display = 'none';
            document.getElementById('conf-nombre-texto').textContent = data.mensaje;
            document.getElementById('msg-confirmacion').style.display = 'block';

            // Modo desarrollo: mientras no haya envío de emails real,
            // se muestra acá el link para poder probar el flujo.
            if (data.dev_link) {
                const aviso = document.createElement('p');
                aviso.style.cssText = 'margin-top:14px; font-size:.8rem; opacity:.8;';
                aviso.innerHTML = 'Modo desarrollo (sin email configurado): <a href="' + data.dev_link + '" style="color:#a5d6a7;">click acá para continuar</a>';
                document.getElementById('msg-confirmacion').appendChild(aviso);
            }
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
        }
    });
}

/* ── Restablecer contraseña ── */
const restablecerForm = document.getElementById('restablecerForm');
if (restablecerForm) {
    const paramsRestablecer = new URLSearchParams(window.location.search);
    const tokenRestablecer = paramsRestablecer.get('token');

    if (!tokenRestablecer) {
        restablecerForm.style.display = 'none';
        document.getElementById('err-general-token').style.display = 'block';
    }

    restablecerForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const password = document.getElementById('rest-password').value;
        const password2 = document.getElementById('rest-password2').value;
        const errPassword = document.getElementById('err-password');
        const errPassword2 = document.getElementById('err-password2');
        const errGeneral = document.getElementById('err-general');

        errPassword.style.display = 'none';
        errPassword2.style.display = 'none';
        errGeneral.style.display = 'none';

        let ok = true;
        if (password.length < 8) { errPassword.style.display = 'block'; ok = false; }
        if (password !== password2) { errPassword2.style.display = 'block'; ok = false; }
        if (!ok) return;

        try {
            const data = await mvAuth.restablecer(tokenRestablecer, password);

            restablecerForm.style.display = 'none';
            document.getElementById('conf-nombre-texto').textContent = data.mensaje;
            document.getElementById('msg-confirmacion').style.display = 'block';

            setTimeout(() => { window.location.href = 'login.html'; }, 1800);
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
        }
    });
}

/* ══════════════════════════════════════════════════════
   NEWSLETTER — conectado al backend Flask + SQLite3 (js/api.js)
   ══════════════════════════════════════════════════════ */

const newsletterForm = document.getElementById('newsletterForm');
if (newsletterForm) {
    const origenSelect = document.getElementById('origen');
    if (origenSelect) {
        origenSelect.addEventListener('change', function () {
            document.getElementById('campo-codigo-ref').style.display =
                this.value === 'Referido' ? 'block' : 'none';
        });
    }

    newsletterForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const nombre  = document.getElementById('nombre').value.trim();
        const mail    = document.getElementById('mail').value.trim();
        const origen  = document.getElementById('origen').value;
        const codRef  = document.getElementById('codigo-referido').value.trim().toUpperCase();
        const tyc     = document.getElementById('acepta-tyc').checked;

        let ok = true;
        const show = (id, mostrar) => {
            document.getElementById(id).style.display = mostrar ? 'block' : 'none';
        };

        show('err-nombre', !nombre);   if (!nombre) ok = false;
        show('err-mail', !mail.includes('@'));  if (!mail.includes('@')) ok = false;
        show('err-tyc', !tyc);         if (!tyc) ok = false;
        show('err-ref', false);

        if (!ok) return;

        try {
            const data = await mvNewsletter.suscribir({
                nombre,
                mail,
                origen,
                cod_ref: origen === 'Referido' ? codRef : '',
            });

            localStorage.setItem('mv_newsletter_mail', mail);

            this.style.display = 'none';
            const conf = document.getElementById('msg-confirmacion');
            document.getElementById('conf-nombre-texto').textContent = `¡Hola, ${data.nombre}!`;
            document.getElementById('conf-codigo').textContent = data.codigo_mio;
            conf.style.display = 'block';

            document.getElementById('conf-codigo').addEventListener('click', () => {
                navigator.clipboard.writeText(data.codigo_mio).catch(() => {});
            });
        } catch (err) {
            if (err.message.toLowerCase().includes('referido')) {
                show('err-ref', true);
            } else {
                mostrarToast('⚠️ ' + err.message);
            }
        }
    });

    const btnAbrirConsulta = document.getElementById('btn-abrir-consulta');
    if (btnAbrirConsulta) {
        btnAbrirConsulta.addEventListener('click', () => {
            const form = document.getElementById('form-consulta');
            form.style.display = form.style.display === 'none' ? 'flex' : 'none';
        });
    }

    const btnConsultar = document.getElementById('btn-consultar');
    if (btnConsultar) {
        btnConsultar.addEventListener('click', async () => {
            const mail = document.getElementById('consulta-mail').value.trim();
            const resultado = document.getElementById('consulta-resultado');
            resultado.textContent = '';
            if (!mail) return;

            try {
                const data = await mvNewsletter.miEstado(mail);
                resultado.innerHTML =
                    `Hola ${data.nombre}, tu código es ` +
                    `<span class="codigo-ref-mini">${data.codigo_mio}</span> ` +
                    `y ya tenés <strong>${data.referidos_exitosos}</strong> referido(s) que compraron.`;
            } catch (err) {
                resultado.textContent = err.message;
            }
        });
    }
}

/*-- Menú hamburguesa: idealmente mover este bloque a js/script.js para que valga en todas las páginas --*/

document.addEventListener('DOMContentLoaded', function () {
    const navToggle = document.getElementById('navToggle');
    const navList = document.getElementById('navList');
    if (!navToggle || !navList) return;

    navToggle.addEventListener('click', function () {
        const abierto = navList.classList.toggle('abierto');
        navToggle.classList.toggle('abierto', abierto);
        navToggle.setAttribute('aria-expanded', String(abierto));
    });

    // Cierra el menú al tocar un link
    navList.querySelectorAll('.nav_link').forEach(function (link) {
        link.addEventListener('click', function () {
            navList.classList.remove('abierto');
            navToggle.classList.remove('abierto');
            navToggle.setAttribute('aria-expanded', 'false');
        });
    });

    // Subrubros: acordeón dentro del menú hamburguesa (SOLO celulares/responsive)
    navList.querySelectorAll('.nav_subtoggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const item = btn.closest('.nav_item--dropdown');
            if (!item) return;
            const abierto = item.classList.toggle('abierto');
            btn.setAttribute('aria-expanded', String(abierto));
        });
    });
});

