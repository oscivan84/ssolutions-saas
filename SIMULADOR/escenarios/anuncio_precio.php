<?php
/**
 * Escenario: Cliente que viene de anuncio de PRECIO
 * "Reparación de PC desde $25,000"
 * Llega preguntando directo por el precio que vio.
 */
return [
    'nombre' => 'Anuncio: Precio',
    'descripcion' => 'Cliente atraído por anuncio de precio bajo. Espera exactamente lo que vio.',
    'config_cliente' => [
        'nivel_interes' => 'alto',
        'paciencia' => 5,
        'sensibilidad_precio' => 'alta',
        'probabilidad_respuesta' => 0.9,
    ],
    'mensaje_inicial' => 'Hola vi el anuncio de reparacion desde 25 mil, quiero ese servicio',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 7,
    'respuestas' => [
        ['trigger' => ['25', 'precio', 'costo', 'tarifa', '$'], 'opciones' => [
            'si pero el anuncio decia 25 mil, ese es el precio o no?',
            'ok pero quiero el de 25 mil que vi en el anuncio',
            'por ese precio si me interesa, como agendo?',
        ]],
        ['trigger' => ['agendar', 'confirmar', 'cuando'], 'opciones' => [
            'dale para hoy si se puede',
            'mañana en la tarde',
            'si confirmo',
        ]],
        ['trigger' => ['diagnostico', 'revision', 'depende'], 'opciones' => [
            'pero el anuncio no decia que habia diagnostico aparte',
            'ok y eso cuanto cuesta adicional?',
        ]],
        ['trigger' => ['remoto', 'sitio', 'taller'], 'opciones' => [
            'el remoto, que es el mas barato',
            'remoto esta bien',
        ]],
        ['turno' => 3, 'opciones' => [
            'entonces cuanto me sale en total?',
            'bueno pero asegureme que es desde 25 mil',
        ]],
    ]
];
