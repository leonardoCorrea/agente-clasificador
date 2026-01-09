<?php
/**
 * Clase Database
 * Gestión de conexión a base de datos usando PDO
 */

class Database
{
    private static $instance = null;
    private $connection;

    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Establecer conexión a la base de datos
     */
    private function connect()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_PERSISTENT => false // No usar conexiones persistentes por defecto
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Log de conexión exitosa solo en desarrollo
            if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT) {
                error_log("Conexión a base de datos establecida: " . DB_HOST . "/" . DB_NAME);
            }
        } catch (PDOException $e) {
            $errorMsg = "Error de conexión a base de datos: " . $e->getMessage();
            error_log($errorMsg);
            error_log("Host: " . DB_HOST . ", DB: " . DB_NAME . ", User: " . DB_USER);

            // En desarrollo, mostrar más detalles
            if (defined('IS_DEVELOPMENT') && IS_DEVELOPMENT) {
                die("Error de conexión a la base de datos: " . $e->getMessage() . "<br>Host: " . DB_HOST . "<br>Database: " . DB_NAME);
            }

            die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
        }
    }

    /**
     * Obtener instancia única de la clase (Singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener conexión PDO
     */
    public function getConnection()
    {
        // Verificar que la conexión sigue activa
        if (!$this->isConnected()) {
            $this->reconnect();
        }
        return $this->connection;
    }

    /**
     * Verificar si la conexión está activa
     * @return bool
     */
    public function isConnected()
    {
        if ($this->connection === null) {
            return false;
        }

        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            error_log("Conexión perdida: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reconectar a la base de datos
     */
    public function reconnect()
    {
        error_log("Intentando reconectar a la base de datos...");
        $this->connection = null;
        $this->connect();
    }

    /**
     * Asegurar que la conexión está activa
     */
    private function checkConnection()
    {
        if (!$this->isConnected()) {
            $this->reconnect();
        }
    }

    /**
     * Ejecutar consulta SELECT
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return array Resultados de la consulta
     */
    public function query($sql, $params = [])
    {
        $this->checkConnection();
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en query: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Ejecutar consulta SELECT y obtener un solo registro
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return array|false Registro encontrado o false
     */
    public function queryOne($sql, $params = [])
    {
        $this->checkConnection();
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en queryOne: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Ejecutar consulta INSERT, UPDATE, DELETE
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return bool True si se ejecutó correctamente
     */
    public function execute($sql, $params = [])
    {
        $this->checkConnection();
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en execute: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Obtener ID del último registro insertado
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Revertir transacción
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Método helper para SELECT
     */
    public function select($sql, $params = [])
    {
        return $this->query($sql, $params);
    }

    /**
     * Método helper para INSERT
     */
    public function insert($table, $data)
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        if ($this->execute($sql, $values)) {
            return $this->lastInsertId();
        }
        return false;
    }

    /**
     * Método helper para UPDATE
     */
    public function update($table, $data, $where)
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        $whereClause = [];
        foreach ($where as $key => $value) {
            $whereClause[] = "$key = ?";
            $values[] = $value;
        }

        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE " . implode(' AND ', $whereClause);

        return $this->execute($sql, $values);
    }

    /**
     * Método helper para DELETE
     */
    public function delete($table, $where)
    {
        $whereClause = [];
        $values = [];

        foreach ($where as $key => $value) {
            $whereClause[] = "$key = ?";
            $values[] = $value;
        }

        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);

        return $this->execute($sql, $values);
    }

    /**
     * Prevenir clonación del objeto
     */
    private function __clone()
    {
    }

    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup()
    {
        throw new Exception("No se puede deserializar singleton");
    }
}
