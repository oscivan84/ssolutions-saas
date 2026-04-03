<?php
/**
 * Módulo: Estética / Salón de Belleza
 * Define servicios, vistas y config específica del nicho estético.
 */
return [
    'nombre' => 'Salón de Estética',
    'slug' => 'estetica',
    'descripcion' => 'Gestión de citas, servicios de belleza y seguimiento de clientas',
    'icono' => 'star',

    'vistas' => [
        ['id' => 'soporte_dashboard', 'nombre' => 'Dashboard', 'icono' => 'dashboard'],
        ['id' => 'soporte_tickets', 'nombre' => 'Citas', 'icono' => 'calendar'],
        ['id' => 'soporte_automatizacion', 'nombre' => 'Automatizacion', 'icono' => 'bolt'],
        ['id' => 'soporte_configuracion', 'nombre' => 'Configuracion', 'icono' => 'cog'],
    ],

    'servicios_default' => [
        ['nombre' => 'Uñas Acrílicas', 'slug' => 'unas_acrilicas', 'precio_base' => 45000, 'duracion_minutos' => 90],
        ['nombre' => 'Uñas Semipermanentes', 'slug' => 'unas_semi', 'precio_base' => 35000, 'duracion_minutos' => 60],
        ['nombre' => 'Cejas y Pestañas', 'slug' => 'cejas_pestanas', 'precio_base' => 25000, 'duracion_minutos' => 45],
        ['nombre' => 'Corte de Cabello', 'slug' => 'corte', 'precio_base' => 20000, 'duracion_minutos' => 30],
        ['nombre' => 'Tinte + Mechas', 'slug' => 'tinte', 'precio_base' => 80000, 'duracion_minutos' => 120],
        ['nombre' => 'Tratamiento Capilar', 'slug' => 'tratamiento', 'precio_base' => 55000, 'duracion_minutos' => 60],
    ],

    'prompt_ventas' => "Eres una asistente amigable de un salon de belleza.
OBJETIVO: Agendar citas y generar visitas recurrentes.
Tono: cercano, femenino, entusiasta. Usa emojis moderados.
TECNICAS: Mencionar promociones, combos, 'te va a quedar hermoso'.
Si la clienta duda, ofrecer un servicio mas economico primero.
Maximo 80 palabras por respuesta.",

    'features' => ['citas', 'recordatorios', 'promos'],
];
