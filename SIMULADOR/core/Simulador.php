<?php
/**
 * Simulador — Orquestador principal.
 *
 * Carga escenarios, crea clientes, ejecuta conversaciones, genera reporte.
 *
 * Uso:
 *   $sim = new Simulador('interno'); // o 'real'
 *   $sim->ejecutar('interesado', 10);
 *   $reporte = $sim->reporte();
 */

require_once __DIR__ . '/ClienteSimulado.php';
require_once __DIR__ . '/Escenario.php';
require_once __DIR__ . '/MotorConversacion.php';
require_once __DIR__ . '/Evaluador.php';

class Simulador {

    private string $modo;      // 'interno' o 'real'
    private Evaluador $evaluador;
    private array $logs = [];
    private $servicio;

    public function __construct(string $modo = 'interno') {
        $this->modo = $modo;
        $this->evaluador = new Evaluador();

        if ($modo === 'real') {
            require_once __DIR__ . '/../servicios/IntegracionRealService.php';
            $this->servicio = new IntegracionRealService();
        } else {
            require_once __DIR__ . '/../servicios/WhatsAppFakeService.php';
            $this->servicio = new WhatsAppFakeService();
        }
    }

    /**
     * Obtener el servicio de comunicación (para inyección de prompts)
     */
    public function getServicio() {
        return $this->servicio;
    }

    /**
     * Ejecutar N simulaciones de un escenario
     */
    public function ejecutar(string $nombreEscenario, int $cantidad = 10): self {
        $escenario = Escenario::cargar($nombreEscenario);

        $this->log("=== Simulando: {$escenario->nombre} x{$cantidad} (modo: {$this->modo}) ===");

        for ($i = 1; $i <= $cantidad; $i++) {
            $cliente = ClienteSimulado::crear($escenario->config_cliente);
            $motor = new MotorConversacion($cliente, $escenario, $this->servicio);

            $this->log("--- Sim #{$i}: {$cliente->nombre} ({$cliente->nivel_interes}) ---");

            $clienteFinal = $motor->ejecutar();

            $this->evaluador->agregarResultado(
                $clienteFinal->resumen(),
                $motor->getLog(),
                $motor->getAnalisisCalidad()
            );

            // Agregar logs del motor al log general
            foreach ($motor->getLog() as $line) {
                $this->logs[] = "  [$i] $line";
            }
        }

        return $this;
    }

    /**
     * Ejecutar TODOS los escenarios disponibles
     */
    public function ejecutarTodos(int $cantidadPorEscenario = 5): self {
        $escenarios = Escenario::listar();
        foreach ($escenarios as $nombre) {
            $this->ejecutar($nombre, $cantidadPorEscenario);
        }
        return $this;
    }

    /**
     * Obtener reporte completo
     */
    public function reporte(): array {
        return $this->evaluador->generarReporte();
    }

    /**
     * Guardar reporte en archivo JSON
     */
    public function guardarReporte(string $path = null): string {
        return $this->evaluador->guardarReporte($path);
    }

    /**
     * Guardar logs en archivo
     */
    public function guardarLogs(string $path = null): string {
        $path = $path ?: __DIR__ . '/../logs/simulaciones.log';
        $content = implode("\n", $this->logs);
        file_put_contents($path, date('Y-m-d H:i:s') . "\n" . $content . "\n\n", FILE_APPEND);
        return $path;
    }

    private function log(string $msg) {
        $this->logs[] = date('H:i:s') . " $msg";
    }

    public function getLogs(): array {
        return $this->logs;
    }
}
