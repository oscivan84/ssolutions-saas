<?php
/**
 * SSolutions SIMULADOR — Script ejecutable
 *
 * Uso:
 *   php SIMULADOR/run.php                                    # Todos los escenarios x5
 *   php SIMULADOR/run.php --escenario=interesado             # Solo interesado x10
 *   php SIMULADOR/run.php --escenario=interesado --cantidad=50
 *   php SIMULADOR/run.php --todos --cantidad=20
 *   php SIMULADOR/run.php --modo=real                        # Usa webhook real (cuidado)
 *   php SIMULADOR/run.php --listar                           # Lista escenarios
 */

require_once __DIR__ . '/core/Simulador.php';

// ============================================================
// PARSEAR ARGUMENTOS CLI
// ============================================================
$opts = getopt('', ['escenario:', 'cantidad:', 'modo:', 'todos', 'listar', 'help', 'optimizar', 'iteraciones:']);

if (isset($opts['help'])) {
    echo <<<HELP
SSolutions SIMULADOR — Simulador de clientes WhatsApp

Uso:
  php SIMULADOR/run.php [opciones]

Opciones:
  --escenario=NOMBRE    Ejecutar escenario específico (interesado, indeciso, curioso, agresivo, silencioso)
  --cantidad=N          Número de simulaciones por escenario (default: 10)
  --modo=interno|real   Modo de ejecución (default: interno)
  --todos               Ejecutar TODOS los escenarios
  --optimizar           Optimizar prompt automáticamente (detectar + mejorar + guardar)
  --iteraciones=N       Número de rondas de optimización (default: 3)
  --listar              Listar escenarios disponibles
  --help                Mostrar esta ayuda

Ejemplos:
  php SIMULADOR/run.php --escenario=interesado --cantidad=50
  php SIMULADOR/run.php --todos --cantidad=20
  php SIMULADOR/run.php --optimizar --escenario=interesado --iteraciones=5
  php SIMULADOR/run.php --listar

HELP;
    exit(0);
}

// Listar escenarios
if (isset($opts['listar'])) {
    require_once __DIR__ . '/core/Escenario.php';
    echo "\nEscenarios disponibles:\n";
    foreach (Escenario::listar() as $nombre) {
        $esc = Escenario::cargar($nombre);
        echo "  - {$nombre}: {$esc->descripcion}\n";
    }
    echo "\n";
    exit(0);
}

$escenario = $opts['escenario'] ?? null;
$cantidad = intval($opts['cantidad'] ?? 10);
$modo = $opts['modo'] ?? 'interno';
$todos = isset($opts['todos']);
$optimizar = isset($opts['optimizar']);
$iteraciones = intval($opts['iteraciones'] ?? 3);

// ============================================================
// MODO OPTIMIZACION
// ============================================================
if ($optimizar) {
    require_once __DIR__ . '/optimizacion/OptimizadorPrompt.php';

    $escOpt = $escenario ?: 'interesado';
    $simsPorVariante = max($cantidad, 30); // Mínimo 30 para datos significativos

    echo "\n";
    echo "=========================================================\n";
    echo "  SSolutions OPTIMIZADOR DE PROMPTS v1.0\n";
    echo "  " . date('Y-m-d H:i:s') . "\n";
    echo "  Escenario: {$escOpt} | Iteraciones: {$iteraciones} | Sims: {$simsPorVariante}\n";
    echo "=========================================================\n\n";

    $inicio = microtime(true);
    $optimizador = new OptimizadorPrompt($simsPorVariante);
    $reporte = $optimizador->optimizar($escOpt, $iteraciones);
    $duracion = round(microtime(true) - $inicio, 2);

    // Mostrar logs
    foreach ($optimizador->getLogs() as $line) {
        echo "$line\n";
    }

    echo "\n=========================================================\n";
    echo "  RESULTADO FINAL\n";
    echo "=========================================================\n\n";

    echo "  Score:      {$reporte['score_inicial']} → {$reporte['score_final']}";
    if ($reporte['mejora_score_pct'] > 0) echo " (+{$reporte['mejora_score_pct']}%)";
    echo "\n";
    echo "  Conversión: " . round($reporte['conversion_inicial'] * 100) . "% → " . round($reporte['conversion_final'] * 100) . "%";
    if ($reporte['mejora_conversion_pct'] > 0) echo " (+{$reporte['mejora_conversion_pct']}%)";
    echo "\n";
    echo "  Estrategias: " . implode(', ', $reporte['estrategias_aplicadas'] ?: ['ninguna']) . "\n";
    echo "  Versión guardada: " . ($reporte['version_guardada'] ?? 'N/A') . "\n";
    echo "  Duración: {$duracion}s\n";

    echo "\n  Reporte: SIMULADOR/resultados/optimizacion.json\n";
    $optimizador->guardarLogs();
    echo "  Logs: SIMULADOR/logs/optimizacion.log\n";

    echo "\n=========================================================\n";
    if ($reporte['mejora_score_pct'] > 0) {
        echo "  OPTIMIZACION EXITOSA — Prompt mejorado y guardado\n";
    } else {
        echo "  SIN MEJORA — Prompt actual es el mejor\n";
    }
    echo "=========================================================\n\n";

    exit(0);
}

