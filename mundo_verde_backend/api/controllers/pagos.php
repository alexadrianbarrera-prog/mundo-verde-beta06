<?php
/**
 * pagos.php — REFERENCIA para el endpoint POST /pagos/tarjeta
 * ============================================================
 * Este archivo es un ESQUELETO, no un drop-in: adaptalo a la
 * estructura real de mundo_verde_backend (conexión a MySQL,
 * router de la API, manejo de auth, etc). La idea es mostrar
 * exactamente qué tiene que hacer el endpoint, no reemplazar
 * tus archivos.
 *
 * Qué hace:
 * 1) Recibe el token que generó el Card Payment Brick en el
 *    navegador del cliente (script.js → procesarPagoConTarjeta
 *    → api.js → mvPagos.pagarConTarjeta → acá).
 * 2) Llama a la API de Pagos de Mercado Pago del lado del
 *    servidor, usando el Access Token PRIVADO (nunca el público,
 *    y nunca expuesto al frontend).
 * 3) Devuelve el resultado (approved / in_process / rejected)
 *    al frontend, que decide si avanza al paso 4 o no.
 *
 * IMPORTANTE: acá es donde de verdad se cobra. El "token" que
 * llega en $input ya no es el número de tarjeta — es un ID de
 * un solo uso que generó el SDK de MP en el navegador.
 */

// ── Configuración ──────────────────────────────────────────
// Nunca hardcodear esto en el repo: usar variables de entorno
// o un archivo de config fuera del control de versiones.
const MP_ACCESS_TOKEN = getenv('MP_ACCESS_TOKEN') ?: 'TU-ACCESS-TOKEN-PRIVADO-AQUI';

header('Content-Type: application/json');

// ── Autenticación (igual que el resto de tu API — adaptar) ──
// Ejemplo simplificado; usá tu misma lógica de verificación de
// "Authorization: Bearer <token>" que ya tenés en los demás
// endpoints (pedidos, usuario/me, etc).
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
// $usuario = validarTokenMv($authHeader); // ← tu función existente

// ── Leer el body ──────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['token']) || empty($input['transaction_amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos del pago (token o monto).']);
    exit;
}

// ── Armar el payload para la API de Pagos de Mercado Pago ──
// Referencia oficial:
// https://www.mercadopago.com.ar/developers/es/reference/payments/_payments/post
$payload = [
    'transaction_amount'   => (float) $input['transaction_amount'],
    'token'                => $input['token'],
    'description'          => 'Pedido Mundo Verde',
    'installments'         => (int) ($input['installments'] ?? 1),
    'payment_method_id'    => $input['payment_method_id'] ?? null,
    'issuer_id'             => $input['issuer_id'] ?? null,
    'payer'                => [
        'email'          => $input['payer']['email'] ?? '',
        'identification' => [
            'type'   => $input['payer']['identification']['type'] ?? 'DNI',
            'number' => $input['payer']['identification']['number'] ?? '',
        ],
    ],
];

// ── Llamada a Mercado Pago ──────────────────────────────────
$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        // Clave de idempotencia: evita cobrar dos veces si el
        // navegador reintenta la misma petición.
        'X-Idempotency-Key: ' . bin2hex(random_bytes(16)),
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);
$respuesta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($respuesta, true);

if ($httpCode >= 400 || !$data) {
    http_response_code(502);
    echo json_encode(['error' => $data['message'] ?? 'No se pudo procesar el pago con Mercado Pago.']);
    exit;
}

// ── Guardar referencia del pago en tu base de datos ─────────
// Acá conviene guardar $data['id'], $data['status'] y el email
// del comprador en una tabla de pagos/pedidos, igual que ya
// hacés en el endpoint /pedidos con el comprobante de
// transferencia. El pedido en sí (items, entrega, etc.) lo sigue
// mandando el frontend por separado a POST /pedidos, incluyendo
// este payment_id en el campo "pago_mp" (ver script.js).
//
// guardarPagoEnBD($data['id'], $data['status'], $usuario);

// ── Responder al frontend ───────────────────────────────────
echo json_encode([
    'payment_id' => $data['id'],
    'status'     => $data['status'], // approved | in_process | rejected
]);
