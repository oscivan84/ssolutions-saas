-- ============================================================
-- DATOS DEMO: Para modo demo automatico / onboarding
-- Ejecutar DESPUES de soporte_tables.sql
-- Crea clientes, diagnosticos, tickets y conversaciones fake
-- ============================================================

USE dbsolventas17;

SET @nid = (SELECT idnegocio FROM negocios WHERE slug = 'ssolutions' LIMIT 1);

-- ============================================================
-- CLIENTES DEMO (tabla persona del sistema base)
-- ============================================================
INSERT IGNORE INTO persona (nombre, telefono, email, tipo_persona) VALUES
('Carlos Ramirez', '573001234567', 'carlos@ejemplo.com', 'Cliente'),
('Maria Torres', '573009876543', 'maria@ejemplo.com', 'Cliente'),
('Andres Lopez', '573005551234', 'andres@ejemplo.com', 'Cliente'),
('Laura Gomez', '573008884321', 'laura@ejemplo.com', 'Cliente'),
('Pedro Martinez', '573007776655', 'pedro@ejemplo.com', 'Cliente');

-- ============================================================
-- DIAGNOSTICOS DEMO
-- ============================================================
INSERT INTO diagnosticos (negocio_id, idpersona, nombre_cliente, telefono_cliente, hostname, sistema_operativo,
    cpu_modelo, cpu_uso_porcentaje, cpu_nucleos, ram_total_gb, ram_usada_gb, ram_uso_porcentaje,
    disco_total_gb, disco_usado_gb, disco_uso_porcentaje, temperatura_cpu, procesos_activos,
    problemas_detectados, recomendaciones, nivel_urgencia, resumen_ia) VALUES

(@nid, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 'Carlos Ramirez', '573001234567', 'DESKTOP-CARLOS', 'Windows 10 Pro',
 'Intel Core i5-10400', 92.5, 6, 8.00, 7.20, 90.0,
 500.00, 475.00, 95.0, 78.0, 145,
 '["CPU al 92%: rendimiento critico","RAM casi llena al 90%","Disco al 95%: espacio critico","15 programas en inicio"]',
 '["Cerrar programas innecesarios","Ampliar RAM a 16GB","Limpiar disco urgente"]',
 'critica',
 'Tu equipo esta al limite. El procesador trabaja al 92%, la RAM al 90% y el disco al 95%. Necesita atencion urgente para evitar fallas.'),

(@nid, (SELECT idpersona FROM persona WHERE telefono='573009876543' LIMIT 1),
 'Maria Torres', '573009876543', 'LAPTOP-MARIA', 'Windows 11 Home',
 'AMD Ryzen 5 5600', 45.0, 6, 16.00, 8.50, 53.1,
 256.00, 220.00, 85.9, 65.0, 82,
 '["Disco al 86%: espacio limitado"]',
 '["Liberar espacio en disco","Desinstalar programas no usados"]',
 'media',
 'Tu laptop funciona bien en general. Solo el disco esta algo lleno al 86%. Recomendamos liberar espacio.'),

(@nid, (SELECT idpersona FROM persona WHERE telefono='573005551234' LIMIT 1),
 'Andres Lopez', '573005551234', 'PC-ANDRES', 'Windows 10 Home',
 'Intel Core i3-8100', 78.0, 4, 4.00, 3.60, 90.0,
 1000.00, 450.00, 45.0, 72.0, 95,
 '["RAM critica al 90%: solo 4GB","CPU elevado al 78%"]',
 '["Urgente: ampliar RAM de 4GB a 8GB","Optimizar programas de inicio"]',
 'alta',
 'Tu PC tiene poca memoria RAM (4GB al 90%). Esto causa lentitud. Recomendamos ampliar a 8GB como minimo.');

-- ============================================================
-- TICKETS DEMO (vinculados a diagnosticos)
-- ============================================================
SET @diag1 = (SELECT iddiagnostico FROM diagnosticos WHERE hostname='DESKTOP-CARLOS' AND negocio_id=@nid LIMIT 1);
SET @diag2 = (SELECT iddiagnostico FROM diagnosticos WHERE hostname='LAPTOP-MARIA' AND negocio_id=@nid LIMIT 1);
SET @diag3 = (SELECT iddiagnostico FROM diagnosticos WHERE hostname='PC-ANDRES' AND negocio_id=@nid LIMIT 1);

INSERT INTO tickets (negocio_id, iddiagnostico, idpersona, codigo_ticket, titulo, descripcion, tipo_servicio, estado, prioridad, costo_estimado) VALUES
(@nid, @diag1, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 'TKT-DEMO-001', 'Diagnostico critico - DESKTOP-CARLOS',
 'Equipo al limite: CPU 92%, RAM 90%, Disco 95%. Necesita atencion urgente.',
 'en_sitio', 'en_proceso', 'critica', 104000),

