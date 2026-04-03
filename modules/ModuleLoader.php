<?php
/**
 * ModuleLoader — Carga módulos dinámicamente según tipo de negocio.
 *
 * El sistema funciona como LEGO:
 * - Core (WhatsApp, IA, Conversación, Jobs) → siempre activo
 * - Módulos (tecnicos, estetica, automotriz) → se cargan según negocio
 *
 * Uso:
 *   $loader = ModuleLoader::getInstance();
 *   $servicios = $loader->getServicios();          // Servicios del negocio
 *   $flujo = $loader->getFlujoConversacion();       // Flujo JSON del negocio
 *   $prompt = $loader->getPromptVentas();           // Prompt de ventas por nicho
 *   $vistas = $loader->getVistasModulo();           // Vistas específicas del módulo
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/tenant.php';

class ModuleLoader {

    private static ?ModuleLoader $instance = null;
    private string $tipoNegocio;
    private array $modulosActivos = [];
    private ?object $flujo = null;
    private array $servicios = [];

    private function __construct() {
        $negocio = Tenant::negocio();
        $this->tipoNegocio = $negocio->tipo_negocio ?? 'tecnico';
        $this->cargarModulos();
        $this->cargarServicios();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resetear instancia (útil cuando cambia el tenant)
     */
    public static function reset() {
        self::$instance = null;
    }

    // ============================================================
    // GETTERS PRINCIPALES
    // ============================================================

    public function getTipoNegocio(): string {
        return $this->tipoNegocio;
    }

    /**
     * Obtener módulos activos del negocio
     */
    public function getModulosActivos(): array {
        return $this->modulosActivos;
    }

    /**
     * Verificar si un módulo está activo
     */
    public function tieneModulo(string $modulo): bool {
        return isset($this->modulosActivos[$modulo]) && $this->modulosActivos[$modulo]['activo'];
    }

    /**
     * Obtener config específica de un módulo
     */
    public function getConfigModulo(string $modulo): array {
        return $this->modulosActivos[$modulo]['config'] ?? [];
    }

    /**
     * Obtener servicios del negocio (dinámicos, no ENUM)
     */
    public function getServicios(): array {
        return $this->servicios;
    }

    /**
     * Obtener un servicio por slug
     */
    public function getServicio(string $slug): ?array {
        foreach ($this->servicios as $s) {
            if ($s['slug'] === $slug) return $s;
        }
        return null;
    }

    /**
     * Obtener texto de servicios para WhatsApp
     */
    public function getServiciosTexto(): string {
        if (empty($this->servicios)) {
            return "Contactanos para conocer nuestros servicios y precios.";
        }
        $moneda = Tenant::config('moneda_simbolo', '$');
        $lineas = [];
        foreach ($this->servicios as $i => $s) {
            $lineas[] = ($i + 1) . ". {$s['nombre']}: {$moneda}" . number_format($s['precio_base'], 0) . ($s['duracion_minutos'] ? " ({$s['duracion_minutos']} min)" : '');
        }
        return implode("\n", $lineas);
    }

    /**
     * Obtener flujo de conversación JSON del negocio
     */
    public function getFlujoConversacion(): ?array {
        if ($this->flujo === null) {
            $this->cargarFlujo();
        }
        return $this->flujo ? json_decode(
            is_string($this->flujo->pasos) ? $this->flujo->pasos : json_encode($this->flujo->pasos),
            true
        ) : null;
    }

    /**
     * Obtener prompt de ventas personalizado por tipo de negocio
     */
    public function getPromptVentas(): ?string {
        // 1. Buscar prompt custom para este negocio
        $sql = "SELECT system_prompt FROM prompts_ia
                WHERE negocio_id = ? AND tipo = 'ventas' AND activo = 1
                ORDER BY version DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        if ($result && $row = $result->fetch_object()) {
            return $row->system_prompt;
        }

        // 2. Buscar prompt por tipo de negocio (template)
        $sql = "SELECT system_prompt FROM prompts_ia
                WHERE tipo_negocio = ? AND tipo = 'ventas' AND activo = 1 AND es_default = 1
                ORDER BY version DESC LIMIT 1";
        $result = ejecutarConsulta($sql, 's', [$this->tipoNegocio]);
        if ($result && $row = $result->fetch_object()) {
            return $row->system_prompt;
        }

        return null; // Usar prompt por defecto del AIService
    }

    /**
     * Obtener vistas que el módulo agrega al menú
     */
    public function getVistasModulo(): array {
        $moduloPath = __DIR__ . '/' . $this->tipoNegocio . '/module.php';
        if (file_exists($moduloPath)) {
            $def = require $moduloPath;
            return $def['vistas'] ?? [];
        }

        // Defaults por tipo
        $vistas = [
            'tecnico' => [
                ['id' => 'soporte_diagnosticos', 'nombre' => 'Diagnosticos', 'icono' => 'stethoscope'],
                ['id' => 'soporte_tickets', 'nombre' => 'Tickets', 'icono' => 'ticket'],
            ],
            'estetica' => [
                ['id' => 'estetica_citas', 'nombre' => 'Citas', 'icono' => 'calendar'],
                ['id' => 'estetica_servicios', 'nombre' => 'Servicios', 'icono' => 'star'],
            ],
        ];

        return $vistas[$this->tipoNegocio] ?? $vistas['tecnico'];
    }

    // ============================================================
    // CARGA INTERNA
    // ============================================================

    private function cargarModulos() {
        $sql = "SELECT modulo, activo, config_json FROM modulos_negocio WHERE idnegocio = ?";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        if ($result) {
            while ($row = $result->fetch_object()) {
                $this->modulosActivos[$row->modulo] = [
                    'activo' => (bool)$row->activo,
                    'config' => json_decode($row->config_json ?: '{}', true)
                ];
            }
        }

        // Si no tiene módulos, activar base + tipo por defecto
        if (empty($this->modulosActivos)) {
            $this->modulosActivos = [
                'base' => ['activo' => true, 'config' => []],
                $this->tipoNegocio => ['activo' => true, 'config' => []]
            ];
        }
    }

    private function cargarServicios() {
        $sql = "SELECT * FROM servicios_negocio WHERE negocio_id = ? AND activo = 1 ORDER BY orden ASC";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->servicios[] = $row;
            }
        }

        // Fallback: servicios desde config (backward compat)
        if (empty($this->servicios)) {
            $moneda = Tenant::config('moneda_simbolo', '$');
            $this->servicios = [
                ['nombre' => 'Remoto', 'slug' => 'remoto', 'precio_base' => floatval(Tenant::config('costo_hora_remoto', 25000)), 'duracion_minutos' => 60],
                ['nombre' => 'En Sitio', 'slug' => 'en_sitio', 'precio_base' => floatval(Tenant::config('costo_hora_sitio', 40000)), 'duracion_minutos' => 60],
                ['nombre' => 'Taller', 'slug' => 'taller', 'precio_base' => floatval(Tenant::config('costo_hora_taller', 30000)), 'duracion_minutos' => 60],
            ];
        }
    }

    private function cargarFlujo() {
        // 1. Flujo personalizado del negocio
        $sql = "SELECT * FROM flujos_conversacion WHERE negocio_id = ? AND activo = 1 AND nombre = 'principal' LIMIT 1";
        $result = ejecutarConsulta($sql, 'i', [Tenant::id()]);
        $this->flujo = $result ? $result->fetch_object() : null;

        // 2. Flujo template por tipo de negocio
        if (!$this->flujo) {
            $sql = "SELECT * FROM flujos_conversacion WHERE tipo_negocio = ? AND activo = 1 AND nombre = 'principal' LIMIT 1";
            $result = ejecutarConsulta($sql, 's', [$this->tipoNegocio]);
            $this->flujo = $result ? $result->fetch_object() : null;
        }
    }
}
