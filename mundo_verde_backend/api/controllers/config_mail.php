<?php
/**
 * Credenciales de correo. NO subir a GitHub ni compartir.
 * (ya está en .gitignore: subir este archivo al servidor a mano)
 *
 * Casilla de correo de Hostinger (hPanel → Correos electrónicos).
 * La contraseña es la que elegiste al crear la casilla info@vivemundoverde.com.
 */
return [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,                          // 465 = SSL, 587 = STARTTLS
    'usuario'    => 'info@vivemundoverde.com',    // el mail completo
    'password'   => 'f4C1@#31', // la contraseña de la casilla
    'from_email' => 'info@vivemundoverde.com',
    'from_name'  => 'Mundo Verde',
    'reply_to'   => 'viveunmundoverde@gmail.com', // las respuestas llegan acá
];
