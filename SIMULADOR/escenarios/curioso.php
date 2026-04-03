<?php
/**
 * Escenario: Cliente CURIOSO
 * Pregunta mucho pero nunca compra. Solo busca info.
 */
return [
    'nombre' => 'Cliente Curioso',
    'descripcion' => 'Pregunta mucho pero sin intención real de compra. Prueba la capacidad del bot de redirigir.',
    'config_cliente' => [
        'nivel_interes' => 'bajo',
        'paciencia' => 7,
        'sensibilidad_precio' => 'alta',
        'probabilidad_respuesta' => 0.85,
    ],
    'mensaje_inicial' => 'Hola, quiero saber que servicios ofrecen',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 8,
    'respuestas' => [
        ['trigger' => ['precio', 'costo', '$'], 'opciones' => [
            'y eso incluye todo?',
            'uy eso es mucho, y no hay algo gratis?',
            'ok ok, y cuanto sale el taller?',
        ]],
        ['trigger' => ['agendar', 'confirmar'], 'opciones' => [
            'no no, solo preguntaba',
            'por ahora no, gracias',
            'despues te aviso',
        ]],
        ['turno' => 1, 'opciones' => [
            'y ustedes reparan laptops también?',
            'que marcas atienden?',
            'y también arreglan impresoras?',
        ]],
        ['turno' => 2, 'opciones' => [
            'y el servicio incluye garantía?',
            'cuanto se demora una reparación?',
        ]],
        ['turno' => 4, 'opciones' => [
            'bueno gracias por la información',
            'ok ya se, gracias',
        ]],
    ]
];
