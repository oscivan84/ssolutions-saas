<?php
/**
 * Configuración de WhatsApp Cloud API
 * Carga configuración del negocio activo via Tenant
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tenant.php';

// Wrapper de compatibilidad: getConfigSoporte ahora usa Tenant::config()
if (!function_exists('getConfigSoporte')) {
    function getConfigSoporte($clave, $default = null) {
        return Tenant::config($clave, $default);
    }
}

define('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0');
define('WHATSAPP_TOKEN', getConfigSoporte('whatsapp_token', ''));
define('WHATSAPP_PHONE_ID', getConfigSoporte('whatsapp_phone_id', ''));
define('WHATSAPP_VERIFY_TOKEN', getConfigSoporte('whatsapp_verify_token', 'mi_token_verificacion'));
