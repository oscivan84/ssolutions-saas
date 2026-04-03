<?php
require_once __DIR__ . '/EstrategiaBase.php';

class EstrategiaObjeciones extends EstrategiaBase {

    public function nombre(): string { return 'objeciones'; }
    public function descripcion(): string { return 'Anticipa y maneja objeciones de precio y confianza'; }

    public function esRelevante(array $problemas): bool {
        return in_array('abandono_en_precio', $problemas)
            || in_array('falta_confianza', $problemas)
            || in_array('cliente_duda', $problemas);
    }

    public function aplicar(string $prompt, array $problemas = []): string {
        $objeciones = "\n\nMANEJO DE OBJECIONES:"
            . "\n- Si dice 'muy caro': Ofrecer opcion mas economica o descomponer el precio ('son solo X por hora')"
            . "\n- Si dice 'lo pienso': Crear micro-compromiso ('te reservo el turno 10 min mientras decides?')"
            . "\n- Si dice 'en otro lado es mas barato': Diferenciar por calidad/garantia ('incluimos garantia de 30 dias')"
            . "\n- Si dice 'no tengo tiempo': Ofrecer servicio express o remoto"
            . "\n- Si no responde: Enviar beneficio concreto ('te incluimos diagnostico gratis si agendas hoy')"
            . "\n- NUNCA discutir con el cliente. Siempre validar su preocupacion y redirigir.";

        $prompt .= $objeciones;
        return $prompt;
    }
}
