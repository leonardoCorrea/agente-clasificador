<?php
/**
 * Servicio OCR
 * Llama al microservicio Python vía HTTP para procesar OCR con Vision Multi
 * Usa FastAPI microservice desplegado en Railway
 */

class OCRService
{
    private $db;
    private $apiKey;
    private $ocrServiceUrl;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->apiKey = OPENAI_API_KEY; // Definida en config.php
        $this->ocrServiceUrl = OCR_SERVICE_URL; // Definida en config.php
    }

    /**
     * Llamar al microservicio OCR vía HTTP
     * @param string $filePath Ruta al archivo a procesar
     * @param array|null $context Contexto opcional para corroboración
     * @param int $maxRetries Número máximo de reintentos
     * @return array Resultado del OCR
     * @throws Exception Si falla después de todos los reintentos
     */
    private function callOCRService($filePath, $context = null, $maxRetries = 3)
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            try {
                $ch = curl_init($this->ocrServiceUrl . '/api/ocr/process');

                $postFields = [
                    'file' => new CURLFile($filePath),
                    'api_key' => $this->apiKey
                ];

                if ($context !== null) {
                    $postFields['context'] = json_encode($context);
                }

                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postFields,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 300, // 5 minutos
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json'
                    ]
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    throw new Exception("Error de conexión con microservicio OCR: " . $curlError);
                }

                if ($httpCode !== 200) {
                    throw new Exception("Microservicio OCR retornó código HTTP $httpCode: " . substr($response, 0, 500));
                }

                $result = json_decode($response, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Respuesta inválida del microservicio OCR: " . json_last_error_msg());
                }

                return $result;

            } catch (Exception $e) {
                $lastError = $e;
                $attempt++;

                error_log("OCR Microservice - Intento $attempt de $maxRetries falló: " . $e->getMessage());

                if ($attempt < $maxRetries) {
                    // Exponential backoff: 2^attempt segundos
                    sleep(pow(2, $attempt));
                }
            }
        }

        throw new Exception("Microservicio OCR falló después de $maxRetries intentos. Último error: " . $lastError->getMessage());
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

            // Llamar al microservicio OCR
            $result = $this->callOCRService($filePath);

            // Verificar respuesta
            if (!$result || !isset($result['success'])) {
                throw new Exception('Respuesta inválida del microservicio OCR');
            }

            if (!$result['success']) {
                $errorMsg = $result['error'] ?? 'Error desconocido en OCR';
                throw new Exception($errorMsg);
            }

            // El resultado ya viene parseado del microservicio

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

            // Llamar al microservicio OCR con contexto para corroboración
            $result = $this->callOCRService($filePath, $context);

            // Verificar respuesta
            if (!$result || !isset($result['success'])) {
                throw new Exception('Respuesta inválida del microservicio OCR en corroboración');
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
