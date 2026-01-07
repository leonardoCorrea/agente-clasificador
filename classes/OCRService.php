<?php
/**
 * Servicio OCR
 * Ejecuta script Python directamente para procesar OCR con Vision Multi
 * NO usa Flask ni servicios HTTP
 */

class OCRService
{
    private $db;
    private $pythonPath;
    private $scriptPath;
    private $apiKey;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->scriptPath = __DIR__ . '/../python-scripts/ocr_process.py';
        $this->apiKey = OPENAI_API_KEY; // Definida en config.php
        $this->pythonPath = PYTHON_PATH;
    }

    /**
     * Procesar factura con OCR
     */
    public function processInvoice($facturaId)
    {
        set_time_limit(600); // Aumentar a 10 minutos para procesos de multi-factura
        ignore_user_abort(true); // Continuar procesando aunque el cliente desconecte

        try {
            // Obtener información de la factura
            $facturaData = $this->db->select(
                "SELECT * FROM facturas WHERE id = ?",
                [$facturaId]
            );

            if (empty($facturaData)) {
                return ['success' => false, 'message' => 'Factura no encontrada'];
            }

            $factura = $facturaData[0];
            $filePath = $factura['ruta_archivo'];

            // Verificar que el archivo existe
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => 'Archivo no encontrado'];
            }

            // Actualizar estado a procesando
            $this->db->update('facturas', ['estado' => 'procesando'], ['id' => $facturaId]);

            // Ejecutar script Python con captura de stderr
            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            $command = sprintf(
                '"%s" "%s" "%s" "%s"',
                $this->pythonPath,
                $this->scriptPath,
                $filePath,
                $this->apiKey
            );

            $process = proc_open($command, $descriptorspec, $pipes);

            if (!is_resource($process)) {
                throw new Exception("No se pudo iniciar el proceso de OCR. Comando: $command");
            }

            // Leer stdout y stderr
            $output = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            error_log("OCR Output (Raw): " . substr($output, 0, 500)); // Log first 500 chars

            if (!empty($stderr)) {
                error_log("OCR Stderr: " . $stderr);
            }

            if ($returnCode !== 0) {
                throw new Exception("El script de OCR terminó con código de error $returnCode. Error: " . ($stderr ?: 'Sin detalles'));
            }

            if (empty($output)) {
                throw new Exception("El script de OCR no devolvió ninguna salida. Stderr: " . ($stderr ?: 'Sin detalles'));
            }

            // Parsear resultado JSON
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonStr = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                $result = json_decode($jsonStr, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $jsonError = json_last_error_msg();
                    error_log("JSON Parse Error: " . $jsonError);
                    error_log("Invalid JSON String (first 1000 chars): " . substr($jsonStr, 0, 1000));

                    // Guardar JSON problemático en archivo para debugging
                    $debugFile = __DIR__ . '/../logs/ocr_json_error_' . $facturaId . '_' . time() . '.txt';
                    @file_put_contents($debugFile, "JSON Error: " . $jsonError . "\n\nFull JSON:\n" . $jsonStr . "\n\nStderr:\n" . $stderr);

                    $errorMsg = "Error parseando JSON: " . $jsonError;
                    $errorMsg .= "\n\nPrimeros 500 caracteres del JSON:\n" . substr($jsonStr, 0, 500);

                    if (!empty($stderr)) {
                        $errorMsg .= "\n\nStderr:\n" . $stderr;
                    }

                    $errorMsg .= "\n\nJSON completo guardado en: " . basename($debugFile);

                    throw new Exception($errorMsg);
                }
            } else {
                error_log("No JSON found in output. Full output: " . $output);
                $errorMsg = "Error de respuesta: No se encontró un objeto JSON válido.";
                $errorMsg .= "\n\nSalida (primeros 500 chars):\n" . substr($output, 0, 500);
                if (!empty($stderr)) {
                    $errorMsg .= "\n\nStderr:\n" . $stderr;
                }
                throw new Exception($errorMsg);
            }

            if (!$result || !isset($result['success'])) {
                $errorMsg = 'Respuesta inválida o truncada del script Python.';
                if (!empty($stderr)) {
                    $errorMsg .= "\n\nStderr:\n" . $stderr;
                }
                throw new Exception($errorMsg);
            }

            if (!$result['success']) {
                $errorMsg = $result['error'] ?? 'Error desconocido en OCR';
                if (!empty($stderr)) {
                    $errorMsg .= "\n\nDetalles técnicos:\n" . $stderr;
                }
                throw new Exception($errorMsg);
            }

            $facturasDetectadas = $result['facturas'] ?? [];
            if (empty($facturasDetectadas)) {
                throw new Exception('No se detectaron facturas en el documento.');
            }

            $count = 0;
            foreach ($facturasDetectadas as $index => $dataFactura) {
                $currentFacturaId = $facturaId;

                // Si es la segunda factura o posterior, crear un nuevo registro
                if ($index > 0) {
                    $sqlNew = "INSERT INTO facturas (usuario_id, archivo_original, ruta_archivo, tipo_archivo, tamano_archivo, estado, observaciones) 
                              VALUES (?, ?, ?, ?, ?, 'ocr_completado', ?)";
                    $this->db->execute($sqlNew, [
                        $factura['usuario_id'],
                        $factura['archivo_original'],
                        $factura['ruta_archivo'],
                        $factura['tipo_archivo'],
                        $factura['tamano_archivo'],
                        'Factura separada automáticamente del archivo original'
                    ]);
                    $currentFacturaId = $this->db->lastInsertId();
                }

                // Guardar resultados OCR para esta factura específica
                $ocrData = [
                    'factura_id' => $currentFacturaId,
                    'texto_extraido' => $dataFactura['texto_completo'] ?? '',
                    'datos_estructurados' => json_encode($dataFactura['datos_estructurados'] ?? []),
                    'confianza_promedio' => 100, // Nombre corregido según schema.sql
                    'metodo_ocr' => $result['metodo'] ?? 'vision-multi',
                    'fecha_procesamiento' => date('Y-m-d H:i:s')
                ];

                $this->db->insert('resultados_ocr', $ocrData);

                // Actualizar info básica de la factura con lo extraído
                $datosE = $dataFactura['datos_estructurados'] ?? [];
                $totales = $datosE['totales'] ?? [];
                $remitente = $datosE['remitente'] ?? [];
                $consignatario = $datosE['consignatario'] ?? [];

                $this->db->update('facturas', [
                    'numero_factura' => $datosE['numero_factura'] ?? null,
                    'proveedor' => $datosE['proveedor'] ?? null,
                    'fecha_factura' => $datosE['fecha'] ?? null,
                    'moneda' => $datosE['moneda'] ?? 'USD',

                    // Remitente
                    'remitente_nombre' => $remitente['nombre'] ?? null,
                    'remitente_direccion' => $remitente['direccion'] ?? null,
                    'remitente_contacto' => $remitente['contacto'] ?? null,
                    'remitente_telefono' => $remitente['telefono'] ?? null,

                    // Consignatario
                    'consignatario_nombre' => $consignatario['nombre'] ?? null,
                    'consignatario_direccion' => $consignatario['direccion'] ?? null,
                    'consignatario_contacto' => $consignatario['contacto'] ?? null,
                    'pais_consignatario' => $consignatario['pais'] ?? null,

                    'pais_origen' => $datosE['pais_origen'] ?? null,

                    // Totales
                    'subtotal' => $this->cleanDecimal($totales['subtotal'] ?? 0),
                    'descuento' => $this->cleanDecimal($totales['descuento'] ?? 0),
                    'impuesto_calculado' => $this->cleanDecimal($totales['impuesto_monto'] ?? 0),
                    'impuesto_porcentaje' => $this->cleanDecimal($totales['impuesto_porcentaje'] ?? 0),
                    'total_final' => $this->cleanDecimal($totales['total_final'] ?? 0),
                    'total_factura' => $this->cleanDecimal($totales['total_final'] ?? 0), // Keeping backward compatibility

                    'estado' => 'ocr_completado'
                ], ['id' => $currentFacturaId]);

                // GUARDAR ÍTEMS DE FACTURA (NUEVO)
                if (!empty($datosE['items'])) {
                    $invoiceItem = new InvoiceItem();
                    foreach ($datosE['items'] as $itemIdx => $item) {
                        $itemData = [
                            'numero_linea' => $item['numero_linea'] ?? ($itemIdx + 1),
                            'numero_serie_parte' => $item['numero_serie_parte'] ?? null,
                            'descripcion' => $item['descripcion'] ?? 'Sin descripción',
                            'caracteristicas' => $item['caracteristicas'] ?? null,
                            'datos_importantes' => $item['datos_importantes'] ?? null,
                            'cantidad' => $this->cleanDecimal($item['cantidad'] ?? 0),
                            'unidad_medida' => $item['unidad_medida'] ?? null,
                            'precio_unitario' => $this->cleanDecimal($item['precio_unitario'] ?? 0),
                            'precio_total' => $this->cleanDecimal($item['precio_total'] ?? 0),
                            'subtotal' => $this->cleanDecimal($item['precio_total'] ?? 0) // Backward compatibility
                        ];
                        $invoiceItem->create($currentFacturaId, $itemData);
                    }
                }

                // Registrar en auditoría
                AuditLog::log(
                    $_SESSION['user_id'] ?? null,
                    'ocr_procesado',
                    'facturas',
                    $currentFacturaId,
                    null,
                    $ocrData
                );

                $count++;
            }

            return [
                'success' => true,
                'message' => "OCR procesado exitosamente. Se detectaron $count factura(s).",
                'count' => $count
            ];

        } catch (Exception $e) {
            // Actualizar estado a error para que no se quede en "procesando"
            if (isset($facturaId)) {
                $this->db->update('facturas', [
                    'estado' => 'error',
                    'observaciones' => 'Error OCR: ' . $e->getMessage()
                ], ['id' => $facturaId]);
            }

            // Incluir stderr si está disponible
            $errorDetails = $e->getMessage();
            if (isset($stderr) && !empty($stderr)) {
                $errorDetails .= "\n\nStderr Output:\n" . $stderr;
            }

            // Incluir stack trace para debugging
            $errorDetails .= "\n\nStack Trace:\n" . $e->getTraceAsString();

            return [
                'success' => false,
                'message' => 'Error en OCR: ' . $e->getMessage(),
                'error_details' => $errorDetails,
                'stderr' => $stderr ?? ''
            ];
        }
    }

    /**
     * Obtener resultados OCR de una factura
     */
    public function getOCRResults($facturaId)
    {
        $results = $this->db->select(
            "SELECT * FROM resultados_ocr WHERE factura_id = ? ORDER BY fecha_procesamiento DESC LIMIT 1",
            [$facturaId]
        );

        if (!empty($results)) {
            $results[0]['datos_estructurados'] = json_decode($results[0]['datos_estructurados'], true);
            return $results[0];
        }

        return null;
    }
    /**
     * Corroborar datos de factura con IA Vision
     */
    public function corroborateInvoice($facturaId)
    {
        set_time_limit(600); // Aumentar a 10 minutos
        ignore_user_abort(true);
        try {
            // Obtener información ACTUA de la factura (incluso si fue editada)
            $facturaData = $this->db->select(
                "SELECT * FROM facturas WHERE id = ?",
                [$facturaId]
            );

            if (empty($facturaData)) {
                return ['success' => false, 'message' => 'Factura no encontrada'];
            }

            $factura = $facturaData[0];
            $filePath = $factura['ruta_archivo'];

            // Obtener ITEMS actuales para el contexto
            $items = $this->db->select(
                "SELECT descripcion, cantidad, precio_unitario, subtotal FROM items_factura WHERE factura_id = ?",
                [$facturaId]
            );

            // Construir contexto enriquecido
            $context = [
                'numero_factura' => $factura['numero_factura'],
                'proveedor' => $factura['proveedor'],
                'fecha' => $factura['fecha_factura'],
                'total' => $factura['total_factura'],
                'moneda' => $factura['moneda'],
                'items' => $items
            ];

            // Actualizar estado
            $this->db->update('facturas', ['estado' => 'procesando'], ['id' => $facturaId]);

            // Ejecutar script Python con contexto (corroboración)
            $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE);
            $jsonContext = addslashes($jsonContext);

            $command = sprintf(
                '"%s" "%s" "%s" "%s" "%s" 2>&1',
                $this->pythonPath,
                $this->scriptPath,
                $filePath,
                $this->apiKey,
                $jsonContext
            );

            $output = shell_exec($command);

            if ($output === null) {
                throw new Exception('Error ejecutando script Python para corroboración');
            }

            // Limpiar salida para encontrar solo el JSON
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonStr = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
                $result = json_decode($jsonStr, true);
            } else {
                throw new Exception("Error en corroboración: No se encontró respuesta JSON válida.");
            }

            if (!$result || !isset($result['success'])) {
                throw new Exception('Respuesta inválida o truncada en corroboración.');
            }

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Error desconocido en corroboración');
            }

            // En corroboración, tomamos la factura que coincida o la primera del array
            $facturasDetectadas = $result['facturas'] ?? [];
            if (empty($facturasDetectadas)) {
                throw new Exception('No se detectaron facturas en la corroboración.');
            }

            $dataFactura = $facturasDetectadas[0];

            // Guardar resultados OCR históricos
            $ocrData = [
                'factura_id' => $facturaId,
                'texto_extraido' => $dataFactura['texto_completo'] ?? '',
                'datos_estructurados' => json_encode($dataFactura['datos_estructurados'] ?? []),
                'confianza_promedio' => 100, // Nombre corregido según schema.sql
                'metodo_ocr' => $result['metodo'] ?? 'vision-verified',
                'fecha_procesamiento' => date('Y-m-d H:i:s')
            ];

            $this->db->insert('resultados_ocr', $ocrData);

            // Actualizar info básica en la tabla maestra
            $datosE = $dataFactura['datos_estructurados'] ?? [];
            $this->db->update('facturas', [
                'numero_factura' => $datosE['numero_factura'] ?? $factura['numero_factura'],
                'proveedor' => $datosE['proveedor'] ?? $factura['proveedor'],
                'total_factura' => $datosE['total'] ?? $factura['total_factura'],
                'moneda' => $datosE['moneda'] ?? $factura['moneda'],
                'estado' => 'ocr_completado',
                'observaciones' => 'Datos corroborados con IA Vision (Auditoría completa)'
            ], ['id' => $facturaId]);

            // IMPORTANTE: Si los ítems cambiaron significativamente, el usuario los revisará en auto-digitize.php
            // Pero guardamos el registro en auditoría
            AuditLog::log($_SESSION['user_id'] ?? null, 'corroboracion_vision', 'facturas', $facturaId, $context, $dataFactura);

            return [
                'success' => true,
                'message' => 'Datos corroborados exitosamente con IA Vision',
                'resultado' => $dataFactura
            ];

        } catch (Exception $e) {
            $this->db->update('facturas', [
                'estado' => 'error',
                'observaciones' => 'Error Corroboración: ' . $e->getMessage()
            ], ['id' => $facturaId]);

            return [
                'success' => false,
                'message' => 'Error en corroboración: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Limpiar valores decimales para la base de datos
     */
    private function cleanDecimal($value)
    {
        if (empty($value))
            return 0;
        // Eliminar comas y cualquier carácter no numérico excepto el punto
        $clean = preg_replace('/[^-0-9.]/', '', str_replace(',', '', $value));
        return (float) $clean;
    }
}
