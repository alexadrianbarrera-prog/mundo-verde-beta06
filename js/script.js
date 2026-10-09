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

// Devuelve true si hay que mostrar el popup y false si la persona ya está
// inscripta en el newsletter (tabla newsletter_suscriptores). Una inscripta NO
// debe volver a verlo nunca; por eso, ante cualquier duda, NO se muestra.
//
// Se consulta el mail con el que se suscribió desde este navegador y, si hay
// sesión iniciada, el mail de la cuenta (así tampoco reaparece al entrar desde
// otro dispositivo o después de borrar los datos del navegador). Solo un 404
// confirma que NO está inscripta; un servidor caído o un timeout no cuentan.
// Quien ya tiene cuenta (se registró o inició sesión) tampoco ve el popup: queda
// marcado en este navegador (sigue marcado aunque después cierre sesión) y, además,
// no se muestra mientras haya una sesión iniciada (por ejemplo, desde otro dispositivo).
const MV_POPUP_OCULTO_KEY = 'mv_popup_oculto';
function mvOcultarPopupNewsletter() {
    try { localStorage.setItem(MV_POPUP_OCULTO_KEY, '1'); } catch (e) { /* storage bloqueado: se ignora */ }
}

async function mvDebeMostrarPopup() {
    const usuario = (typeof mvUsuarioActual === 'function') ? mvUsuarioActual() : null;
    let oculto = false;
    try { oculto = !!localStorage.getItem(MV_POPUP_OCULTO_KEY); } catch (e) { /* sin storage */ }
    if (oculto || usuario) return false;

    const candidatos = [];
    const guardado = localStorage.getItem('mv_newsletter_mail');
    if (guardado) candidatos.push(guardado);
    if (usuario && usuario.email && !candidatos.includes(usuario.email)) candidatos.push(usuario.email);

    if (candidatos.length === 0) return true; // visitante anónimo: mostrar popup

    let hayDuda = false;
    for (const mail of candidatos) {
        try {
            await mvNewsletter.miEstado(mail);
            localStorage.setItem('mv_newsletter_mail', mail); // inscripta: queda recordado
            return false;
        } catch (err) {
            if (err.status !== 404) hayDuda = true;
        }
    }
    if (hayDuda) return false;

    // 404 en todos los mails: ya no figura (por ejemplo, la borró el admin).
    localStorage.removeItem('mv_newsletter_mail');
    return true;
}

// api.js se carga DESPUÉS de este archivo (mvNewsletter y mvUsuarioActual todavía
// no existen acá), así que se espera a que la página termine de cargar.
function mvIniciarPopup() {
    mvDebeMostrarPopup().then((mostrar) => {
        if (mostrar) setTimeout(() => promoPopup.classList.add('show'), 1000);
    });
}
if (promoPopup) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mvIniciarPopup);
    else mvIniciarPopup();
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

/* ══════════════════════════════════════════════════════
   DESCUENTOS DEL CARRITO
   ──────────────────────────────────────────────────────
   1) Inauguración de la web: % automático hasta una fecha de vencimiento, con
      cuenta regresiva en pantalla. Porcentaje y fecha salen de la tabla `configuracion`
      (GET /descuentos), la misma que usa el backend para cobrar.
   2) Newsletter: -10% al ingresar el "Código Verde" (el código
      personal que recibe el suscriptor). Es de ÚNICO USO: la
      validación y el "marcado como usado" los hace el backend
      (ver mvNewsletter.validarCodigo en api.js y el campo
      codigo_verde que viaja en el pedido).
   Ambos porcentajes se calculan sobre el SUBTOTAL (acumulables:
   10% + 10% = 20% sobre el subtotal).
   calcularTotal() ya devuelve el total CON descuentos, así que
   el paso 3 (pago), el Brick de Mercado Pago, el paso 4, el
   WhatsApp y el mail usan automáticamente el monto correcto.
   ══════════════════════════════════════════════════════ */
const CODIGO_VERDE_KEY = 'mvCodigoVerde';

/* ── Configuración de descuentos (viene del backend: GET /descuentos) ──
   Se cachea en localStorage para que el total sea correcto desde que carga
   la página; en cada carga y al abrir el carrito se refresca.
   offsetMs = (hora del servidor) − (hora del dispositivo): la cuenta regresiva
   y el vencimiento usan la hora del servidor, no la del celular del cliente. */
const CONFIG_DESC_CACHE_KEY = 'mvConfigDescuentos';
const CONFIG_DESCUENTOS = {
    inauguracion: { activa: false, pct: 10, hastaMs: null },
    newsletterPct: 10,
    offsetMs: 0
};

function aplicarConfigDescuentos(cfg, offsetMs) {
    if (!cfg || !cfg.inauguracion) return;
    const hasta = cfg.inauguracion.hasta ? Date.parse(cfg.inauguracion.hasta) : NaN;
    CONFIG_DESCUENTOS.inauguracion.activa = !!cfg.inauguracion.activa;
    CONFIG_DESCUENTOS.inauguracion.pct = Number(cfg.inauguracion.porcentaje) || 0;
    CONFIG_DESCUENTOS.inauguracion.hastaMs = isNaN(hasta) ? null : hasta;
    CONFIG_DESCUENTOS.newsletterPct = Number(cfg.newsletter && cfg.newsletter.porcentaje) || 0;
    CONFIG_DESCUENTOS.offsetMs = Number(offsetMs) || 0;
}

async function cargarConfigDescuentos() {
    if (typeof mvApi !== 'function') return; // api.js no está cargado en esta página
    try {
        const cfg = await mvApi('/descuentos');
        const ahora = cfg.ahora ? Date.parse(cfg.ahora) : NaN;
        const offset = isNaN(ahora) ? 0 : ahora - Date.now();
        aplicarConfigDescuentos(cfg, offset);
        try { localStorage.setItem(CONFIG_DESC_CACHE_KEY, JSON.stringify({ cfg, offset })); } catch (e) {}
    } catch (err) {
        return; // backend apagado: se sigue con lo cacheado
    }
    iniciarCuentaRegresiva();
    if (document.getElementById('carrito-panel')?.classList.contains('abierto')) renderPaso1();
}

