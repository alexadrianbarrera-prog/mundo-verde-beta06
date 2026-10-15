const { JSDOM, VirtualConsole } = require('jsdom');
const fs = require('fs');
const html = fs.readFileSync('../1_0_vivero.html', 'utf8')
    .replace(/<script[^>]*src=[^>]*><\/script>/gi, '');          // los scripts externos se inyectan a mano
const errores = [];
const vc = new VirtualConsole(); vc.on('jsdomError', e => errores.push('jsdomError: ' + (e.detail?.message || e.message)));
vc.on('error', e => errores.push('console.error: ' + e));
const dom = new JSDOM(html, { url: 'http://localhost/', runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole: vc });
const w = dom.window;
w.QRCode = function () {}; w.QRCode.CorrectLevel = { M: 1 };
w.fetch = () => Promise.reject(new Error('sin red'));
w.scrollTo = () => {}; w.HTMLElement.prototype.scrollIntoView = () => {};
for (const f of ['/mnt/user-data/uploads/api.js', '/home/claude/entrega/js/script.js']) {
    try { w.eval(fs.readFileSync(f, 'utf8')); } catch (e) { errores.push('al cargar ' + f.split('/').pop() + ': ' + e.message); }
}
const $ = s => w.document.querySelector(s);
let ok = 0, mal = 0;
const check = (n, c, d = '') => { c ? ok++ : mal++; console.log((c ? '  ✔ ' : '  ✘ ') + n + (c ? '' : '  ← ' + d)); };
const tostadas = []; w.mostrarToast = m => tostadas.push(m);

check('script.js carga sin errores', errores.length === 0, errores.join(' | '));
check('ya no existen MERCADOPAGO_LINK ni METODOS_PROXIMAMENTE', typeof w.MERCADOPAGO_LINK === 'undefined' && typeof w.METODOS_PROXIMAMENTE === 'undefined');
check('quedan solo 2 métodos en la grilla', w.document.querySelectorAll('input[name=metodoPago]').length === 2);
check('el HTML ya no tiene #detalle-cuotasdebito', !$('#detalle-cuotasdebito'));

// Paso 3: cambiar entre los métodos
try { w.renderPaso3Pago(); check('renderPaso3Pago() corre sin error (sin botón de Mercado Pago)', true); } catch (e) { check('renderPaso3Pago() corre sin error', false, e.message); }
const vis = id => { const el = w.document.getElementById(id); return el && el.style.display !== 'none'; };
w.aplicarVisibilidadMetodo('tarjeta');
check('tarjeta: se ve el panel de Getnet y se oculta el de transferencia', vis('detalle-tarjeta') && !vis('detalle-transferencia'));
check('tarjeta: el invitado ve el campo de mail', !!$('#tarjeta-email'));
check('el botón "Ver resumen" queda habilitado', $('#btnContinuarPago').disabled === false && /Ver resumen/.test($('#btnContinuarPago').textContent));
w.aplicarVisibilidadMetodo('transferencia');
check('transferencia: se ve su panel y se oculta el de tarjeta', vis('detalle-transferencia') && !vis('detalle-tarjeta'));

// Validaciones de avanzarAPaso4
const elegir = v => { const r = $(`input[name=metodoPago][value=${v}]`); r.checked = true; };
w.irAPaso = n => { w.__paso = n; };
elegir('tarjeta'); w.aplicarVisibilidadMetodo('tarjeta');
w.avanzarAPaso4();
check('tarjeta SIN mail no avanza y avisa', w.__paso === undefined && tostadas.some(t => /mail/i.test(t)), JSON.stringify(tostadas));
$('#tarjeta-email').value = 'no-es-mail'; w.avanzarAPaso4();
check('tarjeta con mail inválido no avanza', w.__paso === undefined);
$('#tarjeta-email').value = 'cliente@test.com'; w.avanzarAPaso4();
check('tarjeta con mail válido avanza al paso 4', w.__paso === 4, w.__paso);
check('el mail escrito se conserva al volver a mostrar el panel', (w.aplicarVisibilidadMetodo('tarjeta'), $('#tarjeta-email').value === 'cliente@test.com'));
check('emailDeCompra(tarjeta) devuelve ese mail', w.emailDeCompra('tarjeta') === 'cliente@test.com');
check('emailDeCompra(transferencia) NO usa el mail del campo', w.emailDeCompra('transferencia') === '');

// Un método viejo que quedara en otra hoja HTML no debe poder avanzar
w.__paso = undefined; tostadas.length = 0;
const intruso = w.document.createElement('input'); intruso.type = 'radio'; intruso.name = 'metodoPago'; intruso.value = 'mercadopago'; intruso.checked = true;
w.document.body.appendChild(intruso);
w.avanzarAPaso4();
check('un método NO habilitado (ej. de una hoja vieja) no deja avanzar', w.__paso === undefined && tostadas.some(t => /no está disponible/.test(t)), JSON.stringify(tostadas));
intruso.remove();

// Resumen del paso 4
elegir('tarjeta');
try { w.renderPaso4Confirmar(); check('el resumen del paso 4 se arma con tarjeta (etiqueta Getnet)', /Getnet/.test(w.document.body.textContent)); } catch (e) { check('renderPaso4Confirmar() con tarjeta', false, e.message); }
check('el resumen muestra a qué mail llega el comprobante', /cliente@test\.com/.test(w.document.body.textContent));
elegir('transferencia'); w.aplicarVisibilidadMetodo('transferencia');
try { w.renderPaso4Confirmar(); check('el resumen del paso 4 se arma con transferencia (Alias Bancario)', /Alias Bancario/.test(w.document.body.textContent)); } catch (e) { check('renderPaso4Confirmar() con transferencia', false, e.message); }

console.log(`\nRESULTADO: ${ok}/${ok + mal} OK`); process.exit(mal ? 1 : 0);
