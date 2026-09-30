<?php
/**
 * Envío de mails con PHPMailer por SMTP (Gmail).
 * Las credenciales viven en config_mail.php, NUNCA en este archivo.
 *
 * Requiere PHPMailer en:  controllers/phpmailer/src/{Exception,PHPMailer,SMTP}.php
 * (descargar de https://github.com/PHPMailer/PHPMailer → carpeta "src")
 */

use PHPMailer\PHPMailer\PHPMailer;

/** Crea un PHPMailer ya configurado (SMTP + remitente). Lanza excepción si falta algo. */
function crearMailer(): PHPMailer
{
    $base    = __DIR__ . '/phpmailer/src/';
    $cfgPath = __DIR__ . '/config_mail.php';
    if (!file_exists($base . 'PHPMailer.php') || !file_exists($cfgPath)) {
        throw new \RuntimeException('Falta PHPMailer o config_mail.php');
    }
    require_once $base . 'Exception.php';
    require_once $base . 'PHPMailer.php';
    require_once $base . 'SMTP.php';
    $cfg = require $cfgPath;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->Port       = $cfg['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['usuario'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = $cfg['port'] === 465
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);
    $mail->isHTML(true);
    return $mail;
}

/**
 * Manda el mail de bienvenida a un usuario recién registrado (cuenta).
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailBienvenida(string $email, string $nombre): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $mail->Subject = '¡Bienvenido/a a Mundo Verde! 🌱';
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;">' .
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<p>Gracias por crear tu cuenta en <strong>Mundo Verde</strong>. ' .
            'Ya podés iniciar sesión para comprar plantas y productos, ' .
            'reservar talleres y seguir tus pedidos.</p>' .
            '<p>Si tenés cualquier duda, respondé este mail y te contestamos.</p>' .
            '<p style="margin-top:24px;">🌿 El equipo de Mundo Verde</p>' .
            '</div>';
        $mail->AltBody =
            "¡Hola, $nombre!\n\n" .
            "Gracias por crear tu cuenta en Mundo Verde. Ya podés iniciar sesión " .
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
 * Confirmación de suscripción al newsletter, con su código de referido.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailNewsletter(string $email, string $nombre, string $codigo): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $c = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

        $mail->Subject = '¡Ya estás suscripto/a al newsletter de Mundo Verde! 🌿';
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;">' .
            '<h2 style="color:#2e7d32;">¡Hola, ' . $n . '!</h2>' .
            '<p>Gracias por suscribirte al newsletter de <strong>Mundo Verde</strong>. ' .
            'Vas a recibir novedades, consejos de cuidado de plantas y promociones.</p>' .
            '<p>Este es tu código personal de referido. Compartilo con tus amigos: ' .
            'cada vez que alguien se suscriba con tu código, sumás un referido.</p>' .
            '<p style="text-align:center;font-size:28px;letter-spacing:4px;font-weight:bold;' .
            'color:#2e7d32;background:#f1f8e9;padding:14px;border-radius:8px;">' . $c . '</p>' .
            '<p style="font-size:13px;color:#666;background:#fafafa;padding:10px;border-radius:6px;">' .
            '💡 <strong>Tip:</strong> agregá <em>viveunmundoverde@gmail.com</em> a tus contactos ' .
            'y, si este mail llegó a Spam o Promociones, marcalo como "No es spam" ' .
            'para no perderte nuestras novedades.</p>' .
            '<p style="margin-top:24px;">🌱 El equipo de Mundo Verde</p></div>';
        $mail->AltBody =
            "¡Hola, $nombre!\n\nGracias por suscribirte al newsletter de Mundo Verde.\n\n" .
            "Tu código personal de referido es: $codigo\n" .
            "Compartilo: cada persona que se suscriba con tu código suma un referido.\n\n" .
            "Tip: agregá viveunmundoverde@gmail.com a tus contactos y, si este mail llegó a Spam " .
            "o Promociones, marcalo como \"No es spam\" para no perderte nuestras novedades.\n\n" .
            "El equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando newsletter a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}
