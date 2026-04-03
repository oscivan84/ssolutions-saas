<?php
/**
 * Escenario: Cliente AGRESIVO
 * Responde mal, prueba robustez del bot. No debe romper el sistema.
 */
return [
    'nombre' => 'Cliente Agresivo',
    'descripcion' => 'Cliente difícil que se queja y prueba los límites del bot.',
    'config_cliente' => [
        'nivel_interes' => 'bajo',
        'paciencia' => 5,
        'sensibilidad_precio' => 'alta',
        'probabilidad_respuesta' => 0.9,
    ],
    'mensaje_inicial' => 'Oigan me cobraron de más la última vez, qué servicio tan malo',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 6,
    'respuestas' => [
        ['trigger' => ['disculpa', 'lament', 'sentimos', 'perdón'], 'opciones' => [
            'no me sirven disculpas, quiero solución',
            'ya me cansé de esperar',
            'eso dicen siempre',
        ]],
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'eso es un robo!',
            'muy caro, en otro lado me cobran menos',
            'no pago eso ni loco',
        ]],
        ['trigger' => ['ayudar', 'solución', 'resolver'], 'opciones' => [
            'bueno pero más les vale que sea rápido',
            'ok pero si no funciona quiero reembolso',
            'a ver, prueben a ver',
        ]],
        ['turno' => 1, 'opciones' => [
            'el servicio pasado fue terrible',
            'nunca me respondieron el mensaje anterior',
            'quiero hablar con el jefe',
        ]],
        ['turno' => 3, 'opciones' => [
            'ya perdí la paciencia, me voy',
            'me voy a otro sitio',
        ]],
    ]
];
