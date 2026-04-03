<?php
/**
 * Escenario: Cliente que viene de anuncio de PROBLEMA
 * "¿Tu PC está lento? Nosotros lo arreglamos"
 * Llega describiendo su problema, busca solución.
 */
return [
    'nombre' => 'Anuncio: Problema',
    'descripcion' => 'Cliente atraído por anuncio que describe su problema. Viene con dolor real.',
    'config_cliente' => [
        'nivel_interes' => 'alto',
        'paciencia' => 7,
        'sensibilidad_precio' => 'media',
        'probabilidad_respuesta' => 0.85,
    ],
    'mensaje_inicial' => 'Hola vi su anuncio, mi computador esta super lento y se apaga solo, necesito ayuda urgente',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 8,
    'respuestas' => [
        ['trigger' => ['diagnostico', 'revision', 'revisar'], 'opciones' => [
            'si por favor, cuando pueden revisarlo?',
            'necesito que lo revisen urgente, se apaga cada rato',
            'ok como hacemos para el diagnostico?',
        ]],
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'ok me parece bien, necesito que lo arreglen rapido',
            'no importa el precio, necesito que funcione',
            'dale, cuanto es?',
        ]],
        ['trigger' => ['agendar', 'confirmar', 'cuando'], 'opciones' => [
            'hoy mismo si se puede, es urgente',
            'si confirmo, lo necesito ya',
        ]],
        ['trigger' => ['remoto', 'sitio', 'taller'], 'opciones' => [
            'pueden venir a mi casa? no puedo moverlo',
            'en sitio porque no enciende bien',
        ]],
        ['turno' => 1, 'opciones' => [
            'lleva como 2 semanas asi y cada vez peor',
            'creo que tiene virus porque abre ventanas solas',
            'tambien esta muy lleno el disco',
        ]],
    ]
];
