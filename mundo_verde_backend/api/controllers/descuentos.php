<?php
/**
 * Configuración de descuentos (fuente única de verdad).
 * Lee la tabla `configuracion` (clave/valor):
 *   inauguracion_activa  '1' / '0'
 *   inauguracion_pct     porcentaje (ej. 10)
 *   inauguracion_hasta   fecha y hora de vencimiento, hora de Argentina ('2026-10-31 23:59:59')
 *   newsletter_pct       porcentaje del Código Verde (ej. 10)
 * El descuento de inauguración SOLO está activo si el flag es '1', hay una
 * fecha de vencimiento cargada y todavía no pasó. Sin fecha = no se aplica.
 * Lo usan pedidos_crear() (para cobrar) y GET /descuentos (para que el front
 * muestre los montos y la cuenta regresiva con la MISMA fecha).
 */

function descuentos_config(PDO $pdo): array
{
    $tz    = new DateTimeZone('America/Argentina/Buenos_Aires');
    $ahora = new DateTime('now', $tz);

    $valores = [];
    try {
        foreach ($pdo->query('SELECT clave, valor FROM configuracion') as $fila) {
            $valores[$fila['clave']] = $fila['valor'];
        }
    } catch (Throwable $e) {
        // Tabla inexistente (migración sin correr): queda todo en valores por defecto.
        error_log('[descuentos_config] ' . $e->getMessage());
    }

    $hasta = null;
    $raw = trim((string)($valores['inauguracion_hasta'] ?? ''));
    if ($raw !== '') {
        try { $hasta = new DateTime($raw, $tz); } catch (Throwable $e) { $hasta = null; }
    }

    $flag = (string)($valores['inauguracion_activa'] ?? '0') === '1';
    $pctI = max(0.0, min(100.0, (float)($valores['inauguracion_pct'] ?? 10)));
    $pctN = max(0.0, min(100.0, (float)($valores['newsletter_pct'] ?? 10)));

    return [
        'inauguracion_activa' => $flag && $hasta !== null && $ahora <= $hasta,
        'inauguracion_pct'    => $pctI,
        'inauguracion_hasta'  => $hasta ? $hasta->format('c') : null,   // ISO con offset (-03:00)
        'newsletter_pct'      => $pctN,
        'ahora'               => $ahora->format('c'),                   // hora del servidor, para la cuenta regresiva
    ];
}

/** GET /descuentos — público. */
function descuentos_listar(PDO $pdo): void
{
    $c = descuentos_config($pdo);
    header('Cache-Control: no-store');
    responder([
        'inauguracion' => [
            'activa'     => $c['inauguracion_activa'],
            'porcentaje' => $c['inauguracion_pct'],
            'hasta'      => $c['inauguracion_hasta'],
        ],
        'newsletter' => ['porcentaje' => $c['newsletter_pct']],
        'ahora'      => $c['ahora'],
    ]);
}
