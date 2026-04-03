<?php
/**
 * AnalizadorConversaciones — Detecta fallos y puntos de abandono en conversaciones simuladas.
 *
 * Analiza los resultados del Evaluador y detecta patrones:
 * - En qué turno abandonan los clientes
 * - Si el bot no hace cierre
 * - Si las respuestas son muy largas
 * - Si responde genérico sin contexto
 */

class AnalizadorConversaciones {

    /**
     * Analizar reporte del simulador y detectar problemas
     * @param array $reporte Reporte del Evaluador
     * @return array ['problemas' => [...], 'detalle' => [...], 'score_actual' => float]
     */
    public function analizar(array $reporte): array {
        $problemas = [];
        $detalle = [];
        $resultados = $reporte['resultados'] ?? [];
        $metricas = $reporte['metricas'] ?? [];
        $conversaciones = $reporte['detalle'] ?? [];

        $conversion = $resultados['conversion'] ?? 0;
        $abandono = $resultados['abandono'] ?? 0;
        $msgPromedio = $metricas['mensajes_promedio'] ?? 0;

        // 1. Conversión baja
        if ($conversion < 0.25) {
            $problemas[] = 'falta_cierre';
            $detalle[] = "Conversión del " . round($conversion * 100) . "% — el bot no está cerrando ventas";
        }

        // 2. Abandono alto
        if ($abandono > 0.5) {
            $problemas[] = 'abandono_tardio';
            $detalle[] = "Abandono del " . round($abandono * 100) . "% — clientes se van antes de comprar";
        }

        // 3. Conversaciones largas
        if ($msgPromedio > 6) {
            $problemas[] = 'conversacion_larga';
            $problemas[] = 'respuesta_larga';
            $detalle[] = "Promedio de $msgPromedio mensajes — demasiado largo para WhatsApp";
        }

        // 4. Interesados que no cierran
        $interesados = $resultados['interesados_sin_cerrar'] ?? 0;
        if ($interesados > 0.15) {
            $problemas[] = 'interesado_sin_cerrar';
            $detalle[] = round($interesados * 100) . "% de clientes interesados no cerraron — falta empuje final";
        }

        // 5. Analizar punto de abandono (en qué turno se pierden)
        $abandonoPorTurno = $this->detectarPuntoAbandono($conversaciones);
        if ($abandonoPorTurno) {
            $detalle[] = "Mayor abandono en turno {$abandonoPorTurno['turno']} ({$abandonoPorTurno['porcentaje']}% de abandonos)";
            if ($abandonoPorTurno['turno'] <= 2) {
                $problemas[] = 'abandono_temprano';
                $detalle[] = "Abandono en primeros mensajes — primera impresión no engancha";
            }
        }

        // 6. Detectar si hay problemas de precio
        $abandonosPrecio = 0;
        foreach ($conversaciones as $c) {
            if ($c['estado_final'] === 'abandonado') {
                // Revisar si el último mensaje del bot mencionaba precio
                $totalMsgs = $c['total_mensajes'] ?? 0;
                if ($totalMsgs >= 3 && $totalMsgs <= 5) {
                    $abandonosPrecio++;
                }
            }
        }
        if (count($conversaciones) > 0 && ($abandonosPrecio / count($conversaciones)) > 0.2) {
            $problemas[] = 'abandono_en_precio';
            $problemas[] = 'falta_confianza';
            $detalle[] = "Muchos abandonos entre mensaje 3-5 (posible choque de precio)";
        }

        // 7. Falta de urgencia (si no hay cierres rápidos)
        $cierresRapidos = 0;
        foreach ($conversaciones as $c) {
            if ($c['estado_final'] === 'aceptado' && ($c['total_mensajes'] ?? 99) <= 4) {
                $cierresRapidos++;
            }
        }
        if (count($conversaciones) > 0 && ($cierresRapidos / max(1, count($conversaciones))) < 0.1) {
            $problemas[] = 'falta_urgencia';
            $detalle[] = "Pocos cierres rápidos — falta urgencia en las respuestas";
        }

        // Deduplicar
        $problemas = array_unique($problemas);

        return [
            'problemas' => array_values($problemas),
            'detalle' => $detalle,
            'score_actual' => $this->calcularScore($resultados, $metricas),
            'metricas' => [
                'conversion' => $conversion,
                'abandono' => $abandono,
                'mensajes_promedio' => $msgPromedio,
                'interesados_sin_cerrar' => $interesados
            ]
        ];
    }

    /**
     * Calcular score compuesto del prompt
     */
    public function calcularScore(array $resultados, array $metricas): float {
        $conversion = $resultados['conversion'] ?? 0;
        $abandono = $resultados['abandono'] ?? 0;
        $msgPromedio = $metricas['mensajes_promedio'] ?? 5;

        // score = (conversion * 0.6) - (abandono * 0.3) - (mensajes_promedio * 0.01)
        $score = ($conversion * 0.6) - ($abandono * 0.3) - (min($msgPromedio, 10) * 0.01);
        return round($score, 4);
    }

    private function detectarPuntoAbandono(array $conversaciones): ?array {
        $abandonosPorTurno = [];
        $totalAbandonos = 0;

        foreach ($conversaciones as $c) {
            if ($c['estado_final'] === 'abandonado') {
                $turno = intval($c['mensajes_enviados'] ?? 1);
                $abandonosPorTurno[$turno] = ($abandonosPorTurno[$turno] ?? 0) + 1;
                $totalAbandonos++;
            }
        }

        if ($totalAbandonos === 0) return null;

        // Encontrar turno con más abandonos
        arsort($abandonosPorTurno);
        $turnoMax = array_key_first($abandonosPorTurno);

        return [
            'turno' => $turnoMax,
            'cantidad' => $abandonosPorTurno[$turnoMax],
            'porcentaje' => round(($abandonosPorTurno[$turnoMax] / $totalAbandonos) * 100)
        ];
    }
}