function ahoraServidorMs() {
    return Date.now() + CONFIG_DESCUENTOS.offsetMs;
}

/* Vigente = activa en el backend + fecha de vencimiento cargada + todavía no pasó */
function inauguracionVigente() {
    const d = CONFIG_DESCUENTOS.inauguracion;
    return !!(d.activa && d.pct > 0 && d.hastaMs && ahoraServidorMs() <= d.hastaMs);
}

function textoTiempoRestante(ms) {
    const s = Math.max(0, Math.floor(ms / 1000));
    const dias = Math.floor(s / 86400);
    const hh = String(Math.floor((s % 86400) / 3600)).padStart(2, '0');
    const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
    const ss = String(s % 60).padStart(2, '0');
    return (dias > 0 ? dias + ' d ' : '') + `${hh}:${mm}:${ss}`;
}

/* Pinta TODOS los elementos con el atributo data-cuenta-inauguracion (en el
   carrito y en cualquier página donde se agregue <div data-cuenta-inauguracion></div>).
   Devuelve true si la promo sigue vigente. */
function pintarCuentaRegresiva() {
    const vigente = inauguracionVigente();
    const d = CONFIG_DESCUENTOS.inauguracion;
    const txt = vigente
        ? `🎉 Descuento de inauguración −${d.pct}% · termina en ${textoTiempoRestante(d.hastaMs - ahoraServidorMs())}`
        : '';
    const els = document.querySelectorAll('[data-cuenta-inauguracion]');
    if (els.length) inyectarEstilosDescuentos();
    els.forEach(el => {
        el.classList.add('cuenta-inauguracion');
        el.textContent = txt;
        el.style.display = vigente ? '' : 'none';
    });
    return vigente;
}

let cuentaTimer = null;
function iniciarCuentaRegresiva() {
    if (cuentaTimer) { clearInterval(cuentaTimer); cuentaTimer = null; }
    if (!pintarCuentaRegresiva()) return;
    cuentaTimer = setInterval(() => {
        if (pintarCuentaRegresiva()) return;
        clearInterval(cuentaTimer); cuentaTimer = null;
        alVencerInauguracion();
    }, 1000);
}

/* Al llegar a cero con el carrito abierto: el descuento desaparece del total.
   Si el cliente estaba en pasos 2-4 (todavía no confirmó) se lo vuelve al paso 1
   para que no pague un monto desactualizado. */
function alVencerInauguracion() {
    if (!document.getElementById('carrito-panel')?.classList.contains('abierto')) return;
    if (document.getElementById('checkout-paso-5')?.style.display === 'flex') return; // pedido ya enviado
    mostrarToast('⏰ Venció el descuento de inauguración. Actualizamos el total.');
    if (typeof pasoActual !== 'undefined' && pasoActual > 1) irAPaso(1);
    else renderPaso1();
}

(function () {
    try {
        const c = JSON.parse(localStorage.getItem(CONFIG_DESC_CACHE_KEY));
        if (c && c.cfg) aplicarConfigDescuentos(c.cfg, c.offset);
    } catch (e) {}
    document.addEventListener('DOMContentLoaded', () => {
        iniciarCuentaRegresiva();
        cargarConfigDescuentos();
    });
})();

function formatoPesos(n) {
    return '$' + Number(n).toLocaleString('es-AR');
}

function limpiarCodigoVerde(valor) {
    return String(valor || '').toUpperCase().replace(/[^A-Z0-9-]/g, '');
}

function obtenerCodigoVerde() {
    try { return limpiarCodigoVerde(localStorage.getItem(CODIGO_VERDE_KEY)) || null; }
    catch (e) { return null; }
}

function guardarCodigoVerde(codigo) {
    try {
        if (codigo) localStorage.setItem(CODIGO_VERDE_KEY, codigo);
        else localStorage.removeItem(CODIGO_VERDE_KEY);
    } catch (e) { /* storage bloqueado: se ignora */ }
}

// Base de los descuentos: SOLO productos. El flete (si algún día se cobra) no lleva descuento.
function calcularSubtotal(items) {
    return items.reduce((s, i) => s + i.precio * i.qty, 0);
}

function calcularDescuentos(items) {
    const subtotal = calcularSubtotal(items);
    const lineas = [];
    if (subtotal > 0) {
        if (inauguracionVigente()) {
            const pct = CONFIG_DESCUENTOS.inauguracion.pct;
            lineas.push({ id: 'inauguracion', etiqueta: '🎉 Inauguración de la web', pct,
                          monto: Math.round(subtotal * pct / 100) });
        }
        if (obtenerCodigoVerde()) {
            const pct = CONFIG_DESCUENTOS.newsletterPct;
            lineas.push({ id: 'newsletter', etiqueta: '🌿 Newsletter (Código Verde)', pct,
                          monto: Math.round(subtotal * pct / 100) });
        }
    }
    const descuento = lineas.reduce((s, l) => s + l.monto, 0);
    return { subtotal, lineas, descuento, total: Math.max(0, subtotal - descuento) };
}

function calcularTotal(items) {
    return calcularDescuentos(items).total;
}

/* Texto del detalle de descuentos para WhatsApp (wa=true) o mail */
function textoDescuentosPedido(desc, wa) {
    if (!desc.lineas.length) return '';
    const titulo = wa ? '🏷️ *Descuentos aplicados*' : 'Descuentos aplicados';
    const cod = obtenerCodigoVerde();
    return `\n\n${titulo}\nSubtotal: ${formatoPesos(desc.subtotal)}\n` +
        desc.lineas.map(l => `• ${l.etiqueta.replace(/^\S+\s/, '')} (-${l.pct}%): -${formatoPesos(l.monto)}`).join('\n') +
        (cod ? `\nCódigo Verde: ${cod}` : '');
}

