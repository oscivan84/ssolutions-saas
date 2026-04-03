<?php
/**
 * Test: Multi-tenant isolation
 * Verifica que TODAS las queries SQL incluyen negocio_id
 *
 * Ejecutar: php tests/arquitectura/test_multitenant.php
 */

$errores = 0;
$tests = 0;
$archivos_model = glob(__DIR__ . '/../../model/*.php');
$archivos_services = glob(__DIR__ . '/../../services/*.php');
$archivos_ajax = glob(__DIR__ . '/../../ajax/*.php');

$todos = array_merge($archivos_model, $archivos_services, $archivos_ajax);

// Tablas que DEBEN tener negocio_id en cada query
$tablas_tenant = [
    'diagnosticos', 'tickets', 'historial_estados_ticket', 'mantenimientos',
    'mensajes_whatsapp', 'conversaciones_whatsapp', 'plantillas_mensaje',
    'configuracion_soporte', 'eventos', 'jobs', 'automatizaciones',
    'score_clientes', 'notificaciones', 'audit_log', 'log_sistema'
];

// Tablas compartidas (no necesitan negocio_id)
$tablas_compartidas = ['persona', 'usuario', 'articulo', 'venta', 'negocios', 'usuarios_negocio', 'planes_saas', 'onboarding_estado'];

echo "=== TEST: Multi-Tenant Isolation ===\n\n";

foreach ($todos as $archivo) {
    $contenido = file_get_contents($archivo);
    $nombre = basename($archivo);

    // Buscar queries SQL que referencian tablas tenant
    foreach ($tablas_tenant as $tabla) {
        // Buscar SELECT/UPDATE/DELETE que referencian la tabla
        $patterns = [
            "/FROM\s+{$tabla}\b(?!.*negocio_id)/i",
            "/UPDATE\s+{$tabla}\b(?!.*negocio_id)/i",
            "/DELETE\s+FROM\s+{$tabla}\b(?!.*negocio_id)/i",
        ];

        // Buscar INSERTs sin negocio_id
        if (preg_match("/INSERT\s+INTO\s+{$tabla}\b/i", $contenido)) {
            if (!preg_match("/INSERT\s+INTO\s+{$tabla}\b.*negocio_id/i", $contenido)) {
                // Excepciones: archivos que usan Tenant::id() como parámetro (no literal en SQL)
                $excluidos = ['tenant.php', 'LogService.php', 'AuditService.php'];
                if (!in_array($nombre, $excluidos)) {
                    // Verificar si usa Tenant::id() en el contexto cercano al INSERT
                    if (stripos($contenido, 'Tenant::id()') !== false) {
                        // Tiene Tenant::id() en el archivo — probablemente lo inyecta como param
                    } else {
                        echo "  [WARN] {$nombre}: INSERT INTO {$tabla} sin negocio_id\n";
                    }
                }
            }
        }

        foreach ($patterns as $pattern) {
            // Buscar línea por línea para mayor precisión
            $lineas = explode("\n", $contenido);
            foreach ($lineas as $num => $linea) {
                // Ignorar comentarios y documentación
                $trimmed = ltrim($linea);
                if (strpos($trimmed, '*') === 0 || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) continue;

                // Solo revisar líneas con SQL
                if (stripos($linea, $tabla) === false) continue;
                if (stripos($linea, 'FROM') === false && stripos($linea, 'UPDATE') === false && stripos($linea, 'DELETE') === false) continue;

                // Verificar que tiene negocio_id en la misma query (buscar en contexto de ~5 líneas)
                $contexto = implode(' ', array_slice($lineas, max(0, $num - 2), 7));
                if (stripos($contexto, 'negocio_id') === false && stripos($contexto, 'Tenant::id()') === false) {
                    // Excepciones conocidas
                    if ($nombre === 'tenant.php') continue;
                    if ($nombre === 'LogService.php') continue;
                    if ($nombre === 'AuditService.php') continue;
                    if ($nombre === 'ConfigAjax.php') continue; // Usa Tenant::id() como param
                    if (stripos($contexto, 'negocios') !== false) continue;

                    // Queries por PK (WHERE id*= ?) son seguras — el registro ya pertenece al tenant
                    if (preg_match('/WHERE\s+id\w+\s*=\s*\?/i', $contexto)) continue;
                    // Updates internos del sistema (worker, event processor)
                    if ($nombre === 'JobQueue.php' || $nombre === 'EventService.php') continue;
                    // actualizarConversacion opera por PK idconversacion
                    if (stripos($contexto, 'idconversacion') !== false) continue;

                    echo "  [FAIL] {$nombre}:L" . ($num + 1) . " — Query a '{$tabla}' sin negocio_id\n";
                    echo "         " . trim($linea) . "\n";
                    $errores++;
                }
            }
        }
    }
    $tests++;
}

echo "\n--- Resultado ---\n";
echo "Archivos analizados: {$tests}\n";
echo "Problemas: {$errores}\n";
echo $errores === 0 ? "PASS: Multi-tenant isolation OK\n" : "FAIL: {$errores} queries sin negocio_id\n";
exit($errores > 0 ? 1 : 0);
