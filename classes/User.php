<?php
/**
 * Clase User
 * Gestión de usuarios del sistema
 */

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener usuario por ID
     */
    public function getById($id)
    {
        $sql = "SELECT id, nombre, apellido, email, rol, activo, email_verificado, 
                fecha_creacion, fecha_actualizacion, ultimo_acceso 
                FROM usuarios WHERE id = ?";

        return $this->db->queryOne($sql, [$id]);
    }

    /**
     * Obtener todos los usuarios
     */
    public function getAll($filters = [])
    {
        $sql = "SELECT id, nombre, apellido, email, rol, activo, email_verificado, 
                fecha_creacion, ultimo_acceso 
                FROM usuarios WHERE 1=1";

        $params = [];

        if (isset($filters['rol'])) {
            $sql .= " AND rol = ?";
            $params[] = $filters['rol'];
        }

        if (isset($filters['activo'])) {
            $sql .= " AND activo = ?";
            $params[] = $filters['activo'];
        }

        $sql .= " ORDER BY fecha_creacion DESC";

        return $this->db->query($sql, $params);
    }

    /**
     * Actualizar usuario
     */
    public function update($id, $data)
    {
        $allowedFields = ['nombre', 'apellido', 'email', 'rol', 'activo'];
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
        $sql = "UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id = ?";

        $result = $this->db->execute($sql, $params);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'actualizar_usuario', 'usuarios', $id, null, $data);
            return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar usuario'];
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $user = $this->db->queryOne("SELECT password_hash FROM usuarios WHERE id = ?", [$userId]);

        if (!$user) {
            return ['success' => false, 'message' => 'Usuario no encontrado'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Contraseña actual incorrecta'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $result = $this->db->execute("UPDATE usuarios SET password_hash = ? WHERE id = ?", [$newHash, $userId]);

        if ($result) {
            AuditLog::log($userId, 'cambio_password', 'usuarios', $userId, null, null);
            return ['success' => true, 'message' => 'Contraseña cambiada exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al cambiar contraseña'];
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function delete($id)
    {
        $result = $this->db->execute("UPDATE usuarios SET activo = 0 WHERE id = ?", [$id]);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'eliminar_usuario', 'usuarios', $id, null, null);
            return ['success' => true, 'message' => 'Usuario eliminado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al eliminar usuario'];
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getStats($userId)
    {
        // Reemplazamos SP con consultas directas para mayor flexibilidad
        $stats = [
            'total_facturas' => 0,
            'lineas_aprobadas' => 0,
            'items_clasificados' => 0,
            'lineas_pendientes' => 0, // Antes facturas pendientes
            'total_lineas' => 0
        ];

        // 1. Total Facturas
        $stats['total_facturas'] = $this->db->queryOne(
            "SELECT COUNT(*) as count FROM facturas WHERE usuario_id = ?",
            [$userId]
        )['count'];

        // 2. Líneas ya aprobadas por el cliente
        $stats['lineas_aprobadas'] = $this->db->queryOne(
            "SELECT COUNT(c.id) as count 
             FROM clasificacion_final c
             JOIN items_factura i ON c.item_factura_id = i.id
             JOIN facturas f ON i.factura_id = f.id
             WHERE f.usuario_id = ?",
            [$userId]
        )['count'];

        // 3. Líneas que se han clasificado (tienen resultado IA pero NO aprobación final)
        $stats['items_clasificados'] = $this->db->queryOne(
            "SELECT COUNT(r.id) as count
             FROM (
                SELECT item_factura_id, MAX(id) as max_id 
                FROM resultados_ia 
                GROUP BY item_factura_id
             ) r_max
             JOIN resultados_ia r ON r_max.max_id = r.id
             JOIN items_factura i ON r.item_factura_id = i.id
             JOIN facturas f ON i.factura_id = f.id
             LEFT JOIN clasificacion_final c ON i.id = c.item_factura_id
             WHERE f.usuario_id = ? AND c.id IS NULL",
            [$userId]
        )['count'];

        // 4. Líneas pendientes (sin clasificación IA ni aprobación)
        $stats['lineas_pendientes'] = $this->db->queryOne(
            "SELECT COUNT(i.id) as count
             FROM items_factura i
             JOIN facturas f ON i.factura_id = f.id
             LEFT JOIN resultados_ia r ON i.id = r.item_factura_id
             LEFT JOIN clasificacion_final c ON i.id = c.item_factura_id
             WHERE f.usuario_id = ? AND r.id IS NULL AND c.id IS NULL",
            [$userId]
        )['count'];

        // 5. Total de Líneas Identificadas (todos los items extraídos)
        $stats['total_lineas'] = $this->db->queryOne(
            "SELECT COUNT(i.id) as count 
             FROM items_factura i
             JOIN facturas f ON i.factura_id = f.id
             WHERE f.usuario_id = ?",
            [$userId]
        )['count'];

        return $stats;
    }
}
