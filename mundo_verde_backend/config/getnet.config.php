<?php
/**
 * PLANTILLA de config/getnet.config.php (copiala con ese nombre y completala).
 * El archivo real NO se sube a GitHub (agregalo al .gitignore, igual que
 * config_mail.php) y en Hostinger se crea a mano.
 *
 * Credenciales: portal de comercios de Getnet → Productos digitales → Integraciones.
 * Se generan por separado para preproducción (sandbox) y producción.
 */
return [
    // Solo aplica en el servidor: en tu PC (localhost) siempre se usa 'sandbox'.
    'entorno' => 'sandbox', // 'sandbox' | 'produccion'

    'sandbox' => [
        'client_id'     => 'cid_3a18944d-661c-4c4f-bc72-05b83a8b9a7',
        'client_secret' => 'c28dc006-03c4-4a68-bf32-41a314b5c2ac ',
        'auth_url'      => 'https://auth.geopagos.com/oauth/token', // TODO(doc): confirmar
        'api_base'      => 'https://TODO-sandbox.globalgetnet.com.ar', // TODO(doc): URL de preproducción
    ],
    'produccion' => [
        'client_id'     => 'CLIENT_ID_PRODUCCION',
        'client_secret' => 'CLIENT_SECRET_PRODUCCION',
        'auth_url'      => 'https://auth.geopagos.com/oauth/token', // TODO(doc): confirmar
        'api_base'      => 'https://api.globalgetnet.com.ar',       // TODO(doc): confirmar
    ],

    'return_url'  => 'https://vivemundoverde.com/pago_resultado.html',
    'webhook_url' => 'https://vivemundoverde.com/mundo_verde_backend/api/pagos/webhook',

    // Texto largo y aleatorio (ej.: bin2hex(random_bytes(24))). Viaja como ?k=... en la URL del webhook.
    'webhook_token' => 'Qwertys23324sd6sdjsdfs63ksd',
];
