<?php
/**
 * Envío de mails con PHPMailer por SMTP (Gmail).
 * Las credenciales viven en config_mail.php, NUNCA en este archivo.
 *
 * Requiere PHPMailer en:  controllers/phpmailer/src/{Exception,PHPMailer,SMTP}.php
 * (descargar de https://github.com/PHPMailer/PHPMailer → carpeta "src")
 */

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Manda el mail de bienvenida a un usuario recién registrado.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailBienvenida(string $email, string $nombre): bool
{
    try {
        $base = __DIR__ . '/phpmailer/src/';
        $cfgPath = __DIR__ . '/config_mail.php';
        if (!file_exists($base . 'PHPMailer.php') || !file_exists($cfgPath)) {
            error_log('[mail] Falta PHPMailer o config_mail.php: no se envió la bienvenida.');
            return false;
        }
        require_once $base . 'Exception.php';
        require_once $base . 'PHPMailer.php';
        require_once $base . 'SMTP.php';
        $cfg = require $cfgPath;

        $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

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
        $mail->addAddress($email, $nombre);

        $mail->isHTML(true);
        $mail->Subject = '¡Bienvenido/a a Mundo Verde! 🌱';
        $mail->Body    =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;">' .
            '<h2 style="color:#2e7d32;">¡Hola, ' . $nombreSeguro . '!</h2>' .
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
