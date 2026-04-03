<?php
/**
 * Detecta la base URL del proyecto automáticamente.
 * Funciona tanto en local (/landingV2/) como en Railway (/).
 */
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// En Railway/Render la app está en la raíz
// En local/XAMPP puede estar en /landingV2/
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$basePath = '/';

// Si estamos dentro de una subcarpeta (ej: /landingV2/api/dashboard.php)
if (strpos($scriptDir, '/landingV2') !== false) {
    $basePath = '/landingV2/';
} elseif (strpos($scriptDir, '/api') !== false || strpos($scriptDir, '/ajax') !== false) {
    $basePath = '/';
}

define('BASE_URL', $protocol . '://' . $host . $basePath);
define('AJAX_BASE', $basePath . 'ajax/');
define('API_BASE', $basePath . 'api/');
