<?php
/**
 * Servicio de Generación de DUA
 * Maneja la lógica de creación de DUAs y cálculo de impuestos (Liquidación)
 */
require_once __DIR__ . '/CatalogService.php';

class DUAService
{
    private $db;
    private $catalogService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->catalogService = new CatalogService();
    }

    /**
     * Crear borrador de DUA a partir de facturas seleccionadas
     */
    public function createDraftFromInvoices($userId, $invoiceIds)
    {
        if (empty($invoiceIds)) {
            return ['success' => false, 'message' => 'No se seleccionaron facturas'];
        }

        // 1. Obtener la suma total FOB (valor de mercancías) de las facturas
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));

        // Calcular totales de las facturas seleccionadas
        $sqlTotals = "SELECT SUM(total_factura) as total_fob FROM facturas WHERE id IN ($placeholders) AND usuario_id = ?";
        $params = array_merge($invoiceIds, [$userId]);
        $totals = $this->db->queryOne($sqlTotals, $params);

        if (!$totals || $totals['total_fob'] <= 0) {
            return ['success' => false, 'message' => 'Error al calcular totales de facturas'];
        }

        $totalFOB = $totals['total_fob'];

        // Estimación básica de Flete y Seguro (10% y 1% por defecto si no hay datos, editable luego)
        // En un sistema real, esto vendría del B/L y póliza
        $fleteTotal = $totalFOB * 0.10;
        $seguroTotal = $totalFOB * 0.01;
        $valorCIF = $totalFOB + $fleteTotal + $seguroTotal;

        // 2. Crear Encabezado DUA
        $ref = 'DUA-' . date('Ymd') . '-' . rand(1000, 9999);
        $sqlHeader = "INSERT INTO duas (usuario_id, numero_referencia, valor_cif_total, estado) VALUES (?, ?, ?, 'borrador')";

        if (!$this->db->execute($sqlHeader, [$userId, $ref, $valorCIF])) {
            return ['success' => false, 'message' => 'Error al crear encabezado DUA'];
        }

        $duaId = $this->db->lastInsertId();

        // 3. Crear Líneas (Items) obteniendo datos de items_factura y clasificacion_final
        $totalSkipped = 0;
        foreach ($invoiceIds as $invId) {
            $skipped = $this->addInvoiceItemsToDua($duaId, $invId, $totalFOB, $fleteTotal, $seguroTotal);
            $totalSkipped += $skipped;
        }

        // 4. Recalcular impuestos totales del DUA
        $this->recalculateDuaTotals($duaId);

        // Prepare success message with warning if items were skipped
        $message = 'Borrador de DUA creado exitosamente';
        if ($totalSkipped > 0) {
            $message .= ". ADVERTENCIA: {$totalSkipped} item(s) fueron omitidos porque no tienen clasificación SAC aprobada. Por favor, clasifique todos los items antes de generar el DUA.";
        }

        return ['success' => true, 'dua_id' => $duaId, 'message' => $message, 'items_skipped' => $totalSkipped];
    }

    /**
     * Agregar items de una factura al DUA
     * Realiza el prorrateo de flete y seguro basado en el valor FOB de la línea
     * @return int Number of items skipped due to missing classification
     */
    private function addInvoiceItemsToDua($duaId, $invoiceId, $totalFOBGlobal, $totalFlete, $totalSeguro)
    {
        // Obtener items con su clasificación final (aprobada)
        // Si no está aprobada, se usa la sugerencia de IA o nulo
        $sqlItems = "SELECT i.*, c.codigo_arancelario as codigo_aprobado 
                     FROM items_factura i
                     LEFT JOIN clasificacion_final c ON i.id = c.item_factura_id
                     WHERE i.factura_id = ?";

        $items = $this->db->query($sqlItems, [$invoiceId]);
        $skippedCount = 0;

        foreach ($items as $item) {
            // Determinar código SAC (solo usar si está aprobado)
            $sacCode = $item['codigo_aprobado'] ?? null;

            // Skip items without approved classification
            if (empty($sacCode)) {
                $skippedCount++;
                error_log("DUA Item skipped: Invoice item ID {$item['id']} has no approved SAC classification");
                continue;
            }

            // Obtener tasas del catálogo
            $taxInfo = $this->catalogService->getTaxInfo($sacCode);

            // Si no existe en catálogo, usar valores defaults (0%)
            $taxes = [
                'dai' => $taxInfo['dai'] ?? 0,
                'sc' => $taxInfo['sc'] ?? 0,
                'ley' => $taxInfo['ley_6946'] ?? 1,
                'iva' => $taxInfo['iva'] ?? 13
            ];

            // Prorrateo (Rule of three based on value)
            $fobLinea = $item['subtotal']; // Asumiendo subtotal es el valor total de la línea
            $factor = ($totalFOBGlobal > 0) ? ($fobLinea / $totalFOBGlobal) : 0;

            $fleteLinea = $totalFlete * $factor;
            $seguroLinea = $totalSeguro * $factor;
            $cifLinea = $fobLinea + $fleteLinea + $seguroLinea;

            // Calcular Impuestos (Fórmula Hacienda CR)
            // DAI = CIF * %DAI
            $montoDai = $cifLinea * ($taxes['dai'] / 100);

            // SC = (CIF + DAI) * %SC
            $montoSc = ($cifLinea + $montoDai) * ($taxes['sc'] / 100);

            // Ley = CIF * %Ley (Usualmente 1%)
            $montoLey = $cifLinea * ($taxes['ley'] / 100);

            // IVA = (CIF + DAI + SC + Ley) * %IVA
            $baseIva = $cifLinea + $montoDai + $montoSc + $montoLey;
            $montoIva = $baseIva * ($taxes['iva'] / 100);

            $totalImpuestos = $montoDai + $montoSc + $montoLey + $montoIva;

            // Insertar Línea DUA
            $sqlInsert = "INSERT INTO dua_items 
                (dua_id, item_factura_id, codigo_sac, numero_linea, descripcion_mercancia, cantidad, 
                 valor_fob, flete_prorrateado, seguro_prorrateado, valor_cif,
                 dai_monto, sc_monto, ley_monto, iva_monto, total_impuestos_linea, porcentajes_aplicados)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->db->execute($sqlInsert, [
                $duaId,
                $item['id'],
                $sacCode,
                $item['numero_linea'],
                $item['descripcion'],
                $item['cantidad'],
                $fobLinea,
                $fleteLinea,
                $seguroLinea,
                $cifLinea,
                $montoDai,
                $montoSc,
                $montoLey,
                $montoIva,
                $totalImpuestos,
                json_encode($taxes)
            ]);
        }

        return $skippedCount;
    }

    /**
     * Recalcular totales del encabezado DUA
     */
    public function recalculateDuaTotals($duaId)
    {
        $sql = "SELECT SUM(valor_cif) as total_cif, SUM(total_impuestos_linea) as total_tax, 
                       SUM(cantidad) as total_units 
                FROM dua_items WHERE dua_id = ?";
        $totals = $this->db->queryOne($sql, [$duaId]);

        if ($totals) {
            $this->db->update('duas', [
                'valor_cif_total' => $totals['total_cif'],
                'total_impuestos' => $totals['total_tax'],
                'total_bultos' => $totals['total_units'] // Simplificacion: bultos = unidades por ahora
            ], ['id' => $duaId]);
        }
    }

    /**
     * Obtener DUA completo
     */
    public function getDua($duaId)
    {
        $header = $this->db->queryOne("SELECT * FROM duas WHERE id = ?", [$duaId]);
        if (!$header)
            return null;

        $items = $this->db->query("SELECT * FROM dua_items WHERE dua_id = ?", [$duaId]);

        return ['header' => $header, 'items' => $items];
    }

    /**
     * Validar que el DUA esté listo para finalizar
     */
    public function validateDuaForFinalization($duaId)
    {
        $dua = $this->getDua($duaId);
        if (!$dua) {
            return ['valid' => false, 'message' => 'DUA no encontrado'];
        }

        // Verificar que tenga items
        if (empty($dua['items'])) {
            return ['valid' => false, 'message' => 'El DUA no tiene items. No se puede finalizar un DUA vacío.'];
        }

        // Verificar que todos los items tengan clasificación SAC válida
        foreach ($dua['items'] as $item) {
            if (empty($item['codigo_sac']) || $item['codigo_sac'] === '0000000000') {
                return ['valid' => false, 'message' => 'Algunos items no tienen clasificación SAC válida'];
            }
        }

        return ['valid' => true, 'message' => 'DUA válido para finalizar'];
    }

    /**
     * Finalizar DUA (cambiar de borrador a generado)
     */
    public function finalizeDua($duaId)
    {
        // Validar antes de finalizar
        $validation = $this->validateDuaForFinalization($duaId);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        // Recalcular totales una última vez
        $this->recalculateDuaTotals($duaId);

        // Cambiar estado a 'generado'
        $updated = $this->db->update('duas', [
            'estado' => 'generado'
        ], ['id' => $duaId]);

        if ($updated) {
            return ['success' => true, 'message' => 'DUA finalizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al finalizar DUA'];
        }
    }

    /**
     * Actualizar estado del DUA
     */
    public function updateDuaStatus($duaId, $newStatus)
    {
        $validStatuses = ['borrador', 'generado', 'transmitido'];
        if (!in_array($newStatus, $validStatuses)) {
            return ['success' => false, 'message' => 'Estado inválido'];
        }

        $updated = $this->db->update('duas', [
            'estado' => $newStatus
        ], ['id' => $duaId]);

        if ($updated) {
            return ['success' => true, 'message' => "Estado actualizado a: $newStatus"];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar estado'];
        }
    }

    /**
     * Listar todos los DUAs del usuario
     */
    public function getUserDuas($userId, $limit = 50)
    {
        $sql = "SELECT * FROM duas WHERE usuario_id = ? ORDER BY fecha_generacion DESC LIMIT ?";
        return $this->db->query($sql, [$userId, $limit]);
    }
}
