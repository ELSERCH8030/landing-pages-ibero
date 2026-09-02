<?php
/**
 * Conector landing → Prospecty (HighLevel / LeadConnector).
 *
 * Recibe el JSON del formulario, crea o actualiza el contacto en el CRM
 * (upsert por teléfono/correo), le agrega etiquetas, una nota con los datos
 * del alumno y, opcionalmente, lo mete a un workflow.
 *
 * El token NUNCA va en este repositorio (es público). Se lee, en este orden:
 *   1. Archivo fuera de la carpeta pública:  <carpeta padre del docroot>/prospecty-config.php
 *   2. /etc/prospecty-config.php
 *   3. Variables de entorno GHL_TOKEN, GHL_LOCATION_ID, GHL_ALLOWED_ORIGINS, GHL_WORKFLOW_ID
 * Ver prospecty-config.example.php para el formato.
 *
 * No requiere la extensión curl: usa file_get_contents con allow_url_fopen.
 */

const API = 'https://services.leadconnectorhq.com';
const VERSION = '2021-07-28';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Configuración ──────────────────────────────────────────────────────────
function cargarConfig(): array {
    $rutas = [
        dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/prospecty-config.php',
        '/etc/prospecty-config.php',
    ];
    foreach ($rutas as $f) {
        if (is_readable($f)) { $c = include $f; if (is_array($c)) return $c; }
    }
    return [
        'token'           => getenv('GHL_TOKEN') ?: '',
        'location_id'     => getenv('GHL_LOCATION_ID') ?: '',
        'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('GHL_ALLOWED_ORIGINS') ?: ''))),
        'workflow_id'     => getenv('GHL_WORKFLOW_ID') ?: '',
    ];
}
$cfg = cargarConfig();

// ── CORS (solo hace falta si la landing vive en otro dominio) ──────────────
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
$permitidos = $cfg['allowed_origins'] ?? [];
if ($origen && (in_array('*', $permitidos, true) || in_array($origen, $permitidos, true))) {
    header('Access-Control-Allow-Origin: ' . (in_array('*', $permitidos, true) ? '*' : $origen));
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responder(405, ['ok' => false, 'error' => 'Método no permitido']); }

if (empty($cfg['token']) || empty($cfg['location_id'])) {
    responder(500, ['ok' => false, 'error' => 'Falta configurar token o location_id en el servidor']);
}

// ── Entrada ────────────────────────────────────────────────────────────────
$b = json_decode(file_get_contents('php://input'), true);
if (!is_array($b)) $b = $_POST;

$nombre   = trim((string)($b['nombre'] ?? ''));
$telefono = normalizarTelefono($b['telefono'] ?? '');
$email    = strtolower(trim((string)($b['email'] ?? '')));
if ($nombre === '')            responder(400, ['ok' => false, 'error' => 'Falta el nombre']);
if ($telefono === '' && $email === '') responder(400, ['ok' => false, 'error' => 'Falta teléfono o correo']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

[$firstName, $lastName] = partirNombre($nombre);
$tags = array_slice(array_map('strval', (array)($b['tags'] ?? [])), 0, 15);

// ── 1) Crear o actualizar contacto ─────────────────────────────────────────
$contacto = array_filter([
    'locationId' => $cfg['location_id'],
    'firstName'  => $firstName,
    'lastName'   => $lastName,
    'phone'      => $telefono ?: null,
    'email'      => $email ?: null,
    'source'     => (string)($b['fuente'] ?? 'Landing'),
    'tags'       => $tags,
], fn($v) => $v !== null && $v !== '');

try {
    $up = ghl('POST', '/contacts/upsert', $contacto, $cfg['token']);
    $id = $up['contact']['id'] ?? null;
    if (!$id) throw new RuntimeException('El CRM no devolvió el id del contacto');

    // ── 2) Nota con datos del alumno y del evento ──────────────────────────
    $utm = is_array($b['utm'] ?? null) ? $b['utm'] : [];
    $utmTxt = implode(' / ', array_filter([$utm['source'] ?? '', $utm['medium'] ?? '', $utm['campaign'] ?? '', $utm['content'] ?? '']));
    $fecha = (new DateTime('now', new DateTimeZone('America/Tijuana')))->format('d/m/Y H:i');
    $lineas = array_filter([
        'Registro desde: ' . ($b['fuente'] ?? 'Landing'),
        !empty($b['colegio']) ? 'Colegio: ' . $b['colegio'] : null,
        !empty($b['hijo'])    ? 'Alumno(a): ' . $b['hijo'] : null,
        !empty($b['grado'])   ? 'Grado: ' . $b['grado'] : null,
        !empty($b['carrera']) ? 'Licenciatura de interés: ' . $b['carrera'] : null,
        $utmTxt !== '' ? 'UTM: ' . $utmTxt : null,
        !empty($b['pagina'])  ? 'Página: ' . $b['pagina'] : null,
        'Fecha: ' . $fecha,
    ]);
    try { ghl('POST', "/contacts/$id/notes", ['body' => implode("\n", $lineas)], $cfg['token']); }
    catch (Throwable $e) { error_log('Prospecty nota no guardada: ' . $e->getMessage()); }

    // ── 3) Workflow opcional ───────────────────────────────────────────────
    if (!empty($cfg['workflow_id'])) {
        try { ghl('POST', "/contacts/$id/workflow/{$cfg['workflow_id']}", new stdClass(), $cfg['token']); }
        catch (Throwable $e) { error_log('Prospecty workflow no aplicado: ' . $e->getMessage()); }
    }

    responder(200, ['ok' => true, 'id' => $id, 'nuevo' => !empty($up['new'])]);
} catch (Throwable $e) {
    error_log('Prospecty error: ' . $e->getMessage());
    responder(502, ['ok' => false, 'error' => 'No se pudo guardar en el CRM']);
}

// ── Utilidades ─────────────────────────────────────────────────────────────
function ghl(string $metodo, string $ruta, $cuerpo, string $token): array {
    $ctx = stream_context_create(['http' => [
        'method'        => $metodo,
        'header'        => "Authorization: Bearer $token\r\nVersion: " . VERSION . "\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
        'content'       => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        'timeout'       => 15,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents(API . $ruta, false, $ctx);
    if ($raw === false) throw new RuntimeException("Sin respuesta de HighLevel en $ruta");
    $status = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1]; }
    $data = json_decode($raw, true) ?: [];
    if ($status < 200 || $status >= 300) {
        $msg = $data['message'] ?? "HighLevel $status";
        throw new RuntimeException(is_array($msg) ? implode('; ', $msg) : (string)$msg);
    }
    return $data;
}

function normalizarTelefono($raw): string {
    $d = preg_replace('/\D/', '', (string)$raw);
    if (strlen($d) === 10) return '+52' . $d;                        // México
    if (strlen($d) === 11 && $d[0] === '1') return '+' . $d;          // EE. UU.
    if (strlen($d) === 12 && str_starts_with($d, '52')) return '+' . $d;
    if (strlen($d) === 13 && str_starts_with($d, '521')) return '+52' . substr($d, 3);
    return $d !== '' ? '+' . $d : '';
}

function partirNombre(string $nombre): array {
    $p = preg_split('/\s+/', trim($nombre));
    if (count($p) <= 1) return [$p[0] ?? '', ''];
    return [$p[0], implode(' ', array_slice($p, 1))];
}

function responder(int $status, array $json): void {
    http_response_code($status);
    echo json_encode($json, JSON_UNESCAPED_UNICODE);
    exit;
}
