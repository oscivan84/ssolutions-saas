<?php
/**
 * Configuración del servicio de IA
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

define('AI_PROVIDER', getConfigSoporte('ai_provider', 'anthropic'));
define('AI_API_KEY', getConfigSoporte('ai_api_key', ''));
define('AI_MODEL', getConfigSoporte('ai_model', 'claude-haiku-4-5-20251001'));
