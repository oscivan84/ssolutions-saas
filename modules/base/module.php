<?php
/**
 * Módulo: Base
 * Siempre activo. Provee funcionalidad core compartida.
 */
return [
    'nombre' => 'Base',
    'slug' => 'base',
    'descripcion' => 'Módulo base: WhatsApp, IA, conversaciones, automatizaciones',
    'icono' => 'cogs',

    'vistas' => [
        ['id' => 'soporte_dashboard', 'nombre' => 'Dashboard', 'icono' => 'dashboard'],
        ['id' => 'soporte_automatizacion', 'nombre' => 'Automatizacion', 'icono' => 'bolt'],
        ['id' => 'soporte_configuracion', 'nombre' => 'Configuracion', 'icono' => 'cog'],
    ],

    'servicios_default' => [],

    'prompt_ventas' => "Eres un asistente de ventas profesional.
OBJETIVO: Guiar al cliente hacia una compra o cita.
Respuestas cortas, amigables y orientadas al cierre.
Maximo 80 palabras.",

    'features' => ['whatsapp', 'ia', 'automatizaciones', 'scoring'],
];
