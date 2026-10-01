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
 * Saludo de bienvenida al usuario que acaba de crear su cuenta.
 * Se envía al mail que dejó en el registro.
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
 * Saludo al suscribirse al newsletter, con su código de referido.
 * Se envía al mail que dejó en la suscripción.
 * Nunca lanza excepciones: devuelve true/false y registra el error en el log.
 */
function enviarMailNewsletter(string $email, string $nombre, string $codigo): bool
{
    try {
        $mail = crearMailer();
        $mail->addAddress($email, $nombre);
        $n = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $c = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

        $mail->Subject = '¡Gracias por sumarte a la comunidad de Mundo Verde! 🌱';
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#333;text-align:center;">' .
            '<h2 style="color:#2e7d32;">¡Bienvenido/a a Mundo Verde!</h2>' .
            '<p>¡Hola, ' . $n . '!</p>' .
            '<p>Gracias por formar parte de nuestra comunidad. Te alcanzamos tu código de referido ' .
            'para que refieras a nuevos clientes y obtengas importantes descuentos y vouchers.</p>' .
            '<p style="margin:24px 0;">' .
            '<span style="display:inline-block;background:#2e7d32;color:#fff;font-size:24px;' .
            'font-weight:bold;letter-spacing:4px;padding:12px 28px;border-radius:8px;">' . $c . '</span></p>' .
            '<p style="font-size:13px;color:#666;">Compartilo con tus amigos y ganá descuentos.</p>' .
            '<p>Vas a recibir novedades, promos y talleres exclusivos en tu email.</p>' .
            '<p style="font-size:12px;color:#777;background:#fafafa;padding:10px;border-radius:6px;">' .
            '💡 Agregá <em>info@vivemundoverde.com</em> a tus contactos y, si este mail llegó a ' .
            'Spam o Promociones, marcalo como "No es spam" para no perderte nuestras novedades.</p>' .
            '<p style="margin-top:24px;">🌿 El equipo de Mundo Verde</p>' .
            '</div>';
        $mail->AltBody =
            "¡Hola, $nombre!\n\n" .
            "Gracias por formar parte de nuestra comunidad. Te alcanzamos tu código de referido " .
            "para que refieras a nuevos clientes y obtengas importantes descuentos y vouchers.\n\n" .
            "Tu código: $codigo\n\n" .
            "Vas a recibir novedades, promos y talleres exclusivos en tu email.\n\n" .
            "Tip: agregá info@vivemundoverde.com a tus contactos y, si este mail llegó a Spam " .
            "o Promociones, marcalo como \"No es spam\".\n\n" .
            "El equipo de Mundo Verde";

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[mail] Error enviando newsletter a ' . $email . ': ' . $e->getMessage());
        return false;
    }
}
