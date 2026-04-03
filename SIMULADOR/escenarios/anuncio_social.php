<?php
/**
 * Escenario: Cliente que viene de anuncio con PRUEBA SOCIAL
 * "Más de 500 equipos reparados este año"
 * Llega curioso pero con algo de confianza. Pregunta bastante.
 */
return [
    'nombre' => 'Anuncio: Prueba Social',
    'descripcion' => 'Cliente atraído por testimonios/numeros. Tiene confianza parcial, pregunta para confirmar.',
    'config_cliente' => [
        'nivel_interes' => 'medio',
        'paciencia' => 6,
        'sensibilidad_precio' => 'media',
        'probabilidad_respuesta' => 0.8,
    ],
    'mensaje_inicial' => 'Hola vi que han reparado muchos equipos, necesito saber si pueden con el mio',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 8,
    'respuestas' => [
        ['trigger' => ['si', 'claro', 'podemos', 'por supuesto'], 'opciones' => [
            'ok y tienen garantia?',
            'que marcas atienden?',
            'cuanto se demoran normalmente?',
        ]],
        ['trigger' => ['garantia', 'respald'], 'opciones' => [
            'perfecto, eso me da confianza. cuanto cuesta?',
            'ok y si no queda bien que pasa?',
        ]],
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'me parece razonable, como agendo?',
            'ok dejame pensarlo y te aviso',
            'tienen algun descuento para clientes nuevos?',
        ]],
        ['trigger' => ['descuento', 'promo', 'nuevo'], 'opciones' => [
            'ah bueno dale, con descuento si me animo',
            'ok acepto, cuando puede ser?',
        ]],
        ['trigger' => ['agendar', 'confirmar'], 'opciones' => [
            'si para esta semana si se puede',
            'dale confirmo',
        ]],
        ['turno' => 2, 'opciones' => [
            'y ustedes donde quedan?',
            'llevan mucho tiempo en esto?',
        ]],
    ]
];
