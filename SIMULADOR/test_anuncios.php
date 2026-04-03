<?php
/**
 * Test de Anuncios — Simula tráfico de 4 tipos de anuncio.
 *
 * Responde: ¿Qué tipo de anuncio convierte más ANTES de gastar dinero?
 *
 * Uso:
 *   php SIMULADOR/test_anuncios.php
 *   php SIMULADOR/test_anuncios.php --cantidad=50
 *   php SIMULADOR/test_anuncios.php --optimizar    # optimiza el mejor
 */

require_once __DIR__ . '/core/Simulador.php';

$opts = getopt('', ['cantidad:', 'optimizar']);
$cantidad = intval($opts['cantidad'] ?? 30);
$optimizarMejor = isset($opts['optimizar']);

$anuncios = [
    'anuncio_precio'   => 'Anuncio: PRECIO ("desde $25,000")',
    'anuncio_problema'  => 'Anuncio: PROBLEMA ("PC lento?")',
    'anuncio_social'    => 'Anuncio: PRUEBA SOCIAL ("500+ reparados")',
    'anuncio_urgencia'  => 'Anuncio: URGENCIA ("últimos turnos")',
];

echo "\n";
echo "================================================================\n";
echo "  TEST DE ANUNCIOS — SSolutions\n";
echo "  " . date('Y-m-d H:i:s') . " | $cantidad simulaciones por tipo\n";
echo "================================================================\n\n";

$resultados = [];
$inicio = microtime(true);

foreach ($anuncios as $escenario => $descripcion) {
    echo "Simulando: $descripcion...\n";

    $sim = new Simulador('interno');
    $sim->ejecutar($escenario, $cantidad);
    $reporte = $sim->reporte();

    $r = $reporte['resultados'];
    $m = $reporte['metricas'];

    $cal = $reporte['calidad'] ?? [];
    $dinero = $reporte['dinero_perdido'] ?? [];

    $resultados[$escenario] = [
        'nombre' => $descripcion,
        'conversion' => $r['conversion'],
        'abandono' => $r['abandono'],
        'interesados' => $r['interesados_sin_cerrar'],
        'mensajes_promedio' => $m['mensajes_promedio'],
        'errores' => $r['errores'],
        'ad_match_score' => $cal['ad_promise_match_score'] ?? 1.0,
        'ventas_perdidas' => $dinero['ventas_perdidas_total'] ?? 0,
        'razones_perdida' => $dinero['razones'] ?? [],
        'score' => round(($r['conversion'] * 0.6) - ($r['abandono'] * 0.3) - ($m['mensajes_promedio'] * 0.01), 4),
    ];

    $matchEmoji = ($cal['ad_promise_match_score'] ?? 1) >= 0.9 ? 'OK' : 'MISMATCH';
    echo "  Conv: " . round($r['conversion'] * 100) . "% | "
        . "Abandono: " . round($r['abandono'] * 100) . "% | "
        . "Msgs: {$m['mensajes_promedio']} | "
        . "AdMatch: " . ($cal['ad_promise_match_score'] ?? '1.0') . " [$matchEmoji] | "
        . "Perdidas: " . ($dinero['ventas_perdidas_total'] ?? 0) . "\n\n";
}

$duracion = round(microtime(true) - $inicio, 2);

// Ranking
echo "================================================================\n";
echo "  RANKING DE ANUNCIOS (por score)\n";
echo "================================================================\n\n";

usort($resultados, fn($a, $b) => $b['score'] <=> $a['score']);
$mejorEscenario = null;

foreach ($resultados as $i => $r) {
    $pos = $i + 1;
    $medal = ['', '  <<<'][min($i, 1)] ?? '';
    if ($i === 0) {
        $medal = '  <<< GANADOR';
        $mejorEscenario = array_search($r, $resultados) ?: '';
        // Buscar el escenario key
        foreach ($anuncios as $key => $desc) {
            if ($desc === $r['nombre']) { $mejorEscenario = $key; break; }
        }
    }

    echo "  #{$pos} {$r['nombre']}\n";
    echo "     Score: {$r['score']} | Conv: " . round($r['conversion'] * 100) . "% | "
        . "Abandono: " . round($r['abandono'] * 100) . "% | "
        . "Msgs: {$r['mensajes_promedio']}{$medal}\n\n";
}

// Recomendaciones
echo "================================================================\n";
echo "  RECOMENDACIONES\n";
echo "================================================================\n\n";

$mejor = $resultados[0];
$peor = end($resultados);

echo "  INVERTIR EN: {$mejor['nombre']}\n";
echo "  -> Conversion: " . round($mejor['conversion'] * 100) . "% | Score: {$mejor['score']}\n\n";

echo "  EVITAR: {$peor['nombre']}\n";
echo "  -> Conversion: " . round($peor['conversion'] * 100) . "% | Score: {$peor['score']}\n\n";

if ($mejor['conversion'] > 0 && $peor['conversion'] > 0) {
    $diff = round((($mejor['conversion'] - $peor['conversion']) / $peor['conversion']) * 100);
    echo "  Diferencia: el mejor convierte {$diff}% mas que el peor\n\n";
}

// Guardar reporte
$reporte = [
    'timestamp' => date('Y-m-d H:i:s'),
    'simulaciones_por_tipo' => $cantidad,
    'duracion_segundos' => $duracion,
    'ranking' => $resultados,
    'mejor' => $mejor,
    'peor' => $peor,
    'recomendacion' => "Invertir en '{$mejor['nombre']}' — {$diff}% mas conversion que el peor"
];

$path = __DIR__ . '/resultados/test_anuncios.json';
file_put_contents($path, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  Reporte: $path\n";
echo "  Duracion: {$duracion}s\n";

// Si pidió optimizar el mejor
if ($optimizarMejor && $mejorEscenario) {
    echo "\n================================================================\n";
    echo "  OPTIMIZANDO MEJOR ANUNCIO: {$mejor['nombre']}\n";
    echo "================================================================\n\n";

    require_once __DIR__ . '/optimizacion/OptimizadorPrompt.php';
    $opt = new OptimizadorPrompt($cantidad);
    $resultOpt = $opt->optimizar($mejorEscenario, 3);

    foreach ($opt->getLogs() as $line) {
        echo "  $line\n";
    }

    echo "\n  Conversion: " . round($resultOpt['conversion_inicial'] * 100) . "% → "
        . round($resultOpt['conversion_final'] * 100) . "%\n";
    if ($resultOpt['mejora_conversion_pct'] > 0) {
        echo "  Mejora: +{$resultOpt['mejora_conversion_pct']}%\n";
    }

    $opt->guardarLogs();
}

echo "\n================================================================\n";
echo "  TEST COMPLETADO\n";
echo "================================================================\n\n";
