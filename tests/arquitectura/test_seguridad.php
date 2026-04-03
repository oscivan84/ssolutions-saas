<?php
/**
 * Test: Seguridad — Auth checks, encryption, rate limit
 *
 * Ejecutar: php tests/arquitectura/test_seguridad.php
 */

$errores = 0;

echo "=== TEST: Seguridad ===\n\n";

// 1. Verificar que AJAX handlers de escritura tienen requireAuth
$ajax_escritura = [
    'ConfigAjax.php' => ['guardar', 'guardarMultiple'],
    'TicketAjax.php' => ['SaveOrUpdate', 'cambiarEstado'],
    'MantenimientoAjax.php' => ['SaveOrUpdate'],
];

foreach ($ajax_escritura as $archivo => $ops) {
    $contenido = file_get_contents(__DIR__ . "/../../ajax/{$archivo}");
    if (strpos($contenido, 'requireAuth') === false) {
        echo "  [FAIL] ajax/{$archivo} — Sin requireAuth() en operaciones de escritura\n";
        $errores++;
    } else {
        echo "  [PASS] ajax/{$archivo} — requireAuth() presente\n";
    }
}

// 2. Verificar encriptación funciona
require_once __DIR__ . '/../../config/security.php';

$original = 'sk-test-api-key-12345';
$encrypted = encriptar($original);
$decrypted = desencriptar($encrypted);

if ($decrypted === $original) {
    echo "  [PASS] Encriptación/desencriptación OK\n";
} else {
    echo "  [FAIL] Encriptación rota — original: {$original}, decrypted: {$decrypted}\n";
    $errores++;
}

// Verificar que encriptado != original
if ($encrypted !== $original) {
    echo "  [PASS] Valor encriptado es diferente al original\n";
} else {
    echo "  [FAIL] Valor no se encriptó\n";
    $errores++;
}

// 3. Verificar rate limit
$key = 'test_rate_' . time();
$passed = 0;
for ($i = 0; $i < 5; $i++) {
    if (checkRateLimit($key, 3, 10)) $passed++;
}
if ($passed === 3) {
    echo "  [PASS] Rate limit funciona (3/5 pasaron con limit=3)\n";
} else {
    echo "  [FAIL] Rate limit — esperaba 3 de 5, pasaron {$passed}\n";
    $errores++;
}

// 4. Verificar que API endpoints tienen rate limit
$apis_con_ratelimit = ['diagnostico.php', 'ticket_auto.php'];
foreach ($apis_con_ratelimit as $api) {
    $contenido = file_get_contents(__DIR__ . "/../../api/{$api}");
    if (strpos($contenido, 'checkRateLimit') !== false) {
        echo "  [PASS] api/{$api} — Rate limit implementado\n";
    } else {
        echo "  [WARN] api/{$api} — Sin rate limit\n";
    }
}

// 5. Verificar prepared statements (no queries directas con variables)
$todos_php = array_merge(
    glob(__DIR__ . '/../../model/*.php'),
    glob(__DIR__ . '/../../services/*.php')
);
foreach ($todos_php as $archivo) {
    $contenido = file_get_contents($archivo);
    $nombre = basename($archivo);
    // Buscar $conexion->query() con variables interpoladas
    if (preg_match('/\$conexion->query\s*\(\s*"[^"]*\$/', $contenido)) {
        echo "  [FAIL] {$nombre} — Query directa con variable interpolada (SQL injection risk)\n";
        $errores++;
    }
}

echo "\n--- Resultado ---\n";
echo $errores === 0 ? "PASS: Seguridad OK\n" : "FAIL: {$errores} problemas de seguridad\n";
exit($errores > 0 ? 1 : 0);
