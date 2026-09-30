<?php
/**
 * Credenciales de correo. NO subir a GitHub ni compartir.
 * Si podés, guardá este archivo FUERA de public_html y ajustá la ruta en mailer.php.
 *
 * La contraseña es una "contraseña de aplicación" de Google
 * (Cuenta de Google → Seguridad → Verificación en 2 pasos → Contraseñas de aplicaciones),
 * NO la contraseña normal de Gmail.
 */
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 465,                 // 465 = SSL, 587 = STARTTLS
    'usuario'    => 'viveunmundoverde@gmail.com',
    'password'   => 'uagqpswqjfsdgyat', // <--- PONÉ TU CONTRASEÑA DE APLICACIÓN DE GMAIL AQUÍ
    'from_email' => 'viveunmundoverde@gmail.com',
    'from_name'  => 'Mundo Verde',
];