/* HTML del detalle de descuentos para el paso 4 (Confirmar) */
function htmlResumenDescuentos(desc) {
    if (!desc.lineas.length) return '';
    return `
        <div class="confirm-item"><span>Subtotal</span><span>${formatoPesos(desc.subtotal)}</span></div>
        ${desc.lineas.map(l => `
        <div class="confirm-item" style="color:#1b5e20;">
            <span>${l.etiqueta} −${l.pct}%</span><span>−${formatoPesos(l.monto)}</span>
        </div>`).join('')}
    `;
}

/* ── UI del paso 1: caja de Código Verde + renglones de descuento ──
   Se inyecta por JS dentro de #carrito-footer-p1 (antes del Total),
   así no hay que tocar el HTML de las 23 páginas que tienen su panel. */
function inyectarEstilosDescuentos() {
    if (document.getElementById('estilos-descuentos')) return;
    const st = document.createElement('style');
    st.id = 'estilos-descuentos';
    st.textContent = `
        .carrito-descuentos{display:flex;flex-direction:column;gap:6px;margin-bottom:10px;font-size:.92rem}
        .cd-fila{display:flex;justify-content:space-between;gap:8px}
        .cd-fila.cd-desc{color:#1b5e20;font-weight:600}
        .cd-codigo{background:#f1f8e9;border:1px dashed #66bb6a;border-radius:10px;padding:10px;margin-bottom:4px}
        .cd-codigo label{display:block;font-size:.82rem;margin-bottom:6px;line-height:1.3}
        .cd-codigo-fila{display:flex;gap:6px}
        .cd-codigo-fila input{flex:1;min-width:0;padding:8px 10px;border:1px solid #a5d6a7;border-radius:8px;
            text-transform:uppercase;letter-spacing:2px;font-size:.9rem}
        .cd-codigo-fila button{padding:8px 14px;border:0;border-radius:8px;background:#2e7d32;color:#fff;
            font-weight:600;cursor:pointer}
        .cd-codigo-fila button:disabled{opacity:.6;cursor:wait}
        .cd-codigo-ok{background:#e8f5e9;border-radius:10px;padding:8px 10px;margin-bottom:4px;
            display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:.85rem}
        .cd-quitar{background:none;border:0;color:#c62828;text-decoration:underline;cursor:pointer;font-size:.8rem}
        .cuenta-inauguracion{background:linear-gradient(90deg,#2e7d32,#66bb6a);color:#fff;font-weight:600;
            text-align:center;padding:8px 12px;border-radius:10px;font-size:.9rem;font-variant-numeric:tabular-nums}
    `;
    document.head.appendChild(st);
}

function renderDescuentosCarrito(desc) {
    const footerEl = document.getElementById('carrito-footer-p1');
    if (!footerEl) return;
    inyectarEstilosDescuentos();

    let cont = document.getElementById('carrito-descuentos');
    if (!cont) {
        cont = document.createElement('div');
        cont.id = 'carrito-descuentos';
        cont.className = 'carrito-descuentos';
        const filaTotal = footerEl.querySelector('.carrito-total');
        if (filaTotal && filaTotal.parentNode) filaTotal.parentNode.insertBefore(cont, filaTotal);
        else footerEl.appendChild(cont);
    }

    // Conserva lo que el cliente estaba tipeando si se vuelve a renderizar
    const previo = limpiarCodigoVerde(document.getElementById('codigo-verde-input')?.value);
    const codigo = obtenerCodigoVerde();
    let html = inauguracionVigente() ? '<div data-cuenta-inauguracion></div>' : '';

    if (codigo) {
        html += `<div class="cd-codigo-ok">
                    <span>✅ Código Verde <strong>${codigo}</strong> aplicado</span>
                    <button type="button" class="cd-quitar" onclick="quitarCodigoVerde()">Quitar</button>
                 </div>`;
    } else {
        html += `<div class="cd-codigo">
                    <label for="codigo-verde-input">¿Estás suscripto al newsletter? Ingresá tu <strong>Código Verde</strong> y obtené ${CONFIG_DESCUENTOS.newsletterPct}% OFF (válido una sola vez)</label>
                    <div class="cd-codigo-fila">
                        <input type="text" id="codigo-verde-input" maxlength="12" autocomplete="off"
                               placeholder="Ej: MV-A3B2C1" value="${previo}">
                        <button type="button" id="codigo-verde-btn" onclick="aplicarCodigoVerde()">Aplicar</button>
                    </div>
                 </div>`;
    }

    if (desc.lineas.length) {
        html += `<div class="cd-fila"><span>Subtotal</span><span>${formatoPesos(desc.subtotal)}</span></div>`;
        html += desc.lineas.map(l =>
            `<div class="cd-fila cd-desc"><span>${l.etiqueta} −${l.pct}%</span><span>−${formatoPesos(l.monto)}</span></div>`
        ).join('');
    }
    cont.innerHTML = html;
    pintarCuentaRegresiva();
}

async function aplicarCodigoVerde() {
    const input = document.getElementById('codigo-verde-input');
    const btn = document.getElementById('codigo-verde-btn');
    const codigo = limpiarCodigoVerde(input?.value);

    if (!codigo) { mostrarToast('⚠️ Ingresá tu Código Verde'); return; }
    if (typeof mvNewsletter === 'undefined' || typeof mvNewsletter.validarCodigo !== 'function') {
        mostrarToast('⚠️ No se pudo validar el código en esta página');
        return;
    }

    if (btn) btn.disabled = true;
    try {
        const r = await mvNewsletter.validarCodigo(codigo);
        if (!r || !r.valido) throw new Error((r && r.error) || 'Código Verde inválido');
        guardarCodigoVerde(codigo);
        mostrarToast(`🌿 ¡Código Verde aplicado! ${CONFIG_DESCUENTOS.newsletterPct}% de descuento`);
    } catch (err) {
        mostrarToast('⚠️ ' + err.message);
    }
    renderPaso1();
}

