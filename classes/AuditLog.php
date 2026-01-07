<?php
/**
 * Clase AuditLog
 * Registro de auditoría del sistema
 */

class AuditLog
{

    /**
     * Registrar acción en auditoría
     */
    public static function log($userId, $accion, $tabla, $registroId, $datosAnteriores = null, $datosNuevos = null)
    {
        $db = Database::getInstance();

        $sql = "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        return $db->execute($sql, [
            $userId,
            $accion,
            $tabla,
            $registroId,
            $datosAnteriores ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null,
            $datosNuevos ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Obtener registros de auditoría
     */
    public static function getAll($filters = [], $limit = 100, $offset = 0)
    {
        $db = Database::getInstance();

        $sql = "SELECT a.*, u.nombre, u.apellido, u.email 
                FROM auditoria a 
                LEFT JOIN usuarios u ON a.usuario_id = u.id 
                WHERE 1=1";

        $params = [];

        if (isset($filters['usuario_id'])) {
            $sql .= " AND a.usuario_id = ?";
            $params[] = $filters['usuario_id'];
        }

        if (isset($filters['accion'])) {
            $sql .= " AND a.accion = ?";
            $params[] = $filters['accion'];
        }

        if (isset($filters['tabla'])) {
            $sql .= " AND a.tabla_afectada = ?";
            $params[] = $filters['tabla'];
        }

        if (isset($filters['fecha_desde'])) {
            $sql .= " AND a.fecha_accion >= ?";
            $params[] = $filters['fecha_desde'];
        }

        if (isset($filters['fecha_hasta'])) {
            $sql .= " AND a.fecha_accion <= ?";
            $params[] = $filters['fecha_hasta'];
        }

        $sql .= " ORDER BY a.fecha_accion DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $db->query($sql, $params);
    }

    /**
     * Contar registros de auditoría
     */
    public static function count($filters = [])
    {
        $db = Database::getInstance();

        $sql = "SELECT COUNT(*) as total FROM auditoria WHERE 1=1";
        $params = [];

        if (isset($filters['usuario_id'])) {
            $sql .= " AND usuario_id = ?";
            $params[] = $filters['usuario_id'];
        }

        if (isset($filters['accion'])) {
            $sql .= " AND accion = ?";
            $params[] = $filters['accion'];
        }

        if (isset($filters['tabla'])) {
            $sql .= " AND tabla_afectada = ?";
            $params[] = $filters['tabla'];
        }

        $result = $db->queryOne($sql, $params);
        return $result ? $result['total'] : 0;
    }
}
