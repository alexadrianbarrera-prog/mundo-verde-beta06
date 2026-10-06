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
            '<p>🎉 Estamos tan contentos de estar on line que te regalamos ' .
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