function quitarCodigoVerde() {
    guardarCodigoVerde(null);
    renderPaso1();
}

/* Al abrir el carrito se vuelve a chequear el código guardado: si ya se
   usó (p. ej. en otro dispositivo) se quita. Si el backend no responde,
   se deja como está (la validación final la hace el backend al crear el pedido). */
async function revalidarCodigoVerde() {
    const codigo = obtenerCodigoVerde();
    if (!codigo || typeof mvNewsletter === 'undefined' || typeof mvNewsletter.validarCodigo !== 'function') return true;

    let r;
    try {
        r = await mvNewsletter.validarCodigo(codigo);
    } catch (err) {
        return true; // sin conexión o error del servidor: se deja (el backend valida al crear el pedido)
    }
    if (r && r.valido === false) {
        guardarCodigoVerde(null);
        mostrarToast('⚠️ ' + (r.error || 'Tu Código Verde ya no es válido.') + ' Lo quitamos del carrito.');
        if (document.getElementById('carrito-panel')?.classList.contains('abierto')) renderPaso1();
        return false;
    }
    return true;
}

// Código en mayúsculas y sin caracteres raros mientras se tipea; Enter = Aplicar
document.addEventListener('input', e => {
    if (e.target && e.target.id === 'codigo-verde-input') {
        e.target.value = limpiarCodigoVerde(e.target.value);
    }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target && e.target.id === 'codigo-verde-input') {
        e.preventDefault();
        aplicarCodigoVerde();
    }
});
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
    sincronizarPreciosCarrito(); // refresca precios con los del admin
    revalidarCodigoVerde();      // chequea que el Código Verde guardado siga vigente
    cargarConfigDescuentos();    // refresca porcentajes y fecha de vencimiento
}

/* ------------------------------------------------------
   Sincronizar los precios del carrito guardado con la base
   El carrito vive en localStorage con el precio del momento en que
   se agregó cada ítem. Si el admin cambia un precio después, acá se
   actualiza (por código) para que el total que ve el cliente coincida
   con el que cobra el backend. Los productos que ya no existen o
   quedaron inactivos se sacan del carrito.
   ------------------------------------------------------ */
async function sincronizarPreciosCarrito() {
    if (typeof mvApi !== 'function') return; // api.js no está cargado en esta página

    const items = cargarCarrito();
    if (items.length === 0) return;

    let productos;
    try {
        productos = await mvApi('/productos');
    } catch (err) {
        return; // backend apagado: se deja el carrito como está
    }

    const porCodigo = {};
    productos.forEach(p => { porCodigo[String(p.codigo).toUpperCase()] = p; });

    const vigentes = [];
    const quitados = [];
    let huboCambios = false;

    items.forEach(it => {
        if (!it.codigo) { vigentes.push(it); return; } // sin código no se puede verificar
        const p = porCodigo[String(it.codigo).toUpperCase()];
        const inactivo = p && [0, '0', false].includes(p.activo);
        if (!p || inactivo) {
            quitados.push(it.nombre);
            huboCambios = true;
            return;
        }
        const precioReal = Number(p.precio);
        if (it.precio !== precioReal) {
            it.precio = precioReal;
            huboCambios = true;
        }
        vigentes.push(it);
    });

    if (!huboCambios) return;

    guardarCarrito(vigentes);
    actualizarBadge();
    if (document.getElementById('carrito-panel')?.classList.contains('abierto')) {
        renderPaso1();
    }
    if (quitados.length) {
        mostrarToast('⚠️ Ya no está disponible: ' + quitados.join(', '));
    } else {
        mostrarToast('🌿 Actualizamos los precios de tu carrito');
    }
}

document.addEventListener('DOMContentLoaded', sincronizarPreciosCarrito);

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
   <div id="checkout-paso-3">…</div>   ← forma de pago (alias bancario / tarjeta; el comprobante se envía por WhatsApp)
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

    const desc = calcularDescuentos(items);
    renderDescuentosCarrito(desc);
    if (totalEl) totalEl.textContent = formatoPesos(desc.total);

    // El envío gratis se evalúa sobre el subtotal (antes de descuentos)
    const total = desc.subtotal;

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

