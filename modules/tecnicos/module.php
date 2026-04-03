<?php
/**
 * Módulo: Técnicos / Soporte de Computadores
 * Define servicios, vistas y config específica del nicho técnico.
 */
return [
    'nombre' => 'Soporte Técnico',
    'slug' => 'tecnico',
    'descripcion' => 'Diagnóstico, reparación y mantenimiento de equipos de cómputo',
    'icono' => 'wrench',

    'vistas' => [
        ['id' => 'soporte_dashboard', 'nombre' => 'Dashboard', 'icono' => 'dashboard'],
        ['id' => 'soporte_tickets', 'nombre' => 'Tickets', 'icono' => 'ticket'],
        ['id' => 'soporte_diagnosticos', 'nombre' => 'Diagnosticos', 'icono' => 'stethoscope'],
        ['id' => 'soporte_automatizacion', 'nombre' => 'Automatizacion', 'icono' => 'bolt'],
        ['id' => 'soporte_configuracion', 'nombre' => 'Configuracion', 'icono' => 'cog'],
    ],

    'servicios_default' => [
        ['nombre' => 'Soporte Remoto', 'slug' => 'remoto', 'precio_base' => 25000, 'duracion_minutos' => 60],
        ['nombre' => 'Soporte En Sitio', 'slug' => 'en_sitio', 'precio_base' => 40000, 'duracion_minutos' => 60],
        ['nombre' => 'Soporte Taller', 'slug' => 'taller', 'precio_base' => 30000, 'duracion_minutos' => 60],
    ],

    'prompt_ventas' => "Eres un asistente de ventas para un taller de reparacion de computadores.
OBJETIVO: Convertir la conversacion en un servicio agendado.
SERVICIOS: Remoto, En sitio, Taller.
TECNICAS: Mencionar urgencia si hay problemas criticos. Ofrecer diagnostico gratuito.
Maximo 80 palabras por respuesta.",

    'features' => ['diagnosticos', 'mantenimientos', 'repuestos'],
];
