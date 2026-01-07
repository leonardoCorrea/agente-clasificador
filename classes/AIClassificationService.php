<?php
/**
 * Servicio de Clasificación IA
 * Ejecuta script Python directamente para clasificar con Vision Multi
 * NO usa Flask ni servicios HTTP
 */

class AIClassificationService
{
    private $db;
    private $pythonPath;
    private $scriptPath;
    private $apiKey;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->scriptPath = __DIR__ . '/../python-scripts/ai_classify.py';
        $this->apiKey = OPENAI_API_KEY;
        $this->pythonPath = PYTHON_PATH;
    }

    /**
     * Clasificar un solo ítem
     */
    public function classifySingleItem($itemId)
    {
        try {
            // Obtener ítem
            $item = $this->db->select(
                "SELECT * FROM items_factura WHERE id = ?",
                [$itemId]
            );

            if (empty($item)) {
                return ['success' => false, 'message' => 'Ítem no encontrado'];
            }

            $item = $item[0];
            $descripcion = $item['descripcion'];
            $facturaId = $item['factura_id'];

            // Obtener contexto de la factura
            $factura = $this->db->select(
                "SELECT proveedor, remitente_nombre, remitente_direccion, remitente_contacto, consignatario_nombre, pais_origen 
                 FROM facturas WHERE id = ?",
                [$facturaId]
            );
            $contexto = !empty($factura) ? $factura[0] : null;

            // Ejecutar clasificación con contexto
            $result = $this->executeClassification([$descripcion], $contexto);

            if (!$result['success']) {
                return $result;
            }

            $clasificacion = $result['clasificaciones'][0];

            // Guardar resultado
            $this->saveClassificationResult($itemId, $clasificacion);

            return [
                'success' => true,
                'clasificacion' => $clasificacion
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en clasificación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clasificar múltiples ítems
     */
    public function classifyBatch($itemIds)
    {
        try {
            if (empty($itemIds)) {
                return ['success' => false, 'message' => 'No hay ítems para clasificar'];
            }

            // Obtener descripciones
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $items = $this->db->select(
                "SELECT id, descripcion FROM items_factura WHERE id IN ($placeholders)",
                $itemIds
            );

            if (empty($items)) {
                return ['success' => false, 'message' => 'Ítems no encontrados'];
            }

            $descripciones = array_column($items, 'descripcion');

            // Obtener contexto de la factura (asumimos que todos los items son de la misma factura en un batch típico)
            $contexto = null;
            if (!empty($items)) {
                $firstItemId = $itemIds[0];
                $facturaInfo = $this->db->select(
                    "SELECT f.proveedor, f.remitente_nombre, f.remitente_direccion, f.remitente_contacto, f.consignatario_nombre, f.pais_origen 
                     FROM facturas f JOIN items_factura i ON f.id = i.factura_id WHERE i.id = ?",
                    [$firstItemId]
                );
                if (!empty($facturaInfo)) {
                    $contexto = $facturaInfo[0];
                }
            }

            // Ejecutar clasificación con contexto
            $result = $this->executeClassification($descripciones, $contexto);

            if (!$result['success']) {
                return $result;
            }

            // Guardar resultados
            foreach ($items as $index => $item) {
                if (isset($result['clasificaciones'][$index])) {
                    $this->saveClassificationResult(
                        $item['id'],
                        $result['clasificaciones'][$index]
                    );
                }
            }

            return [
                'success' => true,
                'clasificaciones' => $result['clasificaciones']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en clasificación por lotes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar clasificación manual
     */
    public function saveManualClassification($itemId, $codigo, $explicacion = 'Editado manualmente por el usuario')
    {
        try {
            $data = [
                'codigo_arancelario' => $codigo,
                'descripcion_codigo' => 'Clasificación manual',
                'explicacion' => $explicacion,
                'confianza' => 100, // Manual = 100% confidence
                'modelo' => 'manual_override',
                'alternativas' => []
            ];

            $this->saveClassificationResult($itemId, $data);
            return ['success' => true, 'message' => 'Clasificación actualizada manualmente'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al guardar clasificación manual: ' . $e->getMessage()];
        }
    }

    /**
     * Ejecutar script Python de clasificación
     */
    private function executeClassification($descripciones, $contexto = null)
    {
        try {
            // Convertir descripciones a JSON
            $jsonInput = json_encode($descripciones, JSON_UNESCAPED_UNICODE);
            $jsonInput = addslashes($jsonInput);

            // Convertir contexto a JSON si existe
            $jsonContext = "[]";
            if ($contexto) {
                $jsonContext = json_encode($contexto, JSON_UNESCAPED_UNICODE);
                $jsonContext = addslashes($jsonContext);
            }

            // Ejecutar script Python
            $command = sprintf(
                '"%s" "%s" "%s" "%s" "%s" 2>&1',
                $this->pythonPath,
                $this->scriptPath,
                $jsonInput,
                $this->apiKey,
                $jsonContext
            );

            $output = shell_exec($command);

            if ($output === null || $output === false) {
                throw new Exception("Error ejecutando script Python de clasificación. Salida vacía o fallo de shell_exec. Comando: $command");
            }

            // Parsear resultado
            $result = json_decode($output, true);

            if (!$result || !isset($result['success'])) {
                throw new Exception("Respuesta inválida del script Python (JSON esperado). Salida recibida: [" . $output . "]");
            }

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Guardar resultado de clasificación
     */
    private function saveClassificationResult($itemId, $clasificacion)
    {
        $data = [
            'item_factura_id' => $itemId, // Corregido según schema.sql
            'codigo_arancelario_sugerido' => $clasificacion['codigo_arancelario'],
            'descripcion_codigo' => $clasificacion['descripcion_codigo'],
            'explicacion' => $clasificacion['explicacion'],
            'confianza' => $clasificacion['confianza'],
            'modelo_ia' => $clasificacion['modelo'],
            'alternativas' => json_encode($clasificacion['alternativas'] ?? []),
            'fecha_clasificacion' => date('Y-m-d H:i:s')
        ];

        $this->db->insert('resultados_ia', $data);

        // Registrar en auditoría
        AuditLog::log(
            $_SESSION['user_id'] ?? null,
            'clasificacion_ia',
            'items_factura',
            $itemId,
            null,
            $data
        );
    }

    /**
     * Aprobar clasificación
     */
    public function approveClassification($itemId, $codigoFinal, $modificado = false)
    {
        try {
            $this->db->beginTransaction();

            // Obtener resultado IA
            $resultadoIA = $this->db->select(
                "SELECT * FROM resultados_ia WHERE item_factura_id = ? ORDER BY fecha_clasificacion DESC LIMIT 1",
                [$itemId]
            );

            if (empty($resultadoIA)) {
                throw new Exception('No hay clasificación IA para este ítem');
            }

            $resultadoIA = $resultadoIA[0];

            // Guardar clasificación final
            $finalData = [
                'item_factura_id' => $itemId, // Corregido según schema.sql
                'resultado_ia_id' => $resultadoIA['id'],
                'codigo_arancelario' => $codigoFinal, // Corregido según schema.sql
                'descripcion_codigo' => $resultadoIA['descripcion_codigo'], // Agregado campo faltante
                'fue_modificado' => $modificado ? 1 : 0,
                'usuario_aprobador_id' => $_SESSION['user_id'],
                'fecha_aprobacion' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('clasificacion_final', $finalData);

            // Auditoría
            AuditLog::log(
                $_SESSION['user_id'],
                'clasificacion_aprobada',
                'items_factura',
                $itemId,
                null,
                $finalData
            );

            $this->db->commit();

            // Actualizar estado de la factura automáticamente
            $this->updateInvoiceStateAfterApproval($itemId);

            return ['success' => true, 'message' => 'Clasificación aprobada'];

        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Actualizar el estado de la factura basándose en el progreso de aprobación
     */
    private function updateInvoiceStateAfterApproval($itemId)
    {
        // 1. Obtener factura_id del item
        $item = $this->db->queryOne("SELECT factura_id FROM items_factura WHERE id = ?", [$itemId]);
        if (!$item)
            return;
        $facturaId = $item['factura_id'];

        // 2. Contar items totales y aprobados
        $counts = $this->db->queryOne(
            "SELECT 
                (SELECT COUNT(*) FROM items_factura WHERE factura_id = ?) as total,
                (SELECT COUNT(*) FROM clasificacion_final cf 
                 JOIN items_factura i ON cf.item_factura_id = i.id 
                 WHERE i.factura_id = ?) as aprobados",
            [$facturaId, $facturaId]
        );

        if (!$counts)
            return;

        $total = (int) $counts['total'];
        $aprobados = (int) $counts['aprobados'];

        $nuevoEstado = null;
        if ($aprobados === $total && $total > 0) {
            $nuevoEstado = 'aprobado';
        } elseif ($aprobados > 0) {
            $nuevoEstado = 'clasificado';
        }

        if ($nuevoEstado) {
            $this->db->execute("UPDATE facturas SET estado = ? WHERE id = ?", [$nuevoEstado, $facturaId]);

            AuditLog::log(
                $_SESSION['user_id'] ?? null,
                'cambio_estado_automatico',
                'facturas',
                $facturaId,
                null,
                ['estado' => $nuevoEstado, 'motivo' => 'Aprobación de líneas']
            );
        }
    }

    /**
     * Obtener ítems pendientes de clasificación
     */
    public function getPendingItems($facturaId = null)
    {
        $sql = "SELECT i.*, f.numero_factura 
                FROM items_factura i
                JOIN facturas f ON i.factura_id = f.id
                LEFT JOIN clasificacion_final cf ON i.id = cf.item_factura_id
                WHERE cf.id IS NULL";

        $params = [];

        if ($facturaId) {
            $sql .= " AND i.factura_id = ?";
            $params[] = $facturaId;
        }

        $sql .= " ORDER BY i.id ASC";

        return $this->db->select($sql, $params);
    }
}
