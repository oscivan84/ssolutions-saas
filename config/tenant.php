<?php
/**
 * TenantContext: Contexto Multi-Tenant centralizado
 *
 * Resuelve el negocio_id activo y lo inyecta en toda la aplicacion.
 * Todos los modelos y servicios usan Tenant::id() para filtrar queries.
 *
 * Estrategia de resolucion (en orden):
 * 1. Session (panel admin: usuario logueado → negocio asignado)
 * 2. API Key (agente Python / webhooks: api_key → negocio)
 * 3. Header X-Negocio-Id (llamadas internas)
 * 4. Default: primer negocio activo (fallback para desarrollo)
 */

require_once __DIR__ . '/database.php';

class Tenant {

    private static $negocioId = null;
    private static $negocio = null;

    /**
     * Obtener el negocio_id activo
     */
    public static function id() {
        if (self::$negocioId === null) {
            self::resolver();
        }
        if (self::$negocioId === 0 || self::$negocioId === null) {
            error_log("TENANT CRITICAL: No se pudo resolver negocio_id");
            return 0;
        }
        return self::$negocioId;
    }

    /**
     * Obtener datos completos del negocio activo
     */
    public static function negocio() {
        if (self::$negocio === null) {
            self::resolver();
        }
        return self::$negocio;
    }

    /**
     * Forzar un negocio_id (para contextos API/webhook).
     * RESET COMPLETO: limpia todos los singletons dependientes.
     */
    public static function setId($negocioId) {
        self::$negocioId = intval($negocioId);
        self::$negocio = null;
        self::$configCache = [];

        // Reset ModuleLoader para evitar datos stale de otro tenant
        if (class_exists('ModuleLoader', false)) {
            ModuleLoader::reset();
        }
    }

    // Cache de config por request (evita queries repetidas)
    private static array $configCache = [];

    /**
     * Verificar si el negocio tiene una feature habilitada segun su plan
     */
    public static function tieneFeature($feature) {
        $negocio = self::negocio();
        if (!$negocio) return false;

        switch ($feature) {
            case 'whatsapp': return (bool)$negocio->whatsapp_habilitado;
            case 'ia': return (bool)$negocio->ia_habilitada;
            case 'diagnosticos': return self::verificarPlanFeature('diagnosticos_habilitados');
            case 'automatizacion': return self::verificarPlanFeature('automatizacion_habilitada');
            default: return false;
        }
    }

    /**
     * Verificar limite de tickets del plan
     */
    public static function puedeCrearTicket() {
        $negocio = self::negocio();
        if (!$negocio) return false;
        if ($negocio->estado !== 'activo') return false;

        // Contar tickets del mes actual
        $sql = "SELECT COUNT(*) AS total FROM tickets
                WHERE negocio_id = ? AND fecha_creacion >= DATE_FORMAT(NOW(), '%Y-%m-01')";
        $result = ejecutarConsulta($sql, 'i', [self::id()]);
        $row = $result ? $result->fetch_object() : null;
        $total = $row ? intval($row->total) : 0;

        return $total < $negocio->max_tickets_mes;
    }

    /**
     * Obtener configuracion del negocio (con cache por request)
     */
    public static function config($clave, $default = null) {
        $cacheKey = self::id() . ':' . $clave;
        if (isset(self::$configCache[$cacheKey])) {
            return self::$configCache[$cacheKey];
        }

        $sql = "SELECT valor FROM configuracion_soporte WHERE clave = ? AND negocio_id = ? LIMIT 1";
        $result = ejecutarConsulta($sql, 'si', [$clave, self::id()]);
        if ($result && $row = $result->fetch_object()) {
            self::$configCache[$cacheKey] = $row->valor;
            return $row->valor;
        }
        return $default;
    }

