<?php
/**
 * Servicio de Catálogo Arancelario
 * Gestiona la búsqueda de partidas y cálculo de impuestos base
 */
class CatalogService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Buscar en el catálogo por código o descripción
     */
    public function search($query, $limit = 50)
    {
        $searchTerm = "%$query%";
        $sql = "SELECT * FROM catalogo_arancelario 
                WHERE codigo_sac LIKE ? OR descripcion LIKE ? 
                LIMIT ?";
        return $this->db->query($sql, [$searchTerm, $searchTerm, $limit]);
    }

    /**
     * Obtener información de impuestos por código SAC
     */
    public function getTaxInfo($code)
    {
        return $this->db->queryOne(
            "SELECT * FROM catalogo_arancelario WHERE codigo_sac = ?",
            [$code]
        );
    }

    /**
     * Importar catálogo desde array (para seed/csv)
     * Estructura esperada: ['codigo_sac', 'descripcion', 'dai', 'sc', 'iva', 'ley', 'unidad']
     */
    public function importCatalogItem($data)
    {
        // Verificar si existe
        $exists = $this->db->queryOne(
            "SELECT codigo_sac FROM catalogo_arancelario WHERE codigo_sac = ?",
            [$data['codigo_sac']]
        );

        if ($exists) {
            $sql = "UPDATE catalogo_arancelario SET 
                    descripcion = ?, dai = ?, sc = ?, iva = ?, ley_6946 = ?, unidad_medida = ?
                    WHERE codigo_sac = ?";
            $params = [
                $data['descripcion'],
                $data['dai'],
                $data['sc'],
                $data['iva'],
                $data['ley'] ?? 1.00,
                $data['unidad'] ?? 'Unidades',
                $data['codigo_sac']
            ];
            return $this->db->execute($sql, $params);
        } else {
            $sql = "INSERT INTO catalogo_arancelario 
                    (codigo_sac, descripcion, dai, sc, iva, ley_6946, unidad_medida) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $data['codigo_sac'],
                $data['descripcion'],
                $data['dai'],
                $data['sc'],
                $data['iva'],
                $data['ley'] ?? 1.00,
                $data['unidad'] ?? 'Unidades'
            ];
            return $this->db->execute($sql, $params);
        }
    }
}
