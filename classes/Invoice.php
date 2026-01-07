<?php
/**
 * Clase Invoice
 * Gestión de facturas
 */

class Invoice
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crear nueva factura
     */
    public function create($usuarioId, $archivoData)
    {
        // Validar archivo
        $validacion = $this->validateFile($archivoData);
        if (!$validacion['success']) {
            return $validacion;
        }

        // Guardar archivo
        $uploadResult = $this->saveFile($archivoData);
        if (!$uploadResult['success']) {
            return $uploadResult;
        }

        // Insertar factura en base de datos
        $sql = "INSERT INTO facturas (usuario_id, archivo_original, ruta_archivo, tipo_archivo, tamano_archivo, estado) 
                VALUES (?, ?, ?, ?, ?, 'pendiente')";

        $result = $this->db->execute($sql, [
            $usuarioId,
            $uploadResult['original_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            $uploadResult['file_size']
        ]);

        if ($result) {
            $facturaId = $this->db->lastInsertId();

            AuditLog::log($usuarioId, 'crear_factura', 'facturas', $facturaId, null, [
                'archivo' => $uploadResult['original_name']
            ]);

            return [
                'success' => true,
                'message' => 'Factura creada exitosamente',
                'factura_id' => $facturaId,
                'file_path' => $uploadResult['file_path']
            ];
        }

        return ['success' => false, 'message' => 'Error al crear factura'];
    }

    /**
     * Obtener factura por ID
     */
    public function getById($id)
    {
        $sql = "SELECT f.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido 
                FROM facturas f 
                LEFT JOIN usuarios u ON f.usuario_id = u.id 
                WHERE f.id = ?";

        return $this->db->queryOne($sql, [$id]);
    }

    /**
     * Obtener todas las facturas
     */
    public function getAll($filters = [], $limit = 50, $offset = 0)
    {
        $sql = "SELECT f.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido,
                (SELECT COUNT(*) FROM items_factura WHERE factura_id = f.id) as total_items
                FROM facturas f 
                LEFT JOIN usuarios u ON f.usuario_id = u.id 
                WHERE 1=1";

        $params = [];

        if (isset($filters['usuario_id'])) {
            $sql .= " AND f.usuario_id = ?";
            $params[] = $filters['usuario_id'];
        }

        if (isset($filters['estado'])) {
            $sql .= " AND f.estado = ?";
            $params[] = $filters['estado'];
        }

        if (isset($filters['fecha_desde'])) {
            $sql .= " AND f.fecha_carga >= ?";
            $params[] = $filters['fecha_desde'];
        }

        if (isset($filters['fecha_hasta'])) {
            $sql .= " AND f.fecha_carga <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        $sql .= " ORDER BY f.fecha_carga DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->query($sql, $params);
    }

    /**
     * Actualizar factura
     */
    public function update($id, $data)
    {
        $allowedFields = ['numero_factura', 'proveedor', 'fecha_factura', 'total_factura', 'moneda', 'observaciones', 'estado', 'remitente_nombre', 'consignatario_nombre', 'pais_origen'];
        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No hay campos para actualizar'];
        }

        $params[] = $id;
        $sql = "UPDATE facturas SET " . implode(', ', $updates) . " WHERE id = ?";

        try {
            $result = $this->db->execute($sql, $params);

            if ($result) {
                AuditLog::log($_SESSION['user_id'] ?? null, 'actualizar_factura', 'facturas', $id, null, $data);
                return ['success' => true, 'message' => 'Factura actualizada exitosamente'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        }

        return ['success' => false, 'message' => 'Error al actualizar factura (sin respuesta del driver)'];
    }

    /**
     * Eliminar factura
     */
    public function delete($id)
    {
        // Obtener información del archivo para eliminarlo
        $factura = $this->getById($id);

        if ($factura && file_exists($factura['ruta_archivo'])) {
            unlink($factura['ruta_archivo']);
        }

        $result = $this->db->execute("DELETE FROM facturas WHERE id = ?", [$id]);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'eliminar_factura', 'facturas', $id, $factura, null);
            return ['success' => true, 'message' => 'Factura eliminada exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al eliminar factura'];
    }

    /**
     * Validar archivo
     */
    private function validateFile($file)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No se recibió ningún archivo'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al subir archivo'];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'El archivo excede el tamaño máximo permitido'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
        }

        return ['success' => true];
    }

    /**
     * Guardar archivo
     */
    private function saveFile($file)
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = uniqid('factura_') . '.' . $extension;
        $filePath = UPLOAD_PATH . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true,
                'original_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $extension,
                'file_size' => $file['size']
            ];
        }

        return ['success' => false, 'message' => 'Error al guardar archivo'];
    }

    /**
     * Cambiar estado de factura
     */
    public function changeStatus($id, $nuevoEstado)
    {
        $estadosValidos = ['pendiente', 'procesando', 'ocr_completado', 'digitado', 'clasificado', 'aprobado', 'rechazado'];

        if (!in_array($nuevoEstado, $estadosValidos)) {
            return ['success' => false, 'message' => 'Estado no válido'];
        }

        $result = $this->db->execute("UPDATE facturas SET estado = ? WHERE id = ?", [$nuevoEstado, $id]);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'cambiar_estado_factura', 'facturas', $id, null, ['estado' => $nuevoEstado]);
            return ['success' => true, 'message' => 'Estado actualizado'];
        }

        return ['success' => false, 'message' => 'Error al cambiar estado'];
    }
}
