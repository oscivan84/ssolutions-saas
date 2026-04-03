<?php
require_once __DIR__ . '/EstrategiaBase.php';

class EstrategiaPruebaSocial extends EstrategiaBase {

    public function nombre(): string { return 'prueba_social'; }
    public function descripcion(): string { return 'Agrega validacion externa para generar confianza'; }

    public function esRelevante(array $problemas): bool {
        return in_array('falta_confianza', $problemas)
            || in_array('abandono_en_precio', $problemas)
            || in_array('interesado_sin_cerrar', $problemas);
    }

    public function aplicar(string $prompt, array $problemas = []): string {
        $social = "\n\nPRUEBA SOCIAL (usar naturalmente):"
            . "\n- Mencionar cantidad de clientes: 'Ya hemos ayudado a mas de 50 negocios este mes'"
            . "\n- Mencionar resultados: 'Nuestros clientes recuperan en promedio 5 clientes por semana'"
            . "\n- Mencionar satisfaccion: 'El 95% de nuestros clientes repiten servicio'"
            . "\n- Si el cliente duda del precio: 'Es la misma tarifa que manejan los mejores talleres de la zona'";

        // Insertar después de TECNICAS o al final
        if (stripos($prompt, 'TECNICAS') !== false) {
            $prompt = preg_replace('/(TECNICAS[^\n]*\n(?:[^\n]*\n)*)/i', "$1$social\n", $prompt, 1);
        } else {
            $prompt .= $social;
        }

        return $prompt;
    }
}