(@nid, @diag2, (SELECT idpersona FROM persona WHERE telefono='573009876543' LIMIT 1),
 'TKT-DEMO-002', 'Limpieza de disco - LAPTOP-MARIA',
 'Disco al 86%. Limpieza y optimizacion recomendada.',
 'remoto', 'abierto', 'media', 25000),

(@nid, @diag3, (SELECT idpersona FROM persona WHERE telefono='573005551234' LIMIT 1),
 'TKT-DEMO-003', 'Ampliacion RAM - PC-ANDRES',
 'Solo 4GB de RAM al 90%. Necesita ampliacion urgente a 8GB.',
 'taller', 'esperando_repuestos', 'alta', 78000);

-- ============================================================
-- SCORES DEMO
-- ============================================================
INSERT INTO score_clientes (negocio_id, idpersona, score, razon, accion_sugerida, source) VALUES
(@nid, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 'caliente', 'Ticket critico activo, pregunto precio, respondio rapido', 'Ofrecer servicio express con descuento', 'ia'),
(@nid, (SELECT idpersona FROM persona WHERE telefono='573009876543' LIMIT 1),
 'tibio', 'Ticket abierto pero no ha confirmado servicio', 'Enviar recordatorio con cotizacion', 'ia'),
(@nid, (SELECT idpersona FROM persona WHERE telefono='573005551234' LIMIT 1),
 'caliente', 'Necesita repuesto urgente, acepto cotizacion', 'Confirmar disponibilidad de RAM y agendar', 'ia');

-- ============================================================
-- CONVERSACIONES DEMO
-- ============================================================
SET @tk1 = (SELECT idticket FROM tickets WHERE codigo_ticket='TKT-DEMO-001' LIMIT 1);

INSERT INTO conversaciones_whatsapp (negocio_id, idpersona, idticket, telefono, estado_flujo, contexto_json, ultimo_mensaje_enviado, ultimo_mensaje_recibido) VALUES
(@nid, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1), @tk1,
 '573001234567', 'cotizacion',
 '{"idpersona": 1, "idticket": 1}',
 'Servicio: En sitio\nTarifa: $40,000/hora\nQuieres confirmar?',
 'si, cuando pueden venir?');

-- ============================================================
-- MENSAJES DEMO
-- ============================================================
INSERT INTO mensajes_whatsapp (negocio_id, idticket, idpersona, telefono, direccion, tipo, contenido, estado) VALUES
(@nid, @tk1, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 '573001234567', 'enviado', 'texto',
 'Hola Carlos! Hemos recibido el diagnostico de tu equipo DESKTOP-CARLOS.\n\nCodigo: TKT-DEMO-001\nUrgencia: CRITICA\n\nTu equipo esta al limite. Necesita atencion urgente.', 'entregado'),

(@nid, @tk1, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 '573001234567', 'recibido', 'texto',
 'cuanto vale el servicio?', 'entregado'),

(@nid, @tk1, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 '573001234567', 'enviado', 'texto',
 'El servicio en sitio tiene un costo estimado de $104,000. Incluye revision completa, limpieza y optimizacion. Agendamos para hoy o manana?', 'entregado'),

(@nid, @tk1, (SELECT idpersona FROM persona WHERE telefono='573001234567' LIMIT 1),
 '573001234567', 'recibido', 'texto',
 'si, cuando pueden venir?', 'entregado');

-- ============================================================
-- EVENTOS DEMO
-- ============================================================
INSERT INTO eventos (negocio_id, tipo, payload, procesado, fecha_procesado) VALUES
(@nid, 'ticket_creado', '{"idticket": 1, "codigo": "TKT-DEMO-001", "prioridad": "critica"}', 1, NOW()),
(@nid, 'ticket_creado', '{"idticket": 2, "codigo": "TKT-DEMO-002", "prioridad": "media"}', 1, NOW()),
(@nid, 'estado_cambiado', '{"idticket": 1, "estado_anterior": "abierto", "estado": "en_proceso"}', 1, NOW()),
(@nid, 'mensaje_recibido', '{"telefono": "573001234567", "contenido": "cuanto vale?"}', 1, NOW());

-- ============================================================
-- JOBS DEMO
-- ============================================================
INSERT INTO jobs (negocio_id, tipo, payload, prioridad, estado, intentos, max_intentos, resultado, fecha_fin) VALUES
(@nid, 'scoring', '{"idpersona": 1}', 'alta', 'completado', 1, 3, '{"score": "caliente"}', NOW()),
(@nid, 'enviar_whatsapp', '{"telefono": "573001234567", "mensaje": "Hola Carlos..."}', 'media', 'completado', 1, 3, '{"enviado": true}', NOW()),
(@nid, 'scoring', '{"idpersona": 2}', 'media', 'completado', 1, 3, '{"score": "tibio"}', NOW());

SELECT 'Datos demo insertados exitosamente' AS resultado;
