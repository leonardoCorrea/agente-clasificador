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
     * @param string|null $filePath Ruta al archivo (null si se usa sessionId)
     * @param array|null $context Contexto opcional para corroboración
     * @param int|null $pageNumber Página específica a procesar (X de N)
     * @param string|null $sessionId ID de sesión para no re-subir el archivo
     * @param int $maxRetries Número máximo de reintentos
     * @return array Resultado del OCR
     * @throws Exception Si falla después de todos los reintentos
     */
    private function callOCRService($filePath, $context = null, $pageNumber = null, $sessionId = null, $maxRetries = 3)
    {
        $attempt = 0;
        $lastError = null;

        // Log para debugging
        error_log("OCR Microservice - URL: " . $this->ocrServiceUrl);
        error_log("OCR Microservice - File: " . $filePath);

        while ($attempt < $maxRetries) {
            try {
                $url = $this->ocrServiceUrl . '/api/ocr/process';
                $startTime = microtime(true);
                error_log("OCR Microservice - Calling: " . $url);

                $ch = curl_init($url);

                $postFields = [
                    'api_key' => $this->apiKey
                ];

                if ($sessionId !== null) {
                    $postFields['session_id'] = $sessionId;
                } else if ($filePath !== null) {
                    $postFields['file'] = new CURLFile($filePath);
                }

                if ($pageNumber !== null) {
                    $postFields['page_number'] = $pageNumber;
                }

                if ($context !== null) {
                    $postFields['context'] = json_encode($context);
                }

                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $postFields,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 600, // 10 minutos (Aumentado para facturas largas)
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json'
                    ]
                ]);

                $response = curl_exec($ch);
                $duration = microtime(true) - $startTime;

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                // Log detallado de la respuesta
                error_log("OCR Microservice - Request finished in " . round($duration, 2) . " seconds. HTTP Code: " . $httpCode);
                error_log("OCR Microservice - Response (first 500 chars): " . substr($response, 0, 500));

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

                error_log("OCR Microservice - Success!");
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
     * Preparar una sesión de OCR en el servidor (sube el archivo una sola vez)
     */
    private function prepareSession($filePath)
    {
        error_log("OCR Service - Preparing session for: " . $filePath);
        $url = $this->ocrServiceUrl . '/api/ocr/prepare';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($filePath)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("OCR Prepare Error - HTTP $httpCode: $response");
            return ['success' => false, 'pages' => 1];
        }

        return json_decode($response, true);
    }

    /**
     * Procesar factura con OCR
     */
    public function processInvoice($facturaId)
    {
        set_time_limit(900); // 15 minutos para procesos de alta calidad
        // Optimizaciones extremas para evitar timeouts del servidor (Litespeed/Apache)
        @set_time_limit(0);
        @ini_set('max_execution_time', 0);
        @header('X-Accel-Buffering: no');
        ignore_user_abort(true);

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

            // --- ESTRATEGIA: Vision AI Local primero, luego Fallback Railway ---
            $result = null;
            $errorDetails = "";

            // Preparar contexto si hay items esperados (Instrucción explícita para la IA)
            $localContext = null;
            if (!empty($factura['items_esperados']) && $factura['items_esperados'] > 0) {
                $localContext = ['items_esperados' => (int) $factura['items_esperados']];
                error_log("OCRService: Enviando instrucción de búsqueda para {$localContext['items_esperados']} ítems.");
            }

            try {
                // 1. Iniciar Sesión (SUBE EL ARCHIVO UNA SOLA VEZ)
                error_log("OCRService: Iniciando sesión de procesamiento...");
                $this->db->update('facturas', ['observaciones' => 'Preparando documento para análisis...'], ['id' => $facturaId]);

                $sessionInfo = $this->prepareSession($filePath);

                if (!$sessionInfo['success']) {
                    throw new Exception("No se pudo preparar la sesión de OCR: " . ($sessionInfo['detail'] ?? 'Error desconocido'));
                }

                $sessionId = $sessionInfo['session_id'];
                $totalPages = $sessionInfo['pages'] ?? 1;
                error_log("OCRService: Sesión iniciada: $sessionId. Total páginas: $totalPages");

                $allResults = [];
                $errorDetails = "";

                // Procesar cada página usando el sessionId
                for ($p = 1; $p <= $totalPages; $p++) {
                    $statusMsg = "Analizando página $p de $totalPages...";
                    error_log("OCRService: $statusMsg");
                    $this->db->update('facturas', ['observaciones' => $statusMsg], ['id' => $facturaId]);

                    try {
                        // Llamada eficiente: pasamos sessionId en lugar del archivo físico
                        $pageResult = $this->callOCRService(null, $localContext, $p, $sessionId);

                        if ($pageResult['success']) {
                            $allResults[] = $pageResult;
                        } else {
                            error_log("OCRService: Error en página $p: " . ($pageResult['error'] ?? 'Desconocido'));
                        }
                    } catch (Exception $pe) {
                        error_log("OCRService: Excepción en página $p: " . $pe->getMessage());
                    }
                }

                if (empty($allResults)) {
                    throw new Exception("No se pudo procesar ninguna página correctamente.");
                }

                // UNIFICACIÓN DE RESULTADOS
                $result = $this->mergeOCRResults($allResults);

            } catch (Exception $e) {
                error_log("OCRService: Falló procesamiento por páginas. Intentando fallback tradicional...");
                $errorDetails .= "Multi-page Error: " . $e->getMessage() . "\n";

                try {
                    // Fallback a procesamiento completo (antiguo)
                    $result = $this->callOCRService($filePath);
                } catch (Exception $e2) {
                    error_log("OCRService: Fallo total. Error: " . $e2->getMessage());
                    throw new Exception("Fallo total en OCR. Multi-page: " . $e->getMessage() . " | Fallback: " . $e2->getMessage());
                }
            }

            // Verificar respuesta final
            if (!$result || !isset($result['success'])) {
                throw new Exception('Respuesta inválida del motor de OCR (Fallo en cascada)');
            }

            if (!$result['success']) {
                $errorMsg = $result['error'] ?? 'Error desconocido en OCR';
                throw new Exception($errorMsg);
            }

            // El resultado ya viene parseado del microservicio

            $facturasDetectadas = $result['facturas'] ?? [];
            if (empty($facturasDetectadas)) {
                // Fallback: si no hay array 'facturas', intentar ver si result es la factura
                if (isset($result['datos_estructurados']) || isset($result['numero_factura'])) {
                    $facturasDetectadas = [$result];
                } else {
                    throw new Exception('No se detectaron facturas en el documento.');
                }
            }

            $count = 0;
            foreach ($facturasDetectadas as $index => $rawFactura) {
                $currentFacturaId = $facturaId;

                // Extraer datos estructurados de forma segura
                // Si la factura ya viene con 'datos_estructurados', perfecto. 
                // Si no, asumimos que rawFactura contiene los datos directamente.
                $datosE = $rawFactura['datos_estructurados'] ?? $rawFactura;
                $textoCompleto = $rawFactura['texto_completo'] ?? ($rawFactura['texto_extraido'] ?? '');

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
                    'texto_extraido' => $textoCompleto,
                    'datos_estructurados' => json_encode($datosE),
                    'confianza_promedio' => 100,
                    'metodo_ocr' => $result['metodo'] ?? 'vision-multi',
                    'fecha_procesamiento' => date('Y-m-d H:i:s')
                ];

                $this->db->insert('resultados_ocr', $ocrData);

                // Mapear campos de la factura
                $totales = $datosE['totales'] ?? $datosE;
                $remitente = $datosE['remitente'] ?? [];
                $consignatario = $datosE['consignatario'] ?? [];

                $updateData = [
                    'numero_factura' => $datosE['numero_factura'] ?? ($datosE['invoice_number'] ?? null),
                    'proveedor' => $datosE['proveedor'] ?? ($datosE['vendor'] ?? null),
                    'fecha_factura' => $datosE['fecha'] ?? ($datosE['date'] ?? null),
                    'moneda' => $datosE['moneda'] ?? ($datosE['currency'] ?? 'USD'),

                    // Remitente
                    'remitente_nombre' => $remitente['nombre'] ?? ($remitente['name'] ?? null),
                    'remitente_direccion' => $remitente['direccion'] ?? ($remitente['address'] ?? null),
                    'remitente_contacto' => $remitente['contacto'] ?? null,
                    'remitente_telefono' => $remitente['telefono'] ?? null,

                    // Consignatario
                    'consignatario_nombre' => $consignatario['nombre'] ?? ($consignatario['name'] ?? null),
                    'consignatario_direccion' => $consignatario['direccion'] ?? ($consignatario['address'] ?? null),
                    'consignatario_contacto' => $consignatario['contacto'] ?? null,
                    'pais_consignatario' => $consignatario['pais'] ?? ($consignatario['country'] ?? null),

                    'pais_origen' => $datosE['pais_origen'] ?? ($datosE['origin_country'] ?? null),

                    // Totales
                    'subtotal' => $this->cleanDecimal($totales['subtotal'] ?? 0),
                    'descuento' => $this->cleanDecimal($totales['descuento'] ?? 0),
                    'impuesto_monto' => $this->cleanDecimal($totales['impuesto_monto'] ?? ($totales['tax_amount'] ?? 0)),
                    'impuesto_porcentaje' => $this->cleanDecimal($totales['impuesto_porcentaje'] ?? ($totales['tax_percentage'] ?? 0)),
                    'total_final' => $this->cleanDecimal($totales['total_final'] ?? ($totales['total'] ?? 0)),
                    'total_factura' => $this->cleanDecimal($totales['total_final'] ?? ($totales['total'] ?? 0)),

                    'estado' => 'ocr_completado',
                    'observaciones' => 'Procesamiento finalizado con éxito.'
                ];

                $this->db->update('facturas', $updateData, ['id' => $currentFacturaId]);

                // GUARDAR ÍTEMS DE FACTURA
                $items = $datosE['items'] ?? [];
                if (!empty($items)) {
                    $invoiceItem = new InvoiceItem();
                    foreach ($items as $itemIdx => $item) {
                        $itemData = [
                            'numero_linea' => $item['numero_linea'] ?? ($itemIdx + 1),
                            'numero_serie_parte' => $item['numero_serie_parte'] ?? ($item['sku'] ?? ($item['part_number'] ?? null)),
                            'descripcion' => $item['descripcion'] ?? ($item['description'] ?? 'Sin descripción'),
                            'caracteristicas' => $item['caracteristicas'] ?? null,
                            'datos_importantes' => $item['datos_importantes'] ?? null,
                            'cantidad' => $this->cleanDecimal($item['cantidad'] ?? ($item['qty'] ?? 0)),
                            'unidad_medida' => $item['unidad_medida'] ?? ($item['uom'] ?? null),
                            'precio_unitario' => $this->cleanDecimal($item['precio_unitario'] ?? ($item['unit_price'] ?? 0)),
                            'precio_total' => $this->cleanDecimal($item['precio_total'] ?? ($item['line_total'] ?? 0)),
                            'subtotal' => $this->cleanDecimal($item['precio_total'] ?? ($item['line_total'] ?? 0))
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

            // --- ESTRATEGIA: Vision AI Local primero, luego Fallback Railway ---
            $result = null;

            try {
                // 1. Intentar Local (Prioridad 1)
                error_log("OCRService Corroborate: Intentando Vision AI Local...");
                $result = $this->callLocalVisionAI($filePath, $context);
            } catch (Exception $e) {
                error_log("OCRService Corroborate: Falló local, intentando Railway...");
                try {
                    // 2. Fallback Railway
                    $result = $this->callOCRService($filePath, $context);
                } catch (Exception $e2) {
                    throw new Exception("Error en corroboración total. Local: " . $e->getMessage() . " | Railway: " . $e2->getMessage());
                }
            }

            // Verificar respuesta final
            if (!$result || !isset($result['success'])) {
                throw new Exception('Respuesta inválida del motor de OCR en corroboración');
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
     * Obtener metainformación del archivo (ej: páginas PDF)
     */
    public function getFileInfo($filePath)
    {
        error_log("OCR Service - Getting info for: " . $filePath);
        $url = $this->ocrServiceUrl . '/api/ocr/info';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($filePath)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("OCR Info Error - HTTP $httpCode: $response");
            return ['success' => false, 'pages' => 1];
        }

        return json_decode($response, true);
    }

    /**
     * Unificar resultados de múltiples páginas en uno solo
     */
    private function mergeOCRResults($allResults)
    {
        if (empty($allResults)) {
            error_log("OCRService: mergeOCRResults recibió un array vacío.");
            return null;
        }

        error_log("OCRService: Iniciando unificación de " . count($allResults) . " resultados de página.");

        $finalResult = $allResults[0];
        $masterFactura = null;
        $allItems = [];
        $totalText = "";

        foreach ($allResults as $idx => $res) {
            $facturas = $res['facturas'] ?? [];
            $pageNum = $idx + 1;

            if (empty($facturas)) {
                error_log("OCRService [Página $pageNum]: No se detectaron facturas.");
                continue;
            }

            foreach ($facturas as $fIdx => $f) {
                if (!$masterFactura) {
                    $masterFactura = $f;
                    error_log("OCRService [Página $pageNum]: Tomando datos de cabecera de la primera factura detectada.");
                }

                $totalText .= ($f['texto_completo'] ?? "") . "\n";

                $items = $f['datos_estructurados']['items'] ?? [];
                $itemsCount = count($items);
                error_log("OCRService [Página $pageNum, Factura $fIdx]: Encontrados $itemsCount ítems.");

                foreach ($items as $item) {
                    $allItems[] = $item;
                }
            }
        }

        if ($masterFactura) {
            $masterFactura['texto_completo'] = trim($totalText);
            $masterFactura['datos_estructurados']['items'] = $allItems;
            $finalResult['facturas'] = [$masterFactura];
            error_log("OCRService: Unificación completada. Total ítems unificados: " . count($allItems));
        } else {
            error_log("OCRService: No se encontró ninguna 'masterFactura' tras recorrer los resultados.");
        }

        $finalResult['metodo'] = 'intelligent-ocr-multipage';
        return $finalResult;
    }

    /**
     * Fallback: Llamar a Vision AI localmente vía script Python
     */
    private function callLocalVisionAI($filePath, $context = null)
    {
        $pythonPath = str_replace('\\', '/', PYTHON_PATH);
        $scriptPath = str_replace('\\', '/', realpath(BASE_PATH . '/python-scripts/ocr_process.py'));

        if (!file_exists($scriptPath)) {
            throw new Exception("Script de fallback local no encontrado en: " . $scriptPath);
        }

        $cmd = "\"$pythonPath\" \"$scriptPath\" " . escapeshellarg($filePath) . " " . escapeshellarg($this->apiKey);

        if ($context) {
            $cmd .= " " . escapeshellarg(json_encode($context));
        }

        $output = shell_exec($cmd . " 2>&1");

        if (empty($output)) {
            throw new Exception("El script local no devolvió ninguna salida.");
        }

        // El script devuelve el JSON al final, pero puede haber warnings antes
        $jsonStart = strpos($output, '{"success"');
        if ($jsonStart === false) {
            throw new Exception("No se encontró JSON válido en la salida local: " . substr($output, 0, 500));
        }

        $jsonStr = substr($output, $jsonStart);
        $result = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decodificando JSON local: " . json_last_error_msg());
        }

        return $result;
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
