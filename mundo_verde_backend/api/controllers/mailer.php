<?php
/**
 * Envío de mails con PHPMailer por SMTP (casilla de Hostinger).
 * Las credenciales viven en config_mail.php, NUNCA en este archivo.
 *
 * Requiere PHPMailer en:  controllers/phpmailer/src/{Exception,PHPMailer,SMTP}.php
 * (descargar de https://github.com/PHPMailer/PHPMailer → carpeta "src")
 *
 * Todos los mails salen SIEMPRE desde info@vivemundoverde.com y las
 * respuestas de los usuarios llegan a viveunmundoverde@gmail.com (Reply-To).
 */

use PHPMailer\PHPMailer\PHPMailer;

/** Crea un PHPMailer ya configurado (SMTP + remitente). Lanza excepción si falta algo. */
function crearMailer(): PHPMailer
{
    $base    = __DIR__ . '/phpmailer/src/';
    $cfgPath = __DIR__ . '/config_mail.php';
    if (!file_exists($base . 'PHPMailer.php')) {
        throw new \RuntimeException('Falta PHPMailer en ' . $base);
    }
    require_once $base . 'Exception.php';
    require_once $base . 'PHPMailer.php';
    require_once $base . 'SMTP.php';

    // Datos NO secretos, ya cargados. config_mail.php puede pisarlos si hace falta.
    $cfg = [
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,                          // 465 = SSL, 587 = STARTTLS
        'usuario'    => 'info@vivemundoverde.com',
        'password'   => '',                           // viene de config_mail.php
        'from_email' => 'info@vivemundoverde.com',
        'from_name'  => 'Mundo Verde',
        'reply_to'   => 'viveunmundoverde@gmail.com', // las respuestas llegan a esta casilla
    ];
    if (file_exists($cfgPath)) {
        $cfg = array_merge($cfg, (array) require $cfgPath);
    }

    $password = trim((string) $cfg['password']);
    if ($password === '' || strpos($password, 'PEGAR') !== false) {
        throw new \RuntimeException('Falta la contraseña de la casilla en config_mail.php');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->Port       = (int) $cfg['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['usuario'];
    $mail->Password   = $password;
    $mail->SMTPSecure = (int) $cfg['port'] === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addReplyTo($cfg['reply_to'] ?: $cfg['from_email'], $cfg['from_name']);
    $mail->isHTML(true);
    return $mail;
}

/**
 * Nombre "para saludar" sacado del mail: crixus@gmail.com -> "Crixus",
 * juan.perez+tienda@x.com -> "Juan Perez", maria_lopez92@x.com -> "Maria Lopez".
 * Se usa tanto en el mail de bienvenida como en el cartel de pantalla, así los
 * dos dicen exactamente lo mismo.
 */
function nombreDesdeEmail(string $email): string
{
    $local = explode('@', trim($email))[0];
    $local = explode('+', $local)[0];                    // saca etiquetas tipo +tienda
    $limpio = preg_replace('/\d+$/', '', $local);         // saca números finales (crixus92)
    $limpio = $limpio !== '' ? $limpio : $local;
    $partes = preg_split('/[._\-\s]+/', $limpio, -1, PREG_SPLIT_NO_EMPTY);
    if (!$partes) return 'Cliente';
    $partes = array_map(
        fn($p) => mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($p, 1, null, 'UTF-8'), 'UTF-8'),
        $partes
    );
    return implode(' ', $partes);
}

/**
 * Saludo de bienvenida al usuario que acaba de crear su cuenta.
 * Se envía al mail que dejó en el registro.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailBienvenida(string $email, string $nombre): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $saludo = nombreDesdeEmail($email);   // concuerda con el mail cargado
        $n = htmlspecialchars($saludo, ENT_QUOTES, 'UTF-8');

        $mail->Subject = '¡Bienvenido/a a Mundo Verde! 🌱';
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;">' .
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<p>Gracias ' . $n . ' por crear tu cuenta en <strong>Mundo Verde</strong>. ' .
            'Ya podés iniciar sesión para comprar plantas y productos, ' .
            'reservar talleres y seguir tus pedidos.</p>' .
            '<p>Si tenés cualquier duda, respondé este mail y te contestamos.</p>' .
            '<p style="margin-top:24px;">🌿 El equipo de Mundo Verde</p>' .
            '</div>';
        $mail->AltBody =
            "¡Hola, $saludo!\n\n" .
            "Gracias $saludo por crear tu cuenta en Mundo Verde. Ya podés iniciar sesión " .
            "para comprar plantas y productos, reservar talleres y seguir tus pedidos.\n\n" .
            "Si tenés cualquier duda, respondé este mail.\n\nEl equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando bienvenida a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}


/**
 * Envuelve el contenido de un mail con el estilo común de Mundo Verde
 * (caja centrada, texto verde, pie con el tip anti-spam y la firma).
 */
function envolverMailMV(string $contenidoHtml): string
{
    return
        '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;text-align:center;">' .
        $contenidoHtml .
        '<p style="font-size:12px;color:#777;background:#fafafa;padding:10px;border-radius:6px;">' .
        '💡 Agregá <em>info@vivemundoverde.com</em> a tus contactos y si este mail llegó a ' .
        'Spam o Promociones, marcalo como "No es spam" para no perderte nuestras novedades.</p>' .
        '<p style="margin-top:24px;">🌿 El equipo de Mundo Verde</p>' .
        '</div>';
}

/** Recuadro verde grande con un código (mismo estilo que usaba el mail original). */
function recuadroCodigoMV(string $codigo): string
{
    $c = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
    return
        '<p style="margin:24px 0;">' .
        '<span style="display:inline-block;background:#2e7d32;color:#fff;font-size:24px;' .
        'font-weight:bold;letter-spacing:4px;padding:12px 28px;border-radius:8px;">' . $c . '</span></p>';
}

/**
 * MAIL 1 — Bienvenida + CÓDIGO VERDE (el 10% de descuento, de un solo uso).
 * Es el código que se ingresa en el paso 1 del carrito.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailCodigoVerde(string $email, string $nombre, string $codigoVerde): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $mail->Subject = '¡Bienvenidx a Mundo Verde! Acá está tu 10% de descuento 🌱';
        $mail->Body = envolverMailMV(
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<h2 style="color:#2e7d32;">¡Bienvenidx a Mundo Verde!</h2>' .
            '<p>🎉 Estamos tan contentos de estarque te regalamos ' .
            '<strong>10% de descuento</strong> en tu primera compra. 🎉</p>' .
            '<p>Tu cuenta ya está creada. Cuando tengas tu 🛒 carrito armado, iniciá sesión; ' .
            'si el descuento no aparece solo, ingresá este <strong style="color:green;">Código Verde</strong> ' .
            'en el paso 1 del carrito:</p>' .
            recuadroCodigoMV($codigoVerde) .
            '<p style="font-size:13px;color:#666;">Es de un solo uso. Guardá este mail 📧</p>' .
            '<p>Vas a recibir novedades, promos y talleres exclusivos en tu email.</p>'
        );
        $mail->AltBody =
            "¡Hola, $nombre!\n\n" .
            "¡Bienvenidx a Mundo Verde! Te regalamos 10% de descuento en tu primera compra.\n\n" .
            "Tu cuenta ya está creada. Cuando tengas tu carrito armado, iniciá sesión; si el " .
            "descuento no aparece solo, ingresá este Código Verde en el paso 1 del carrito:\n\n" .
            "Código Verde: $codigoVerde\n\n" .
            "Es de un solo uso. Guardá este mail.\n\n" .
            "Tip: agregá info@vivemundoverde.com a tus contactos y, si este mail llegó a Spam " .
            "o Promociones, marcalo como \"No es spam\".\n\n" .
            "El equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando código verde a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * MAIL 2 — Código personal para REFERIR amigos (no es el del descuento).
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailReferidos(string $email, string $nombre, string $codigoReferido): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $mail->Subject = 'Tu código para referir amigos a Mundo Verde 🍀';
        $mail->Body = envolverMailMV(
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<p>🍀 Este es tu <strong style="color:green;">código para referir amigos</strong> 🍀</p>' .
            recuadroCodigoMV($codigoReferido) .
            '<p>Guardá este mail 📧 y compartí tu código: si <strong>3 amigos tuyos</strong> se suscriben ' .
            'a nuestro newsletter con tu código, sumás <strong>10% más</strong> de descuento*.</p>' .
            '<p style="font-size:13px;color:#666;">Este código <strong>no</strong> es el del descuento de bienvenida: ' .
            'ese te llegó en otro mail.</p>' .
            '<p style="font-size:12px;color:#777;">*Sujeto a las condiciones de la promoción.</p>'
        );
        $mail->AltBody =
            "¡Hola, $nombre!\n\n" .
            "Este es tu código para referir amigos: $codigoReferido\n\n" .
            "Si 3 amigos tuyos se suscriben a nuestro newsletter con tu código, sumás 10% más " .
            "de descuento*. Este código no es el del descuento de bienvenida: ese te llegó en otro mail.\n\n" .
            "*Sujeto a las condiciones de la promoción.\n\n" .
            "El equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando código de referidos a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * MAIL 3 — "Beneficios exclusivos": repite lo que anuncia el popup de la web
 * (10% en la primera compra, ofertas únicas y gift cards especiales).
 * No lleva ningún código: el descuento está en el mail 1.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailBeneficios(string $email, string $nombre): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $item = fn(string $icono, string $titulo, string $texto) =>
            '<div style="margin:14px 0;">' .
            '<div style="font-size:28px;">' . $icono . '</div>' .
            '<div style="font-weight:bold;color:#2e7d32;font-size:16px;">' . $titulo . '</div>' .
            '<div style="font-size:14px;color:#555;">' . $texto . '</div>' .
            '</div>';

        $mail->Subject = 'Tus beneficios exclusivos en Mundo Verde 🎁';
        $mail->Body = envolverMailMV(
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<p>Por sumarte a la comunidad de <strong>Mundo Verde</strong>, estos son tus beneficios exclusivos:</p>' .
            $item('🌿', '10% en tu primera compra', 'Usá el Código Verde que te enviamos en otro mail.') .
            // TODO: acá van los detalles reales de ofertas y gift cards cuando estén definidos.
            $item('🏷️', 'Ofertas únicas', 'Vas a recibir nuestras ofertas únicas en tu email.') .
            $item('🎁', 'Gift cards especiales', 'Vas a recibir las novedades de nuestras gift cards especiales en tu email.')
        );
        $mail->AltBody =
            "¡Hola, $nombre!\n\n" .
            "Por sumarte a la comunidad de Mundo Verde, estos son tus beneficios exclusivos:\n\n" .
            "- 10% en tu primera compra: usá el Código Verde que te enviamos en otro mail.\n" .
            "- Ofertas únicas: las vas a recibir en tu email.\n" .
            "- Gift cards especiales: vas a recibir las novedades en tu email.\n\n" .
            "El equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando beneficios a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Al suscribirse al newsletter: manda los TRES mails, en este orden.
 * El del Código Verde sale primero; si los otros fallan, el descuento ya llegó.
 * Devuelve true si el mail del Código Verde se envió.
 *
 * Mantiene el nombre viejo (enviarMailNewsletter) para no romper al que lo llama.
 * OJO: el 3er parámetro ahora es el CÓDIGO VERDE (antes recibía el de referidos).
 * Si $codigoReferido viene vacío solo sale el mail 1 (los mails 2 y 3 se omiten).
 */
function enviarMailNewsletter(string $email, string $nombre, string $codigoVerde, string $codigoReferido = ''): bool
{
    $ok = enviarMailCodigoVerde($email, $nombre, $codigoVerde);
    if ($codigoReferido !== '') {
        enviarMailReferidos($email, $nombre, $codigoReferido);
        enviarMailBeneficios($email, $nombre);
    }
    return $ok;
}


/* ═══════════════════════════════════════════════════════════════════════
 * REMITO DE PEDIDO — mail al comprador con el resumen de su compra
 * (mismo contenido que el paso 4 "Confirmar pedido", con formato de remito).
 * ═══════════════════════════════════════════════════════════════════════ */

const ALIAS_BANCARIO_MV = 'AGUS.MUNDO.VERDE';

function moneyMV(float $n): string
{
    return '$' . number_format($n, 0, ',', '.');
}

/**
 * Envía el remito del pedido. $p espera:
 *   id, referencia (MV-DDMM-NNNN o null), fecha (string), email, nombre,
 *   forma_entrega ('retiro'|'envio'), forma_pago ('efectivo'|'transferencia'|'tarjeta'),
 *   retiro_nombre, retiro_dni, envio_domicilio, envio_localidad, envio_cp,
 *   items [ [codigo, nombre, precio, cantidad, subtotal] ],
 *   subtotal, descuentos [ [etiqueta, monto] ], total
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailPedido(array $p): bool
{
    try {
        $email = trim((string)($p['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false; // invitado sin mail

        $h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $nro = !empty($p['referencia']) ? $p['referencia'] : '#' . $p['id'];
        $nombre = trim((string)($p['nombre'] ?? '')) !== '' ? $p['nombre'] : nombreDesdeEmail($email);

        $esRetiro = ($p['forma_entrega'] ?? '') === 'retiro';
        $metodos  = ['efectivo' => 'Efectivo', 'transferencia' => 'Alias bancario', 'tarjeta' => 'Tarjeta de crédito / débito'];
        $metodo   = $metodos[$p['forma_pago']] ?? $p['forma_pago'];
        $esTransf = $p['forma_pago'] === 'transferencia';
        $estado   = $esTransf ? 'Pendiente de verificación' : ($p['forma_pago'] === 'tarjeta' ? 'Pago procesado' : 'Pendiente de pago');

        $td  = 'padding:8px 6px;border-bottom:1px solid #e5e5e5;font-size:13px;';
        $lbl = 'padding:5px 0;font-size:13px;color:#777;';
        $val = 'padding:5px 0;font-size:13px;color:#222;text-align:right;';

        // Filas de ítems
        $filas = '';
        foreach ($p['items'] as $it) {
            $filas .=
                '<tr>' .
                '<td style="' . $td . 'font-family:Courier New,monospace;color:#777;">' . $h($it['codigo']) . '</td>' .
                '<td style="' . $td . '">' . $h($it['nombre']) . '</td>' .
                '<td style="' . $td . 'text-align:center;">' . (int)$it['cantidad'] . '</td>' .
                '<td style="' . $td . 'text-align:right;">' . moneyMV((float)$it['precio']) . '</td>' .
                '<td style="' . $td . 'text-align:right;"><strong>' . moneyMV((float)$it['subtotal']) . '</strong></td>' .
                '</tr>';
        }

        // Totales
        $totales = '<tr><td style="' . $lbl . '">Subtotal</td><td style="' . $val . '">' . moneyMV((float)$p['subtotal']) . '</td></tr>';
        foreach ($p['descuentos'] as [$etiqueta, $monto]) {
            if ($monto > 0) {
                $totales .= '<tr><td style="' . $lbl . 'color:#2e7d32;">' . $h($etiqueta) . '</td>' .
                            '<td style="' . $val . 'color:#2e7d32;">-' . moneyMV((float)$monto) . '</td></tr>';
            }
        }

        // Entrega
        if ($esRetiro) {
            $entrega =
                '<tr><td style="' . $lbl . '">Forma de entrega</td><td style="' . $val . '">Retiro en local</td></tr>' .
                '<tr><td style="' . $lbl . '">Retira</td><td style="' . $val . '">' . $h($p['retiro_nombre']) . '</td></tr>' .
                '<tr><td style="' . $lbl . '">DNI</td><td style="' . $val . '">' . $h($p['retiro_dni']) . '</td></tr>';
        } else {
            $entrega =
                '<tr><td style="' . $lbl . '">Forma de entrega</td><td style="' . $val . '">Envío a domicilio</td></tr>' .
                '<tr><td style="' . $lbl . '">Domicilio</td><td style="' . $val . '">' . $h($p['envio_domicilio']) . '</td></tr>' .
                '<tr><td style="' . $lbl . '">Localidad</td><td style="' . $val . '">' . $h($p['envio_localidad']) . ' (CP ' . $h($p['envio_cp']) . ')</td></tr>';
        }

        // Bloque de instrucciones (solo transferencia)
        $instrucciones = '';
        if ($esTransf) {
            $instrucciones =
                '<div style="margin:22px 0 0;padding:14px 16px;background:#fff8e1;border:1px solid #f0d98a;border-radius:6px;font-size:13px;color:#5d4a00;">' .
                '<strong>¿Ya transferiste?</strong> Enviános el comprobante por el mismo chat de WhatsApp indicando tu ' .
                'número de pedido <strong>' . $h($nro) . '</strong>. Cuando verifiquemos el pago te confirmamos por ahí.<br>' .
                '<span style="display:block;margin-top:8px;">Alias: <strong style="font-family:Courier New,monospace;">' . ALIAS_BANCARIO_MV . '</strong> · ' .
                'Importe: <strong>' . moneyMV((float)$p['total']) . '</strong> · Concepto: <strong>' . $h($nro) . '</strong></span>' .
                '</div>';
        }

        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $mail->Subject = 'Remito de tu pedido ' . $nro . ' — Mundo Verde 🌿';

        $mail->Body =
            '<div style="background:#f3f3f0;padding:20px 8px;font-family:Arial,sans-serif;color:#333;">' .
            '<div style="max-width:620px;margin:auto;background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;">' .

            // Encabezado
            '<table width="100%" cellspacing="0" cellpadding="0" style="background:#2e7d32;color:#fff;"><tr>' .
            '<td style="padding:18px 22px;"><div style="font-size:20px;font-weight:bold;">Mundo Verde 🌿</div>' .
            '<div style="font-size:12px;opacity:.85;">Vivero · Berazategui, Buenos Aires</div></td>' .
            '<td style="padding:18px 22px;text-align:right;"><div style="font-size:11px;letter-spacing:2px;opacity:.85;">REMITO DE PEDIDO</div>' .
            '<div style="font-size:18px;font-weight:bold;font-family:Courier New,monospace;">' . $h($nro) . '</div></td>' .
            '</tr></table>' .

            '<div style="padding:22px;">' .
            '<p style="margin:0 0 4px;font-size:15px;">¡Hola, ' . $h($nombre) . '! Recibimos tu pedido.</p>' .
            '<p style="margin:0 0 18px;font-size:13px;color:#777;">Fecha: ' . $h($p['fecha']) . '</p>' .

            // Datos del pedido
            '<table width="100%" cellspacing="0" cellpadding="0" style="border-top:2px solid #2e7d32;">' .
            '<tr><td colspan="2" style="padding:12px 0 4px;font-size:11px;letter-spacing:1.5px;color:#999;">ENTREGA</td></tr>' .
            $entrega .
            '<tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;letter-spacing:1.5px;color:#999;">PAGO</td></tr>' .
            '<tr><td style="' . $lbl . '">Método</td><td style="' . $val . '">' . $h($metodo) . '</td></tr>' .
            '<tr><td style="' . $lbl . '">Nº de pedido</td><td style="' . $val . 'font-family:Courier New,monospace;"><strong>' . $h($nro) . '</strong></td></tr>' .
            '<tr><td style="' . $lbl . '">Estado del pago</td><td style="' . $val . '"><span style="background:#fdf0c8;border:1px solid #f0d98a;color:#7a5b00;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:bold;">' . $h($estado) . '</span></td></tr>' .
            '</table>' .

            // Detalle
            '<div style="margin-top:20px;font-size:11px;letter-spacing:1.5px;color:#999;">DETALLE</div>' .
            '<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:6px;border-collapse:collapse;">' .
            '<tr style="background:#f3f7f3;color:#2e7d32;font-size:11px;text-transform:uppercase;">' .
            '<th style="padding:8px 6px;text-align:left;">Código</th><th style="padding:8px 6px;text-align:left;">Producto</th>' .
            '<th style="padding:8px 6px;text-align:center;">Cant.</th><th style="padding:8px 6px;text-align:right;">P. unit.</th>' .
            '<th style="padding:8px 6px;text-align:right;">Importe</th></tr>' .
            $filas . '</table>' .

            // Totales
            '<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:10px;">' . $totales .
            '<tr><td style="padding:12px 0 0;font-size:17px;font-weight:bold;color:#2e7d32;border-top:2px solid #2e7d32;">TOTAL</td>' .
            '<td style="padding:12px 0 0;font-size:17px;font-weight:bold;color:#2e7d32;text-align:right;border-top:2px solid #2e7d32;">' . moneyMV((float)$p['total']) . '</td></tr>' .
            '</table>' .

            $instrucciones .
            '<p style="margin:22px 0 0;font-size:12px;color:#999;text-align:center;">Este remito es un resumen de tu pedido y no tiene validez fiscal. ' .
            'Si tenés dudas, respondé este mail.</p>' .
            '</div></div></div>';

        // Versión texto plano
        $txt = "REMITO DE PEDIDO $nro — Mundo Verde\nFecha: {$p['fecha']}\n\nHola, $nombre! Recibimos tu pedido.\n\n";
        foreach ($p['items'] as $it) {
            $txt .= "- {$it['nombre']} x{$it['cantidad']}  " . moneyMV((float)$it['subtotal']) . "\n";
        }
        $txt .= "\nSubtotal: " . moneyMV((float)$p['subtotal']) . "\n";
        foreach ($p['descuentos'] as [$etiqueta, $monto]) {
            if ($monto > 0) $txt .= "$etiqueta: -" . moneyMV((float)$monto) . "\n";
        }
        $txt .= "TOTAL: " . moneyMV((float)$p['total']) . "\n\n" .
                "Entrega: " . ($esRetiro ? "Retiro en local — {$p['retiro_nombre']} (DNI {$p['retiro_dni']})" : "Envío a {$p['envio_domicilio']}, {$p['envio_localidad']}") . "\n" .
                "Pago: $metodo — $estado\n";
        if ($esTransf) {
            $txt .= "\nAlias: " . ALIAS_BANCARIO_MV . " | Concepto: $nro\nEnviá el comprobante por WhatsApp indicando tu número de pedido.\n";
        }
        $mail->AltBody = $txt . "\nEl equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando remito de pedido ' . ($p['id'] ?? '?') . ': ' . $e->getMessage());
        return false;
    }
}
