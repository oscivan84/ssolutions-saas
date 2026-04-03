<?php
/**
 * Seguridad: Encriptación de API keys + Rate limiting PHP
 */

/**
 * Encriptar un valor sensible (API keys, tokens)
 * Usa AES-256-CBC con la MASTER_KEY del .env
 */
function encriptar($valor) {
    $key = getenv('MASTER_ENCRYPT_KEY');
    if (!$key) {
        error_log("SECURITY WARNING: MASTER_ENCRYPT_KEY not set. Using dev fallback.");
        $key = 'ss-dev-only-' . md5(__DIR__); // Único por instalación al menos
    }
    $key = substr(hash('sha256', $key, true), 0, 32);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($valor, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Desencriptar un valor
 */
function desencriptar($valorEncriptado) {
    $key = getenv('MASTER_ENCRYPT_KEY');
    if (!$key) {
        $key = 'ss-dev-only-' . md5(__DIR__);
    }
    $key = substr(hash('sha256', $key, true), 0, 32);
    $data = base64_decode($valorEncriptado);
    if (strpos($data, '::') === false) {
        return $valorEncriptado; // No está encriptado, retornar tal cual (backward compat)
    }
    list($iv, $encrypted) = explode('::', $data, 2);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

/**
 * Rate limiter simple basado en sesión/IP
 * @param string $key Identificador (ej: 'api_diagnostico', 'webhook')
 * @param int $maxRequests Máximo de requests permitidos
 * @param int $windowSeconds Ventana de tiempo en segundos
 * @return bool true si está dentro del límite, false si excedió
 */
function checkRateLimit($key, $maxRequests = 60, $windowSeconds = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheFile = sys_get_temp_dir() . '/ss_rate_' . md5("rate_{$key}_{$ip}") . '.dat';

    // Atomic file locking para evitar race conditions
    $fp = fopen($cacheFile, 'c+');
    if (!$fp) return true; // Si no puede abrir, permitir (fail open)

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return true;
    }

    $content = stream_get_contents($fp);
    $data = $content ? (json_decode($content, true) ?: ['r' => []]) : ['r' => []];

    $now = time();
    $data['r'] = array_values(array_filter($data['r'], fn($t) => ($now - $t) < $windowSeconds));

    if (count($data['r']) >= $maxRequests) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    $data['r'][] = $now;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}
