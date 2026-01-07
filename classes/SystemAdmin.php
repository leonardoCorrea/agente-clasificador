<?php
/**
 * Clase SystemAdmin
 * Tareas administrativas y mantenimiento del sistema
 */

class SystemAdmin
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ejecutar reseteo de fábrica
     * Elimina todos los datos transaccionales y archivos
     */
    public function factoryReset()
    {
        // 1. Limpiar Base de Datos
        try {
            // Desactivar checks de FK para poder truncar tablas libremente
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 0");

            // Tablas a truncar (Orden no importa con FK=0, pero por claridad)
            $tables = [
                'auditoria',
                'clasificacion_final',
                'resultados_ia',
                'resultados_ocr',
                'items_factura',
                'facturas'
            ];

            foreach ($tables as $table) {
                $this->db->execute("TRUNCATE TABLE $table");
            }

            // Reactivar checks de FK
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");

            // Loguear esta acción (es lo único que quedará en auditoria)
            AuditLog::log($_SESSION['user_id'] ?? null, 'system_reset', 'sistema', null, null, ['action' => 'factory_reset']);

        } catch (Exception $e) {
            // Asegurar que FK checks vuelvan a 1 incluso si falla
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");
            return ['success' => false, 'message' => 'Error al limpiar base de datos: ' . $e->getMessage()];
        }

        // 2. Limpiar Archivos Físicos
        $cleanFiles = $this->cleanUploadsDirectory();

        if (!$cleanFiles['success']) {
            return ['success' => true, 'message' => 'DB limpia, pero error en archivos: ' . $cleanFiles['message']];
        }

        return ['success' => true, 'message' => 'Sistema reseteado correctamente. Base de datos y archivos limpios.'];
    }

    /**
     * Limpiar directorio de subidas
     */
    private function cleanUploadsDirectory()
    {
        // UPLOAD_PATH debe estar definida en config.php
        if (!defined('UPLOAD_PATH')) {
            return ['success' => false, 'message' => 'Constante UPLOAD_PATH no definida'];
        }

        $dir = UPLOAD_PATH;

        if (!is_dir($dir)) {
            return ['success' => true, 'message' => 'Directorio de uploads no existe'];
        }

        $files = array_diff(scandir($dir), array('.', '..', '.gitkeep', 'index.php'));
        $errors = 0;

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                if (!unlink($path)) {
                    $errors++;
                }
            }
        }

        if ($errors > 0) {
            return ['success' => false, 'message' => "No se pudieron eliminar $errors archivos"];
        }

        return ['success' => true];
    }
}
