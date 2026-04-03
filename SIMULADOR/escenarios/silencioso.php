<?php
/**
 * Escenario: Cliente SILENCIOSO
 * Envía un mensaje y no responde más. Ideal para probar recovery/seguimiento.
 */
return [
    'nombre' => 'Cliente Silencioso',
    'descripcion' => 'Cliente que escribe una vez y desaparece. Prueba sistema de recuperación.',
    'config_cliente' => [
        'nivel_interes' => 'medio',
        'paciencia' => 2,
        'sensibilidad_precio' => 'media',
        'probabilidad_respuesta' => 0.15,  // Muy baja — casi nunca responde
    ],
    'mensaje_inicial' => 'Hola necesito ayuda con mi computador',
    'condicion_exito' => 'aceptado',
    'max_turnos' => 4,
    'respuestas' => [
        // Casi nunca llega aquí por probabilidad_respuesta baja
        ['turno' => 1, 'opciones' => [
            '...',
            'ah si?',
        ]],
    ]
];
