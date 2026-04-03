<?php
/**
 * Escenario: Cliente que viene de anuncio de URGENCIA
 * "Últimos 5 turnos disponibles esta semana"
 * Llega con prisa pero desconfiado. Quiere validar que es real.
 */
return [
    'nombre' => 'Anuncio: Urgencia',
    'descripcion' => 'Cliente atraído por escasez/urgencia. Viene rápido pero puede irse igual de rápido.',
    'config_cliente' => [
        'nivel_interes' => 'alto',
        'paciencia' => 4,
        'sensibilidad_precio' => 'baja',
        'probabilidad_respuesta' => 0.95,
    ],
    'mensaje_inicial' => 'Hola vi que quedan pocos turnos, aun hay disponibilidad?',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 5,
    'respuestas' => [
        ['trigger' => ['si', 'queda', 'disponib', 'turno'], 'opciones' => [
            'perfecto reservame uno ya, que necesitan?',
            'ok quiero el de hoy, como hago?',
            'dale antes que se acaben',
        ]],
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'ok esta bien, confirmo',
            'dale no importa el precio, necesito el turno',
        ]],
        ['trigger' => ['no', 'agotado', 'esperar'], 'opciones' => [
            'uy que mal, avíseme cuando haya',
            'bueno entonces la proxima semana?',
        ]],
        ['trigger' => ['datos', 'nombre', 'telefono', 'equipo'], 'opciones' => [
            'si claro, mi PC es un Lenovo que esta lento',
            'tengo un portatil HP con problema de disco',
        ]],
        ['turno' => 1, 'opciones' => [
            'cuantos turnos quedan?',
            'para cuando es el proximo disponible?',
        ]],
    ]
];
