<?php
/**
 * Evaluador — Calcula métricas de conversión, calidad y dinero perdido.
 *
 * Métricas:
 * - Conversión / abandono / errores (básico)
 * - AD_PROMISE_MATCH_SCORE (consistencia anuncio → bot)
 * - Ventas perdidas con razón exacta
 * - Respuestas genéricas del bot
 */

class Evaluador {

    private array $resultados = [];
    private array $observaciones = [];

    public function agregarResultado(array $resumenCliente, array $log = [], array $analisisCalidad = []) {
        $this->resultados[] = [
            'cliente' => $resumenCliente,
            'log' => $log,
            'calidad' => $analisisCalidad
        ];
    }

    /**
     * Generar reporte completo
     */
    public function generarReporte(): array {
        $total = count($this->resultados);
        if ($total === 0) return ['error' => 'Sin simulaciones'];

        $estados = ['aceptado' => 0, 'abandonado' => 0, 'interesado' => 0, 'conversando' => 0, 'error' => 0, 'inicio' => 0];
        $totalMensajes = 0;
        $totalDuracion = 0;
        $errores = 0;

        // Métricas de calidad
        $totalMismatches = 0;
        $totalVentasPerdidas = [];
        $totalRespuestasGenericas = 0;
        $matchScores = [];
        $razonesPerdida = [];

        foreach ($this->resultados as $r) {
            $c = $r['cliente'];
            $estado = $c['estado_final'];
            $estados[$estado] = ($estados[$estado] ?? 0) + 1;
            $totalMensajes += $c['total_mensajes'];
            $totalDuracion += $c['duracion_segundos'];
            if ($estado === 'error') $errores++;

            foreach ($r['log'] as $line) {
                if (stripos($line, 'ERROR') !== false) $errores++;
            }

            // Calidad
            $cal = $r['calidad'] ?? [];
            if (!empty($cal)) {
                $totalMismatches += count($cal['mismatches'] ?? []);
                $totalRespuestasGenericas += $cal['respuestas_genericas'] ?? 0;
                $matchScores[] = $cal['ad_promise_match_score'] ?? 1.0;

                foreach ($cal['ventas_perdidas'] ?? [] as $vp) {
                    $totalVentasPerdidas[] = $vp;
                    $razon = $vp['razon'] ?? 'desconocido';
                    $razonesPerdida[$razon] = ($razonesPerdida[$razon] ?? 0) + 1;
                }
            }
        }

        $conversion = $total > 0 ? round($estados['aceptado'] / $total, 4) : 0;
        $abandono = $total > 0 ? round($estados['abandonado'] / $total, 4) : 0;
        $avgMensajes = $total > 0 ? round($totalMensajes / $total, 1) : 0;
        $avgDuracion = $total > 0 ? round($totalDuracion / $total, 2) : 0;
        $avgMatchScore = !empty($matchScores) ? round(array_sum($matchScores) / count($matchScores), 2) : 1.0;

        $this->analizarPatrones($estados, $total, $avgMensajes, $avgMatchScore, $razonesPerdida, $totalRespuestasGenericas);

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'simulaciones' => $total,
            'resultados' => [
                'conversion' => $conversion,
                'abandono' => $abandono,
                'interesados_sin_cerrar' => $total > 0 ? round($estados['interesado'] / $total, 4) : 0,
                'errores' => $errores,
            ],
            'metricas' => [
                'mensajes_promedio' => $avgMensajes,
                'duracion_promedio_segundos' => $avgDuracion,
                'estados_finales' => $estados,
            ],
            'calidad' => [
                'ad_promise_match_score' => $avgMatchScore,
                'mismatches_total' => $totalMismatches,
                'respuestas_genericas_total' => $totalRespuestasGenericas,
            ],
            'dinero_perdido' => [
                'ventas_perdidas_total' => count($totalVentasPerdidas),
                'razones' => $razonesPerdida,
                'detalle' => array_slice($totalVentasPerdidas, 0, 10), // top 10
            ],
            'observaciones' => $this->observaciones,
            'detalle' => array_map(fn($r) => $r['cliente'], $this->resultados)
        ];
    }

    /**
     * Detectar patrones y generar observaciones
     */
    private function analizarPatrones(array $estados, int $total, float $avgMensajes,
                                       float $matchScore, array $razonesPerdida, int $respGenericas) {
        if ($total < 3) return;

        $convRate = $estados['aceptado'] / $total;
        $abandRate = $estados['abandonado'] / $total;

        // Conversión baja
        if ($convRate < 0.15) {
            $this->observaciones[] = [
                'tipo' => 'critico',
                'mensaje' => 'Conversión ' . round($convRate * 100) . '% — el bot no cierra ventas',
                'accion' => 'Revisar prompt de ventas y técnicas de cierre'
            ];
        }

        // Abandono alto
        if ($abandRate > 0.6) {
            $this->observaciones[] = [
                'tipo' => 'alto',
                'mensaje' => 'Abandono ' . round($abandRate * 100) . '% — clientes se van',
                'accion' => 'Respuestas más cortas, cierre más rápido'
            ];
        }

        // Interesados sin cerrar
        if (($estados['interesado'] ?? 0) > ($estados['aceptado'] ?? 0)) {
            $this->observaciones[] = [
                'tipo' => 'medio',
                'mensaje' => 'Más interesados que compradores — falta empuje final',
                'accion' => 'Agregar call-to-action: "Agendamos para hoy?"'
            ];
        }

        // Conversaciones largas
        if ($avgMensajes > 8) {
            $this->observaciones[] = [
                'tipo' => 'medio',
                'mensaje' => "Promedio $avgMensajes mensajes — demasiado largo",
                'accion' => 'Cerrar en 4-5 mensajes máximo'
            ];
        }

        // === NUEVAS: Calidad ===

        // Mismatch anuncio → bot
        if ($matchScore < 0.7) {
            $this->observaciones[] = [
                'tipo' => 'critico',
                'mensaje' => "Ad Promise Match Score: $matchScore — el bot NO cumple lo que promete el anuncio",
                'accion' => 'Alinear precios/disponibilidad del bot con el copy del anuncio'
            ];
        } elseif ($matchScore < 0.9) {
            $this->observaciones[] = [
                'tipo' => 'alto',
                'mensaje' => "Ad Promise Match Score: $matchScore — hay desajustes entre anuncio y bot",
                'accion' => 'Revisar que el bot confirme precios/servicios del anuncio'
            ];
        }

        // Respuestas genéricas
        if ($respGenericas > $total * 0.3) {
            $this->observaciones[] = [
                'tipo' => 'alto',
                'mensaje' => "$respGenericas respuestas genéricas — bot no entiende al cliente",
                'accion' => 'Mejorar clasificación de intención o agregar más patrones'
            ];
        }

        // Ventas perdidas por razón
        if (!empty($razonesPerdida)) {
            $peorRazon = array_keys($razonesPerdida, max($razonesPerdida))[0];
            $cantidad = $razonesPerdida[$peorRazon];
            $razonTexto = [
                'falta_cierre_directo' => 'Bot no hace pregunta de cierre',
                'paciencia_agotada' => 'Conversación muy larga, cliente se cansa',
                'respuestas_genericas' => 'Bot da respuestas genéricas repetidas',
                'abandono_tras_interes' => 'Cliente interesado abandonó sin razón clara',
            ][$peorRazon] ?? $peorRazon;

            $this->observaciones[] = [
                'tipo' => 'critico',
                'mensaje' => "$cantidad ventas perdidas por: $razonTexto",
                'accion' => "Corregir '$peorRazon' — es donde más dinero se pierde"
            ];
        }

        // Errores
        if (($estados['error'] ?? 0) > 0) {
            $this->observaciones[] = [
                'tipo' => 'critico',
                'mensaje' => $estados['error'] . ' conversaciones con error',
                'accion' => 'Revisar logs del sistema'
            ];
        }

        if (empty($this->observaciones)) {
            $this->observaciones[] = ['tipo' => 'ok', 'mensaje' => 'Sin problemas detectados', 'accion' => 'Mantener'];
        }
    }

    /**
     * Guardar reporte en JSON
     */
    public function guardarReporte(string $path = null): string {
        $reporte = $this->generarReporte();
        $path = $path ?: __DIR__ . '/../resultados/reporte_simulacion.json';
        file_put_contents($path, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $path;
    }
}
