<?php
/**
 * Escenario: Cliente INTERESADO (caliente)
 * Pregunta precio, acepta rápido, quiere agendar.
 */
return [
    'nombre' => 'Cliente Interesado',
    'descripcion' => 'Cliente caliente que busca servicio activamente. Alta probabilidad de cierre.',
    'config_cliente' => [
        'nivel_interes' => 'alto',
        'paciencia' => 8,
        'sensibilidad_precio' => 'baja',
        'probabilidad_respuesta' => 0.95,
    ],
    'mensaje_inicial' => 'Hola, mi computador está muy lento y necesito que me lo arreglen urgente',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 8,
    'respuestas' => [
        ['trigger' => ['precio', 'costo', 'tarifa', '$'], 'opciones' => [
            'ok, me parece bien el precio, cuando pueden?',
            'si, acepto. Como agendamos?',
            'dale, quiero el servicio remoto',
        ]],
        ['trigger' => ['agendar', 'confirmar', 'proceder', 'cuando'], 'opciones' => [
            'si, para mañana estaría perfecto',
            'hoy mismo si se puede',
            'dale, confirmo',
        ]],
        ['trigger' => ['diagnostico', 'revision', 'equipo'], 'opciones' => [
            'cuanto cuesta la revision?',
            'si, quiero el diagnostico',
            'necesito que revisen mi laptop urgente',
        ]],
        ['turno' => 1, 'opciones' => [
            'cuanto cobran por arreglar un computador?',
            'necesito saber el precio del servicio',
            'que servicios tienen?',
        ]],
        ['turno' => 2, 'opciones' => [
            'me interesa, como funciona?',
            'ok, quiero agendar',
            'si, hagamoslo',
        ]],
    ]
];
