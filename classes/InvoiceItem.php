<?php
/**
 * Clase InvoiceItem
 * Gestión de ítems de factura
 */

class InvoiceItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crear nuevo ítem de factura
     */
    public function create($facturaId, $data)
    {
        $sql = "INSERT INTO items_factura (factura_id, numero_linea, descripcion, cantidad, unidad_medida, precio_unitario, subtotal, codigo_producto, observaciones, numero_serie_parte, caracteristicas, datos_importantes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $result = $this->db->execute($sql, [
            $facturaId,
            $data['numero_linea'],
            $data['descripcion'],
            $data['cantidad'],
            $data['unidad_medida'] ?? null,
            $data['precio_unitario'],
            $data['subtotal'],
            $data['codigo_producto'] ?? null,
            $data['observaciones'] ?? null,
            $data['numero_serie_parte'] ?? null,
            $data['caracteristicas'] ?? null,
            $data['datos_importantes'] ?? null
        ]);

        if ($result) {
            $itemId = $this->db->lastInsertId();

            AuditLog::log($_SESSION['user_id'] ?? null, 'crear_item_factura', 'items_factura', $itemId, null, $data);

            return [
                'success' => true,
                'message' => 'Ítem creado exitosamente',
                'item_id' => $itemId
            ];
        }

        return ['success' => false, 'message' => 'Error al crear ítem'];
    }

    /**
     * Crear múltiples ítems
     */
    public function createBatch($facturaId, $items)
    {
        $this->db->beginTransaction();

        try {
            foreach ($items as $item) {
                $result = $this->create($facturaId, $item);
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Ítems creados exitosamente'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Error al crear ítems: ' . $e->getMessage()];
        }
    }

    /**
     * Obtener ítems por factura
     */
    public function getByFactura($facturaId)
    {
        $sql = "SELECT * FROM items_factura WHERE factura_id = ? ORDER BY numero_linea";
        return $this->db->query($sql, [$facturaId]);
    }

    /**
     * Obtener ítem por ID
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM items_factura WHERE id = ?";
        return $this->db->queryOne($sql, [$id]);
    }

    /**
     * Actualizar ítem
     */
    public function update($id, $data)
    {
        $allowedFields = ['descripcion', 'cantidad', 'unidad_medida', 'precio_unitario', 'subtotal', 'codigo_producto', 'observaciones', 'numero_serie_parte', 'caracteristicas', 'datos_importantes'];
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
        $sql = "UPDATE items_factura SET " . implode(', ', $updates) . " WHERE id = ?";

        $result = $this->db->execute($sql, $params);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'actualizar_item_factura', 'items_factura', $id, null, $data);
            return ['success' => true, 'message' => 'Ítem actualizado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar ítem'];
    }

    /**
     * Eliminar ítem
     */
    public function delete($id)
    {
        $result = $this->db->execute("DELETE FROM items_factura WHERE id = ?", [$id]);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'eliminar_item_factura', 'items_factura', $id, null, null);
            return ['success' => true, 'message' => 'Ítem eliminado exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al eliminar ítem'];
    }

    /**
     * Obtener ítems sin clasificar
     */
    public function getUnclassified($facturaId = null)
    {
        $sql = "SELECT i.* FROM items_factura i 
                LEFT JOIN clasificacion_final c ON i.id = c.item_factura_id 
                WHERE c.id IS NULL";

        $params = [];

        if ($facturaId) {
            $sql .= " AND i.factura_id = ?";
            $params[] = $facturaId;
        }

        $sql .= " ORDER BY i.factura_id, i.numero_linea";

        return $this->db->query($sql, $params);
    }
}