if (!$escenario && !$todos) {
    $todos = true; // Por defecto ejecutar todos
    $cantidad = $cantidad ?: 5;
}

// ============================================================
// EJECUTAR SIMULACION
// ============================================================

echo "\n";
echo "=========================================================\n";
echo "  SSolutions SIMULADOR v1.0\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "  Modo: {$modo} | Cantidad: {$cantidad}\n";
echo "=========================================================\n\n";

$inicio = microtime(true);
$simulador = new Simulador($modo);

if ($todos) {
    echo "Ejecutando TODOS los escenarios ({$cantidad} cada uno)...\n\n";
    $simulador->ejecutarTodos($cantidad);
} else {
    echo "Ejecutando escenario: {$escenario} x{$cantidad}...\n\n";
    $simulador->ejecutar($escenario, $cantidad);
}

$duracionTotal = round(microtime(true) - $inicio, 2);

// ============================================================
// GENERAR REPORTE
// ============================================================

$reporte = $simulador->reporte();

echo "\n";
echo "=========================================================\n";
echo "  REPORTE DE SIMULACION\n";
echo "=========================================================\n\n";

echo "Simulaciones: {$reporte['simulaciones']}\n";
echo "Duración: {$duracionTotal}s\n\n";

echo "--- RESULTADOS ---\n";
$r = $reporte['resultados'];
echo "  Conversión:     " . ($r['conversion'] * 100) . "%\n";
echo "  Abandono:       " . ($r['abandono'] * 100) . "%\n";
echo "  Interesados:    " . ($r['interesados_sin_cerrar'] * 100) . "%\n";
echo "  Errores:        {$r['errores']}\n\n";

echo "--- METRICAS ---\n";
$m = $reporte['metricas'];
echo "  Mensajes promedio:  {$m['mensajes_promedio']}\n";
echo "  Duración promedio:  {$m['duracion_promedio_segundos']}s\n\n";

echo "--- ESTADOS FINALES ---\n";
foreach ($m['estados_finales'] as $estado => $count) {
    if ($count > 0) echo "  {$estado}: {$count}\n";
}

echo "\n--- OBSERVACIONES ---\n";
foreach ($reporte['observaciones'] as $obs) {
    $icon = ['critico' => '!!!', 'alto' => '!! ', 'medio' => '!  ', 'ok' => ' OK'][strtolower($obs['tipo'])] ?? '   ';
    echo "  [{$icon}] {$obs['mensaje']}\n";
    if (!empty($obs['accion'])) echo "        -> {$obs['accion']}\n";
}

// Guardar archivos
$pathReporte = $simulador->guardarReporte();
$pathLogs = $simulador->guardarLogs();

echo "\n--- ARCHIVOS ---\n";
echo "  Reporte: {$pathReporte}\n";
echo "  Logs:    {$pathLogs}\n";

echo "\n=========================================================\n";

// Exit code según resultado
$exitCode = 0;
if ($r['errores'] > 0) $exitCode = 1;
if ($r['conversion'] < 0.1 && $reporte['simulaciones'] >= 10) {
    echo "  ADVERTENCIA: Conversión menor al 10%\n";
    $exitCode = 1;
}

echo $exitCode === 0 ? "  SIMULACION COMPLETADA OK\n" : "  SIMULACION CON PROBLEMAS\n";
echo "=========================================================\n\n";

exit($exitCode);