async function avanzarAPaso3Pago() {
    const entrega = document.querySelector('input[name="formaEntrega"]:checked');
    if (!entrega) {
        mostrarToast('⚠️ Elegí una forma de entrega');
        return;
    }
    if (entrega.value === 'envio' && !validarFormularioEnvio()) return;
    if (entrega.value === 'retiro' && !validarFormularioRetiro()) return;

    // Antes de mostrar el monto a pagar, se confirma que el Código Verde siga
    // vigente (así no se transfiere / cobra un total que después cambia).
    if (obtenerCodigoVerde() && !(await revalidarCodigoVerde())) {
        irAPaso(1);
        return;
    }
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

/* ── Transferencia al alias: número de pedido + confirmación ─────────────
   El cliente ve el total y un número de pedido (MV-DDMM-NNNN) para poner en
   el concepto de la transferencia, y tilda "Ya transferí" para
   poder continuar. El pedido se guarda "pendiente de verificación" y el
   número viaja en el mensaje de WhatsApp (el comprobante se manda por ahí). */
const REF_PEDIDO_KEY = 'mvRefPedido';

function obtenerReferenciaPedido() {
    let ref = null;
    try { ref = sessionStorage.getItem(REF_PEDIDO_KEY); } catch (e) {}
    if (!ref) {
        const d = new Date();
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const n = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
        ref = `MV-${dd}${mm}-${n}`;
        try { sessionStorage.setItem(REF_PEDIDO_KEY, ref); } catch (e) {}
    }
    return ref;
}

function reiniciarReferenciaPedido() {
    try { sessionStorage.removeItem(REF_PEDIDO_KEY); } catch (e) {}
    document.querySelectorAll('.chk-transferencia').forEach(c => { c.checked = false; });
}

function transferenciaConfirmada() {
    const chk = document.querySelector('.chk-transferencia');
    return !chk || chk.checked;   // si la hoja no tiene la casilla, no se exige
}

function actualizarPanelTransferencia() {
    const total = '$' + calcularTotal(cargarCarrito()).toLocaleString('es-AR');
    const ref = obtenerReferenciaPedido();
    document.querySelectorAll('.pago-total').forEach(el => { el.textContent = total; });
    document.querySelectorAll('.pago-numero').forEach(el => { el.textContent = ref; });
    refrescarBotonTransferencia();
}

// El botón de continuar queda bloqueado hasta tildar "Ya transferí".
function refrescarBotonTransferencia() {
    const metodo = document.querySelector('input[name="metodoPago"]:checked');
    const btn = document.getElementById('btnContinuarPago');
    if (!btn || !metodo || metodo.value !== 'transferencia') return;
    const ok = transferenciaConfirmada();
    btn.disabled = !ok;
    btn.textContent = ok ? 'Ver resumen del pedido →' : 'Tildá "Ya transferí" para continuar';
}

document.addEventListener('change', e => {
    if (e.target && e.target.matches && e.target.matches('.chk-transferencia')) refrescarBotonTransferencia();
});

/* ── PASO 3: Forma de pago ───────────────────────────── */
// Se paga por alias bancario o tarjeta, sea cual sea la forma de entrega
// (retiro en local o envío a domicilio). El comprobante se envía por WhatsApp.
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
    mercadopago: 'detalle-mercadopago',
    modo: 'detalle-modo',
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

    if (valor === 'transferencia') actualizarPanelTransferencia();

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
        mostrarToast('⚠️ Ese método todavía no está disponible. Elegí Alias Bancario o Tarjeta.');
        return;
    }


    if (metodo.value === 'transferencia' && !transferenciaConfirmada()) {
        mostrarToast('⚠️ Tildá "Ya transferí" para continuar');
        return;
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
    transferencia: 'Alias Bancario',
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
    const desc = calcularDescuentos(items);
    const totalFinal = desc.total;

    // Resumen de productos
    const resumenEl = document.getElementById('confirm-productos');
    if (resumenEl) {
        resumenEl.innerHTML = items.map(i => `
            <div class="confirm-item">
                <span>${i.nombre} ×${i.qty}</span>
                <span>$${(i.precio * i.qty).toLocaleString('es-AR')}</span>
            </div>
        `).join('') + htmlResumenDescuentos(desc);
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
            ${metodo === 'transferencia' ? `
            <div class="confirm-item">
                <span>Nº de pedido</span>
                <span><strong>${obtenerReferenciaPedido()}</strong></span>
            </div>
            <div class="confirm-item">
                <span>Estado del pago</span>
                <span><span class="badge-pendiente-verif">Pendiente de verificación</span></span>
            </div>` : ''}
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

    // Descuentos: el backend DEBE recalcularlos y validar el Código Verde
    // (existe, es de único uso y no fue usado). Esto viaja solo de referencia.
    const desc = calcularDescuentos(items);
    payload.codigo_verde = obtenerCodigoVerde() || '';
    payload.descuentos = {
        subtotal: desc.subtotal,
        inauguracion_pct: desc.lineas.find(l => l.id === 'inauguracion')?.pct || 0,
        newsletter_pct: desc.lineas.find(l => l.id === 'newsletter')?.pct || 0,
        descuento: desc.descuento,
        total: desc.total,
    };

    // Pago con tarjeta ya aprobado por el Brick: se manda el id de pago de
    // Mercado Pago para que el backend lo asocie al pedido (sin comprobante).
    if (metodo === 'tarjeta' && pagoTarjetaAprobado) {
        payload.pago_mp = {
            payment_id: pagoTarjetaAprobado.payment_id,
            status: pagoTarjetaAprobado.status,
        };
    }

    // Transferencia al alias: el pedido se crea como "pendiente" (hasta que se
    // marque como pagado en el admin) y se manda su número de referencia
    // (el backend debe guardar el campo referencia_pago).
    if (metodo === 'transferencia') {
        payload.referencia_pago = obtenerReferenciaPedido();
    }

    // El comprobante de pago ya no se adjunta en la página: el cliente lo
    // envía por WhatsApp.
    try {
        return await mvPedidos.crear(payload);
    } catch (err) {
        console.warn('No se pudo guardar el pedido en el backend:', err.message);

        // Error del Código Verde (ya usado / inválido): el pedido NO se guardó y el
        // total cambia. Se quita el código y se vuelve al paso 1 en vez de mandar
        // por WhatsApp un pedido con un monto desactualizado.
        // También cubre "Venció el descuento de inauguración" (mismo tratamiento).
        if (/código verde|descuento/i.test(err.message)) {
            const esCodigo = /código verde/i.test(err.message);
            if (esCodigo) guardarCodigoVerde(null);
            cargarConfigDescuentos();
            mostrarToast('⚠️ ' + err.message + (esCodigo ? ' Lo quitamos del carrito: revisá el nuevo total.' : ''));
            irAPaso(1);
            return false;
        }
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

    if ((await guardarPedidoEnBackend()) === false) return; // false = se canceló por el Código Verde

    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';
    const desc = calcularDescuentos(items);
    const totalFinal = desc.total;

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
        (metodo === 'transferencia'
            ? `🔖 *Nº de pedido:* ${obtenerReferenciaPedido()}\n✅ Ya transferí. Te envío el comprobante por este chat.`
            : `📎 Adjunto el comprobante de pago`) +
        lineasEnvio +
        textoDescuentosPedido(desc, true) +
        `\n\n*Total: $${totalFinal.toLocaleString('es-AR')}*`;

    window.open(`https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(mensaje)}`, '_blank');

    // Mostrar confirmación y vaciar carrito
    mostrarConfirmacionPedido();
}

/* ── Enviar por email ─────────────────────────────────── */
async function enviarPedidoMail() {
    const items = cargarCarrito();
    if (items.length === 0) { mostrarToast('El carrito está vacío'); return; }

    if ((await guardarPedidoEnBackend()) === false) return; // false = se canceló por el Código Verde

    const entrega = document.querySelector('input[name="formaEntrega"]:checked')?.value || 'retiro';
    const metodo = document.querySelector('input[name="metodoPago"]:checked')?.value || 'transferencia';
    const desc = calcularDescuentos(items);
    const totalFinal = desc.total;

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
        (metodo === 'transferencia' ? `\nNº de pedido: ${obtenerReferenciaPedido()}` : '') +
        `\n(No olvidar adjuntar el comprobante de pago a este mail)` +
        lineasEnvio +
        textoDescuentosPedido(desc, false) +
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

        // Transferencia: mostrar el número de pedido y que el pago se está verificando
        const metodoUsado = document.querySelector('input[name="metodoPago"]:checked')?.value;
        const refFinal = document.querySelector('.pedido-referencia-final');
        if (refFinal) {
            const mostrar = metodoUsado === 'transferencia';
            refFinal.style.display = mostrar ? 'block' : 'none';
            if (mostrar) {
                document.querySelectorAll('.pago-numero-final').forEach(el => { el.textContent = obtenerReferenciaPedido(); });
            }
        }
    }

    // Reset del pago con tarjeta para que el próximo checkout arranque limpio
    pagoTarjetaAprobado = null;
    if (mpBrickController && typeof mpBrickController.unmount === 'function') {
        mpBrickController.unmount();
    }
    mpBrickController = null;

    reiniciarReferenciaPedido();
    guardarCodigoVerde(null);   // el Código Verde es de único uso: se descarta al confirmar el pedido
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
            mvOcultarPopupNewsletter();
            migrarCarritoGuest();
            marcarRecordatorioCarrito();
            // Suscriptor con 10% sin usar: queda aplicado en el carrito
            if (data.codigo_verde_pendiente) guardarCodigoVerde(limpiarCodigoVerde(data.codigo_verde_pendiente));

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


/* Tiempo máximo (ms) que se muestra el cartel de bienvenida antes de entrar al sitio:
   30 segundos, o hasta que la persona mueva el mouse / toque la pantalla / apriete
   una tecla (lo que ocurra primero). */
const MV_CARTEL_MS = 30000;
/* Ignora la interacción del primer instante: el mouse todavía se está moviendo
   por el click en "Crear cuenta" y cerraría el cartel sin que nadie lo vea. */
const MV_CARTEL_GRACIA_MS = 1200;

function mvEsperarInteraccion(alTerminar, maxMs) {
    let hecho = false;
    const eventos = ['mousemove', 'mousedown', 'touchstart', 'touchmove', 'keydown', 'wheel'];
    const terminar = () => {
        if (hecho) return;
        hecho = true;
        clearTimeout(timer);
        eventos.forEach(ev => window.removeEventListener(ev, terminar, true));
        alTerminar();
    };
    const timer = setTimeout(terminar, maxMs);
    setTimeout(() => {
        if (hecho) return;
        eventos.forEach(ev => window.addEventListener(ev, terminar, { capture: true, passive: true }));
    }, MV_CARTEL_GRACIA_MS);
}

/* ── Registro ── */
/* Piso y Dpto son obligatorios y van en columnas propias (usuarios.piso / usuarios.dpto).
   Si es una casa, se pone "0" o "-". */
function mvPisoDpto() {
    return {
        piso: (document.getElementById('reg-piso')?.value || '').trim(),
        dpto: (document.getElementById('reg-dpto')?.value || '').trim(),
    };
}

/* Registro: código postal y fecha de nacimiento son obligatorios; el teléfono es
   opcional (si lo completa, se valida). Muestra el error en cada campo y devuelve
   true si todo está bien. */
function mvValidarDatosRegistro(v) {
    const ids = ['err-telefono', 'err-cp', 'err-fecha'];
    ids.forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
    const error = (id, msg) => {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.style.display = 'block'; }
        return false;
    };
    let ok = true;
    const pd = mvPisoDpto();
    if (!pd.piso || !pd.dpto) {
        ok = error('err-dom-completo', 'Completá Piso y Dpto (si es casa, poné 0 o -).');
    }
    // El teléfono es opcional: solo se valida si lo completó.
    if (v.telefono && !/^\d{8,15}$/.test(v.telefono)) ok = error('err-telefono', 'El teléfono debe tener entre 8 y 15 números.');
    if (!/^\d{4}$/.test(v.cp || '')) ok = error('err-cp', 'Ingresá tu código postal de 4 dígitos.');
    if (!v.fechaNacimiento) {
        ok = error('err-fecha', 'Ingresá tu fecha de nacimiento.');
    } else {
        const f = new Date(v.fechaNacimiento + 'T00:00:00');
        if (isNaN(f) || f > new Date() || f.getFullYear() < 1900) ok = error('err-fecha', 'La fecha de nacimiento no es válida.');
    }
    return ok;
}

const registroForm = document.getElementById('registroForm');
if (registroForm) {
    /* ── Modo "completar registro" ──
       registro.html?completar=1, con sesión iniciada. Es para quien se sumó por
       el newsletter (registro corto): nombre y email ya están, no se pide
       contraseña y solo se cargan los datos pendientes. */
    const modoCompletar = new URLSearchParams(window.location.search).get('completar') === '1';

    if (modoCompletar) {
        document.addEventListener('DOMContentLoaded', () => {
            const usuario = mvUsuarioActual();
            if (!usuario) { window.location.href = 'login.html'; return; }

            const nom = document.getElementById('reg-nombre');
            const mai = document.getElementById('reg-email');
            nom.value = usuario.nombre || ''; nom.readOnly = true;
            mai.value = usuario.email || '';  mai.readOnly = true;

            ['reg-password', 'reg-password2'].forEach(id => {
                const bloque = document.getElementById(id)?.closest('.form_input');
                if (bloque) bloque.style.display = 'none';
            });
            const titulo = registroForm.querySelector('.tit_news');
            if (titulo) titulo.textContent = 'Completá tu registro';
            const sub = registroForm.querySelector('.pf');
            if (sub) sub.innerHTML = '<strong>Ya sos parte de Mundo Verde: sumá tus datos para comprar más rápido</strong>';
            const btn = registroForm.querySelector('button[type="submit"]');
            if (btn) btn.textContent = 'Guardar mis datos 🌱';
            const pie = registroForm.lastElementChild;
            if (pie && pie.tagName === 'P') pie.style.display = 'none';   // "¿Ya tenés cuenta?"
        });
    }

    const completarRegistroCorto = async function () {
        const telefono = document.getElementById('reg-telefono').value.trim();
        const domicilioCompleto = document.getElementById('reg-dom-completo').value.trim();
        const localidad = document.getElementById('reg-localidad').value.trim();
        const cp = document.getElementById('reg-cp').value.trim();
        const fechaNacimiento = document.getElementById('reg-fecha-nacimiento').value;

        const errDom = document.getElementById('err-dom-completo');
        const errLoc = document.getElementById('err-localidad');
        const errGeneral = document.getElementById('err-general');
        [errDom, errLoc, errGeneral].forEach(el => el.style.display = 'none');

        let ok = true;
        if (!domicilioCompleto) { errDom.textContent = 'Ingresá tu domicilio completo.'; errDom.style.display = 'block'; ok = false; }
        if (!localidad) { errLoc.textContent = 'Ingresá tu localidad.'; errLoc.style.display = 'block'; ok = false; }
        if (!mvValidarDatosRegistro({ telefono, cp, fechaNacimiento })) ok = false;
        if (!ok) return;

        const btnSubmit = registroForm.querySelector('button[type="submit"]');
        if (btnSubmit) btnSubmit.disabled = true;

        try {
            const data = await mvAuth.completarPerfil({
                telefono,
                domicilio_completo: domicilioCompleto,
                ...mvPisoDpto(),
                localidad,
                codigo_postal: cp,
                fecha_nacimiento: fechaNacimiento,
            });

            // Se actualiza la sesión guardada (ya no figura como incompleta).
            const usuario = data.usuario || Object.assign({}, mvUsuarioActual(), { perfil_completo: 1 });
            mvSetSesion(mvGetToken(), usuario);

            registroForm.style.display = 'none';
            document.getElementById('conf-titulo').textContent = '¡Registro completo!';
            document.getElementById('conf-nombre-texto').textContent =
                `Gracias, ${usuario.nombre}. Ya tenés tus datos cargados: tu próxima compra va a ser más rápida. 🌿`;
            document.getElementById('msg-confirmacion').style.display = 'block';
            mvEsperarInteraccion(() => { window.location.href = '1_0_vivero.html'; }, MV_CARTEL_MS);
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
            if (btnSubmit) btnSubmit.disabled = false;
        }
    };

    registroForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (modoCompletar) { await completarRegistroCorto(); return; }

        const nombre = document.getElementById('reg-nombre').value.trim();
        const email = document.getElementById('reg-email').value.trim();
        const telefono = document.getElementById('reg-telefono').value.trim();
        const domicilioCompleto = document.getElementById('reg-dom-completo').value.trim();
        const piso = document.getElementById('reg-piso').value.trim();
        const dpto = document.getElementById('reg-dpto').value.trim();  
        const localidad = document.getElementById('reg-localidad').value.trim();
        const cp = document.getElementById('reg-cp').value.trim();
        const fechaNacimiento = document.getElementById('reg-fecha-nacimiento').value;
        const password = document.getElementById('reg-password').value;
        const password2 = document.getElementById('reg-password2').value;

        const errNombre = document.getElementById('err-nombre');
        const errEmail = document.getElementById('err-email');
        const errDomCompleto = document.getElementById('err-dom-completo');
        const errPiso = document.getElementById('err-piso');
        const errDpto = document.getElementById('err-dpto');
        const errLocalidad = document.getElementById('err-localidad');
        const errPassword = document.getElementById('err-password');
        const errPassword2 = document.getElementById('err-password2');
        const errGeneral = document.getElementById('err-general');

        [errNombre, errEmail, errDomCompleto, errPiso, errDpto, errLocalidad, errPassword, errPassword2, errGeneral]
            .forEach(el => el.style.display = 'none');

        let ok = true;
        if (!nombre) { errNombre.style.display = 'block'; ok = false; }
        if (!email.includes('@')) { errEmail.style.display = 'block'; ok = false; }
        if (!domicilioCompleto) {
            errDomCompleto.textContent = 'Ingresá tu domicilio completo.';
            errDomCompleto.style.display = 'block';
            ok = false;
        }
        if (!piso) {
            errPiso.textContent = 'Ingresá tu piso.';
            errPiso.style.display = 'block';
            ok = false;
        }
        if (!dpto) {
            errDpto.textContent = 'Ingresá tu departamento.';
            errDpto.style.display = 'block';
            ok = false;
        }
        if (!localidad) {
            errLocalidad.textContent = 'Ingresá tu localidad.';
            errLocalidad.style.display = 'block';
            ok = false;
        }
        if (!mvValidarDatosRegistro({ telefono, cp, fechaNacimiento })) ok = false;
        if (password.length < 8) { errPassword.style.display = 'block'; ok = false; }
        if (password !== password2) { errPassword2.style.display = 'block'; ok = false; }
        if (!ok) return;

        const btnSubmit = registroForm.querySelector('button[type="submit"]');
        if (btnSubmit) btnSubmit.disabled = true;

        try {
            // El backend crea la cuenta, la deja logueada (devuelve token) y manda
            // el mail de bienvenida en paralelo, sin hacer esperar la respuesta.
            const data = await mvAuth.registro({
                nombre, email, telefono,
                domicilio_completo: domicilioCompleto,
                piso,
                dpto,
                localidad,
                codigo_postal: cp,
                fecha_nacimiento: fechaNacimiento,
                password,
            });

            // 1) Queda logueado al instante (el header ya lo muestra logueado
            //    en la próxima página).
            mvSetSesion(data.token, data.usuario);
            mvOcultarPopupNewsletter();   // ya tiene cuenta: el popup del newsletter no vuelve a aparecer
            migrarCarritoGuest();
            marcarRecordatorioCarrito();

            // 2) Cartel de bienvenida: mismo texto que el mail.
            registroForm.style.display = 'none';
            const saludo = data.nombre_saludo || data.usuario.nombre;   // sale del mail cargado
            document.getElementById('conf-titulo').textContent = `¡Hola, ${saludo}!`;
            const texto = document.getElementById('conf-nombre-texto');
            texto.style.whiteSpace = 'pre-line';
            texto.textContent = '';
            texto.innerHTML =
                'Gracias ' + saludo.replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';') +
                ' por crear tu cuenta en <strong>Mundo Verde</strong>. ' +
                'Ya podés iniciar sesión para comprar plantas y productos, ' +
                'reservar talleres y seguir tus pedidos.\n\n' +
                'Si tenés cualquier duda, respondé este mail y te contestamos.\n\n' +
                '🌿 El equipo de Mundo Verde';
            document.getElementById('msg-confirmacion').style.display = 'block';

            // Después del cartel, entra al sitio ya logueado.
            mvEsperarInteraccion(() => { window.location.href = '1_0_vivero.html'; }, MV_CARTEL_MS);
        } catch (err) {
            errGeneral.textContent = '⚠️ ' + err.message;
            errGeneral.style.display = 'block';
            if (btnSubmit) btnSubmit.disabled = false;
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

        const mail    = document.getElementById('mail').value.trim();
        const origen  = document.getElementById('origen').value;
        const codRef  = document.getElementById('codigo-referido').value.trim().toUpperCase();
        const tyc     = document.getElementById('acepta-tyc').checked;

        let ok = true;
        const show = (id, mostrar) => {
            document.getElementById(id).style.display = mostrar ? 'block' : 'none';
        };

        show('err-mail', !mail.includes('@'));  if (!mail.includes('@')) ok = false;
        show('err-tyc', !tyc);         if (!tyc) ok = false;
        show('err-ref', false);

        if (!ok) return;

        const btnSubmit = this.querySelector('button[type="submit"]');
        if (btnSubmit) btnSubmit.disabled = true;

        try {
            const data = await mvNewsletter.suscribir({
                mail,
                origen,
                cod_ref: origen === 'Referido' ? codRef : '',
            });

            localStorage.setItem('mv_newsletter_mail', mail);

            // Cuenta creada = queda logueado al instante, igual que en el registro.
            // La ÚNICA diferencia con un registro común: el Código Verde (10%, un
            // solo uso) ya queda cargado en el carrito, sin tener que tipearlo.
            if (data.token && data.usuario) {
                mvSetSesion(data.token, data.usuario);
                migrarCarritoGuest();
                marcarRecordatorioCarrito();
            }
            if (data.codigo_verde) guardarCodigoVerde(limpiarCodigoVerde(data.codigo_verde));

            this.style.display = 'none';
            const conf = document.getElementById('msg-confirmacion');
            document.getElementById('conf-nombre-texto').textContent = data.nombre ? `¡Hola, ${data.nombre}!` : '¡Hola!';
            document.getElementById('conf-codigo').textContent = data.codigo_mio;
            const elVerde = document.getElementById('conf-codigo-verde');
            if (elVerde) elVerde.textContent = data.codigo_verde || '';
            const elPct = document.getElementById('conf-pct');
            if (elPct) elPct.textContent = CONFIG_DESCUENTOS.newsletterPct;
            conf.style.display = 'block';

            document.getElementById('conf-codigo').addEventListener('click', () => {
                navigator.clipboard.writeText(data.codigo_mio).catch(() => {});
            });
            if (elVerde) {
                elVerde.addEventListener('click', () => {
                    navigator.clipboard.writeText(data.codigo_verde).catch(() => {});
                });
            }

            // No se redirige solo: la pantalla ofrece "Completar mi registro" e
            // "Ir a comprar" (ya logueado y con el 10% aplicado). El botón de
            // completar solo se muestra si a la cuenta le faltan datos.
            // Si el servidor no creó cuenta (no devolvió token), no se dice que hay sesión.
            const txtCuenta = document.getElementById('conf-cuenta-texto');
            if (txtCuenta && !data.token) txtCuenta.textContent = '¡Felicitaciones, ya estás suscripto/a!';
            const txtCompletar = document.getElementById('conf-completar-texto');
            if (txtCompletar && !data.token) txtCompletar.style.display = 'none';
            const btnCompletar = document.getElementById('btn-completar-registro');
            if (btnCompletar) btnCompletar.style.display = mvPerfilIncompleto(mvUsuarioActual()) ? '' : 'none';
        } catch (err) {
            if (btnSubmit) btnSubmit.disabled = false;
            // "Ese email ya está suscripto": se recuerda para que el popup no reaparezca.
            if (err.status === 409 && /suscript/i.test(err.message)) localStorage.setItem('mv_newsletter_mail', mail);
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
                    `Hola ${data.nombre}. Tu <strong>Código Verde</strong> (${CONFIG_DESCUENTOS.newsletterPct}% de descuento, un solo uso): ` +
                    `<span class="codigo-ref-mini">${data.codigo_verde}</span>` +
                    (data.codigo_verde_usado ? ' <em>(ya lo usaste)</em>' : '') +
                    `<br>Tu código para referir amigos: <span class="codigo-ref-mini">${data.codigo_mio}</span> ` +
                    `— ya tenés <strong>${data.referidos_exitosos}</strong> referido(s) que compraron.`;
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

