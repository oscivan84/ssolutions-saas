<?php
/**
 * Configuración de base de datos para el módulo de soporte
 * Reutiliza la misma conexión de la BD compartida (SSolutions)
 */

// Cargar configuración de entorno si existe
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Configuración de BD - misma que el sistema base
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'dbsolventas17');

// Conexión MySQLi (compatible con el sistema existente)
$conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conexion->connect_errno) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Error de conexión a base de datos'
    ]));
}

$conexion->set_charset("utf8mb4");

/**
 * Ejecuta una consulta preparada de forma segura
 * @param string $sql Consulta con placeholders (?)
 * @param string $types Tipos de parámetros (s=string, i=int, d=double)
 * @param array $params Parámetros a bindear
 * @return mysqli_result|bool
 */
function ejecutarConsulta($sql, $types = '', $params = []) {
    global $conexion;

    if (empty($types)) {
        return $conexion->query($sql);
    }

    // Validar que el número de types coincida con los parámetros
    if (strlen($types) !== count($params)) {
        error_log("SQL Param Mismatch: types=" . strlen($types) . " params=" . count($params) . " | SQL: " . $sql);
        return false;
    }

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        error_log("SQL Prepare Error: " . $conexion->error . " | SQL: " . $sql);
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();
    if ($result === false && $stmt->affected_rows >= 0) {
        $result = $stmt;
    }

    return $result;
}

/**
 * Obtiene el último ID insertado
 */
function ultimoId() {
    global $conexion;
    return $conexion->insert_id;
}

/**
 * Escapa un string para uso en SQL (fallback)
 */
function escapar($valor) {
    global $conexion;
    return $conexion->real_escape_string($valor);
}
