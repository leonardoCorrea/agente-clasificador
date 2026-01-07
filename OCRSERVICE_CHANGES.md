# Modificaciones a OCRService.php

## Cambios Necesarios

El archivo `classes/OCRService.php` necesita ser modificado para usar el microservicio HTTP en lugar de ejecutar Python directamente.

## Cambios en el Constructor

**ANTES:**
```php
public function __construct()
{
    $this->db = Database::getInstance();
    $this->scriptPath = __DIR__ . '/../python-scripts/ocr_process.py';
    $this->apiKey = OPENAI_API_KEY;
    $this->pythonPath = PYTHON_PATH;
}
```

**DESPUÉS:**
```php
private $ocrServiceUrl;

public function __construct()
{
    $this->db = Database::getInstance();
    $this->apiKey = OPENAI_API_KEY;
    $this->ocrServiceUrl = OCR_SERVICE_URL; // Definido en config.php
}
```

## Nuevo Método: callOCRService

Agregar este método privado después del constructor:

```php
/**
 * Llamar al microservicio OCR vía HTTP
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
            
            error_log("Intento $attempt de $maxRetries falló: " . $e->getMessage());
            
            if ($attempt < $maxRetries) {
                // Exponential backoff: 2^attempt segundos
                sleep(pow(2, $attempt));
            }
        }
    }
    
    throw new Exception("Microservicio OCR falló después de $maxRetries intentos. Último error: " . $lastError->getMessage());
}
```

## Modificar processInvoice

**Reemplazar la sección de ejecución de Python (líneas 53-96) con:**

```php
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
```

El resto del método `processInvoice` permanece igual (procesamiento de facturas, guardado en BD, etc.)

## Modificar corroborateInvoice

**Reemplazar la sección de ejecución de Python (líneas 347-374) con:**

```php
// Llamar al microservicio OCR con contexto
$result = $this->callOCRService($filePath, $context);

// Verificar respuesta
if (!$result || !isset($result['success'])) {
    throw new Exception('Respuesta inválida del microservicio OCR');
}

if (!$result['success']) {
    throw new Exception($result['error'] ?? 'Error desconocido en corroboración');
}
```

El resto del método `corroborateInvoice` permanece igual.

## Resumen de Cambios

1. ✅ Eliminar `$pythonPath` y `$scriptPath` del constructor
2. ✅ Agregar `$ocrServiceUrl` al constructor
3. ✅ Crear método `callOCRService()` con retry logic
4. ✅ Reemplazar `proc_open()` con `callOCRService()` en `processInvoice()`
5. ✅ Reemplazar `shell_exec()` con `callOCRService()` en `corroborateInvoice()`

## Ventajas del Nuevo Enfoque

- ✅ No requiere Python en el servidor PHP
- ✅ Funciona en cualquier hosting (cPanel, shared hosting, etc.)
- ✅ Retry automático en caso de fallos temporales
- ✅ Mejor manejo de errores
- ✅ Escalable independientemente
- ✅ Más fácil de mantener y debuggear

## Testing

Después de hacer los cambios:

1. **En desarrollo**: Inicia el microservicio localmente
   ```bash
   cd python-service
   uvicorn app.main:app --reload
   ```

2. **Prueba OCR**: Sube una factura desde la aplicación PHP

3. **Verifica logs**: Revisa los logs tanto de PHP como del microservicio

4. **En producción**: Despliega a Railway y actualiza `OCR_SERVICE_URL_PROD` en `.env`
