<?php
/**
 * cron_pagos.php — OPCIONAL. Cancela los pedidos de tarjeta que quedaron
 * pendientes (el cliente abandonó Getnet) y libera su Código Verde.
 *
 * Aunque no lo programes, esto se hace solo cada vez que entra un pedido nuevo
 * y cuando alguien pide/valida un Código Verde retenido; el cron solo mantiene
 * prolijo el listado del admin.
 *
 * Solo corre por consola (cron de Hostinger, cada 15-30 min):
 *   php /home/USUARIO/public_html/mundo_verde_backend/api/cron_pagos.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// En la consola no hay SERVER_NAME: con MV_LOCAL=1 se usa la base local (para probar en tu PC).
if (getenv('MV_LOCAL')) $_SERVER['SERVER_NAME'] = 'localhost';

require_once __DIR__ . '/../config/conexion.php'; // define $pdo
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/controllers/pagos.php';

$n = pagos_cancelar_vencidos($pdo);
echo date('c') . " — pedidos de tarjeta cerrados: $n\n";
