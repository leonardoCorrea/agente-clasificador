<?php
/**
 * Clase Billing
 * Gestión de facturación y costos por cliente
 */

class Billing
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener configuración de facturación para un usuario
     */
    public function getConfig($userId)
    {
        $sql = "SELECT * FROM facturacion_config WHERE usuario_id = ?";
        $config = $this->db->queryOne($sql, [$userId]);

        if (!$config) {
            // Retornar valores por defecto si no existe configuración
            return [
                'usuario_id' => $userId,
                'costo_fijo' => 0.00,
                'costo_linea_reconocida' => 0.00,
                'costo_linea_clasificada' => 0.00,
                'moneda' => 'USD'
            ];
        }

        return $config;
    }

    /**
     * Guardar o actualizar configuración
     */
    public function setConfig($userId, $data)
    {
        // Verificar si ya existe
        $exists = $this->db->queryOne("SELECT id FROM facturacion_config WHERE usuario_id = ?", [$userId]);

        if ($exists) {
            $sql = "UPDATE facturacion_config 
                    SET costo_fijo = ?, costo_linea_reconocida = ?, costo_linea_clasificada = ?, moneda = ?
                    WHERE usuario_id = ?";
            $params = [
                $data['costo_fijo'],
                $data['costo_linea_reconocida'],
                $data['costo_linea_clasificada'],
                $data['moneda'] ?? 'USD',
                $userId
            ];
        } else {
            $sql = "INSERT INTO facturacion_config (costo_fijo, costo_linea_reconocida, costo_linea_clasificada, moneda, usuario_id)
                    VALUES (?, ?, ?, ?, ?)";
            $params = [
                $data['costo_fijo'],
                $data['costo_linea_reconocida'],
                $data['costo_linea_clasificada'],
                $data['moneda'] ?? 'USD',
                $userId
            ];
        }

        $result = $this->db->execute($sql, $params);

        if ($result) {
            AuditLog::log($_SESSION['user_id'] ?? null, 'actualizar_config_facturacion', 'facturacion_config', $userId, null, $data);
            return ['success' => true, 'message' => 'Configuración actualizada correctamente'];
        }

        return ['success' => false, 'message' => 'Error al actualizar configuración'];
    }

    /**
     * Calcular factura mensual
     */
    public function calculateBill($userId, $month, $year)
    {
        $config = $this->getConfig($userId);

        $startDate = "$year-$month-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));

        // 1. Contar líneas reconocidas (items_factura creados en el mes)
        // Se basa en la fecha de carga de la factura
        $sqlRecognized = "SELECT COUNT(i.id) as count 
                          FROM items_factura i
                          JOIN facturas f ON i.factura_id = f.id
                          WHERE f.usuario_id = ? 
                          AND f.fecha_carga BETWEEN ? AND ?";

        $recognizedCount = $this->db->queryOne($sqlRecognized, [$userId, $startDate, $endDate])['count'];

        // 2. Contar líneas clasificadas (Items procesados por IA - resultados_ia)
        // Se basa en la fecha de clasificación
        $sqlClassified = "SELECT COUNT(DISTINCT r.item_factura_id) as count
                          FROM resultados_ia r
                          JOIN items_factura i ON r.item_factura_id = i.id
                          JOIN facturas f ON i.factura_id = f.id
                          WHERE f.usuario_id = ?
                          AND r.fecha_clasificacion BETWEEN ? AND ?";

        $classifiedCount = $this->db->queryOne($sqlClassified, [$userId, $startDate, $endDate])['count'];

        // DEBUG: Contar Total Histórico para comparar (basado en resultados_ia)
        $sqlClassifiedAll = "SELECT COUNT(DISTINCT r.item_factura_id) as count
                          FROM resultados_ia r
                          JOIN items_factura i ON r.item_factura_id = i.id
                          JOIN facturas f ON i.factura_id = f.id
                          WHERE f.usuario_id = ?";
        $classifiedAllTime = $this->db->queryOne($sqlClassifiedAll, [$userId])['count'];

        // Cálculos
        $fixedCost = (float) $config['costo_fijo'];
        $recognizedCost = $recognizedCount * (float) $config['costo_linea_reconocida'];
        $classifiedCost = $classifiedCount * (float) $config['costo_linea_clasificada'];

        $total = $fixedCost + $recognizedCost + $classifiedCost;

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'startDate' => $startDate,
                'endDate' => $endDate
            ],
            'config' => $config,
            'details' => [
                'fixed_fee' => [
                    'label' => 'Costo Fijo Mensual',
                    'quantity' => 1,
                    'unit_price' => $fixedCost,
                    'total' => $fixedCost
                ],
                'recognized_lines' => [
                    'label' => 'Líneas Reconocidas (OCR)',
                    'quantity' => $recognizedCount,
                    'unit_price' => (float) $config['costo_linea_reconocida'],
                    'total' => $recognizedCost
                ],
                'classified_lines' => [
                    'label' => 'Líneas Clasificadas (IA)',
                    'quantity' => $classifiedCount,
                    'unit_price' => (float) $config['costo_linea_clasificada'],
                    'total' => $classifiedCost
                ]
            ],
            'total' => $total,
            'currency' => $config['moneda'],
            'debug_info' => [
                'classified_all_time' => $classifiedAllTime
            ]
        ];
    }

    /**
     * Obtener lista de usuarios para administración
     */
    public function getUsersForAdmin()
    {
        // Removed exclusion of admins to allow testing and billing for all users
        return $this->db->query("SELECT id, nombre, apellido, email, rol FROM usuarios");
    }
}