    /**
     * Obtener el rol del usuario actual en el negocio
     */
    public static function rolUsuario($idusuario = null) {
        if ($idusuario === null) {
            $idusuario = $_SESSION['idusuario'] ?? null;
        }
        if (!$idusuario) return null;

        $sql = "SELECT rol FROM usuarios_negocio WHERE idusuario = ? AND idnegocio = ? AND activo = 1";
        $result = ejecutarConsulta($sql, 'ii', [$idusuario, self::id()]);
        if ($result && $row = $result->fetch_object()) {
            return $row->rol;
        }
        return null;
    }

    /**
     * Requerir autenticación + rol mínimo. Aborta con 403 si falla.
     * Usar al inicio de AJAX handlers que modifican datos.
     */
    public static function requireAuth($rolMinimo = 'tecnico') {
        if (!self::id() || !self::negocio()) {
            http_response_code(403);
            echo json_encode(['error' => 'Negocio no resuelto']);
            exit;
        }
        if (!self::tieneRol($rolMinimo)) {
            // Solo permitir bypass en modo dev EXPLICITO (variable de entorno)
            $devMode = getenv('APP_ENV') === 'development' || getenv('SS_DEV_MODE') === 'true';
            if ($devMode) {
                error_log("AUTH BYPASS (dev mode): rol=$rolMinimo negocio=" . self::id());
                return; // Permitir en desarrollo explícito
            }

            http_response_code(403);
            echo json_encode(['error' => 'Permisos insuficientes']);
            exit;
        }
    }

    /**
     * Verificar que el usuario tiene permiso (rol minimo)
     */
    public static function tieneRol($rolMinimo, $idusuario = null) {
        $rol = self::rolUsuario($idusuario);
        if (!$rol) return false;

        $jerarquia = ['tecnico' => 1, 'admin' => 2, 'dueno' => 3];
        return ($jerarquia[$rol] ?? 0) >= ($jerarquia[$rolMinimo] ?? 0);
    }

    // ============================================================
    // RESOLUCION INTERNA
    // ============================================================

    private static function resolver() {
        // 1. Session (panel admin)
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['negocio_id'])) {
            self::$negocioId = intval($_SESSION['negocio_id']);
            self::cargarNegocio();
            return;
        }

        // 2. API Key del agente → buscar negocio
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($apiKey) {
            $sql = "SELECT cs.negocio_id FROM configuracion_soporte cs
                    WHERE cs.clave = 'api_key_agente' AND cs.valor = ? LIMIT 1";
            $result = ejecutarConsulta($sql, 's', [$apiKey]);
            if ($result && $row = $result->fetch_object()) {
                self::$negocioId = intval($row->negocio_id);
                self::cargarNegocio();
                return;
            }
        }

        // 3. Header explicito
        $headerNegocio = $_SERVER['HTTP_X_NEGOCIO_ID'] ?? null;
        if ($headerNegocio) {
            self::$negocioId = intval($headerNegocio);
            self::cargarNegocio();
            return;
        }

        // 4. Fallback: primer negocio activo
        $sql = "SELECT idnegocio FROM negocios WHERE estado = 'activo' ORDER BY idnegocio ASC LIMIT 1";
        $result = ejecutarConsulta($sql, '', []);
        if ($result && $row = $result->fetch_object()) {
            self::$negocioId = intval($row->idnegocio);
            self::cargarNegocio();
            return;
        }

        // Sin negocio encontrado
        self::$negocioId = 0;
    }

    private static function cargarNegocio() {
        if (!self::$negocioId) return;
        $sql = "SELECT * FROM negocios WHERE idnegocio = ? AND estado = 'activo'";
        $result = ejecutarConsulta($sql, 'i', [self::$negocioId]);
        self::$negocio = $result ? $result->fetch_object() : null;
    }

    private static function verificarPlanFeature($campo) {
        $negocio = self::negocio();
        if (!$negocio) return false;

        $sql = "SELECT $campo FROM planes_saas WHERE codigo = ?";
        $result = ejecutarConsulta($sql, 's', [$negocio->plan]);
        if ($result && $row = $result->fetch_assoc()) {
            return (bool)$row[$campo];
        }
        return false;
    }
}
