<?php
/**
 * Escenario: Cliente INDECISO (tibio)
 * Pregunta varias veces, duda, necesita seguimiento.
 */
return [
    'nombre' => 'Cliente Indeciso',
    'descripcion' => 'Cliente que tiene interés pero duda. Necesita convencimiento.',
    'config_cliente' => [
        'nivel_interes' => 'medio',
        'paciencia' => 6,
        'sensibilidad_precio' => 'media',
        'probabilidad_respuesta' => 0.7,
    ],
    'mensaje_inicial' => 'Hola, mi computador a veces se pone lento, no sé si necesite algo',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 10,
    'respuestas' => [
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'hmm no sé, está un poco caro no?',
            'déjame pensarlo',
            'y no hay algo más barato?',
            'cuanto es lo mínimo?',
        ]],
        ['trigger' => ['agendar', 'confirmar'], 'opciones' => [
            'todavia no estoy seguro',
            'lo pienso y te aviso',
            'puede ser... para cuando sería?',
        ]],
        ['trigger' => ['descuento', 'promocion', 'gratis', 'sin costo'], 'opciones' => [
            'ah bueno, si tiene eso entonces sí me interesa',
            'ok dale, con el descuento sí',
        ]],
        ['trigger' => ['urgente', 'empeorar', 'problema puede'], 'opciones' => [
            'en serio? bueno entonces sí necesito revisarlo',
            'ok me asustaste, hagamoslo',
        ]],
        ['turno' => 1, 'opciones' => [
            'y cuanto sale eso?',
            'es muy caro?',
            'que incluye el servicio?',
        ]],
        ['turno' => 3, 'opciones' => [
            'lo tengo que pensar',
            'no sé, después te digo',
            'y si mejor espero?',
        ]],
    ]
];
