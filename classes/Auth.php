<?php
/**
 * Clase Auth
 * Gestión de autenticación y autorización de usuarios
 */

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Registrar nuevo usuario
     */
    public function register($nombre, $apellido, $email, $password, $rol = 'operador')
    {
        // Validar que el email no exista
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'El email ya está registrado'];
        }

        // Validar longitud de contraseña
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres'];
        }

        // Hash de contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Generar token de verificación
        $tokenVerificacion = bin2hex(random_bytes(32));

        // Insertar usuario
        $sql = "INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, token_verificacion) 
                VALUES (?, ?, ?, ?, ?, ?)";

        $result = $this->db->execute($sql, [$nombre, $apellido, $email, $passwordHash, $rol, $tokenVerificacion]);

        if ($result) {
            $userId = $this->db->lastInsertId();

            // Registrar en auditoría
            $this->logAudit(null, 'registro_usuario', 'usuarios', $userId, null, [
                'email' => $email,
                'rol' => $rol
            ]);

            return [
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'user_id' => $userId,
                'verification_token' => $tokenVerificacion
            ];
        }

        return ['success' => false, 'message' => 'Error al registrar usuario'];
    }

    /**
     * Iniciar sesión
     */
    public function login($email, $password, $rememberMe = false)
    {
        $sql = "SELECT id, nombre, apellido, email, password_hash, rol, activo, email_verificado 
                FROM usuarios WHERE email = ?";

        $user = $this->db->queryOne($sql, [$email]);

        if (!$user) {
            return ['success' => false, 'message' => 'Credenciales incorrectas'];
        }

        // Verificar si el usuario está activo
        if (!$user['activo']) {
            return ['success' => false, 'message' => 'Usuario desactivado. Contacte al administrador'];
        }

        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Credenciales incorrectas'];
        }

        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_apellido'] = $user['apellido'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['email_verificado'] = $user['email_verificado'];
        $_SESSION['login_time'] = time();

        // Actualizar último acceso
        $this->db->execute("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?", [$user['id']]);

        // Registrar en auditoría
        $this->logAudit($user['id'], 'login', 'usuarios', $user['id'], null, null);

        return [
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ]
        ];
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $this->logAudit($userId, 'logout', 'usuarios', $userId, null, null);
        }

        session_destroy();
        return ['success' => true, 'message' => 'Sesión cerrada'];
    }

    /**
     * Solicitar recuperación de contraseña
     */
    public function requestPasswordReset($email)
    {
        $user = $this->db->queryOne("SELECT id FROM usuarios WHERE email = ? AND activo = 1", [$email]);

        if (!$user) {
            // Por seguridad, no revelar si el email existe
            return ['success' => true, 'message' => 'Si el email existe, recibirá instrucciones de recuperación'];
        }

        // Generar token de recuperación
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $sql = "UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?";
        $this->db->execute($sql, [$token, $expiracion, $user['id']]);

        // Aquí se enviaría el email (implementar con PHPMailer o similar)
        // sendPasswordResetEmail($email, $token);

        $this->logAudit($user['id'], 'solicitud_recuperacion_password', 'usuarios', $user['id'], null, null);

        return [
            'success' => true,
            'message' => 'Si el email existe, recibirá instrucciones de recuperación',
            'token' => $token // Solo para desarrollo, remover en producción
        ];
    }

    /**
     * Restablecer contraseña
     */
    public function resetPassword($token, $newPassword)
    {
        $sql = "SELECT id FROM usuarios 
                WHERE token_recuperacion = ? 
                AND token_expiracion > NOW() 
                AND activo = 1";

        $user = $this->db->queryOne($sql, [$token]);

        if (!$user) {
            return ['success' => false, 'message' => 'Token inválido o expirado'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres'];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        $sql = "UPDATE usuarios 
                SET password_hash = ?, token_recuperacion = NULL, token_expiracion = NULL 
                WHERE id = ?";

        $result = $this->db->execute($sql, [$passwordHash, $user['id']]);

        if ($result) {
            $this->logAudit($user['id'], 'password_restablecido', 'usuarios', $user['id'], null, null);
            return ['success' => true, 'message' => 'Contraseña restablecida exitosamente'];
        }

        return ['success' => false, 'message' => 'Error al restablecer contraseña'];
    }

    /**
     * Verificar si un email ya existe
     */
    private function emailExists($email)
    {
        $result = $this->db->queryOne("SELECT id FROM usuarios WHERE email = ?", [$email]);
        return $result !== false;
    }

    /**
     * Verificar si la sesión es válida
     */
    public function validateSession()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }

        // Verificar tiempo de sesión
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Registrar acción en auditoría
     */
    private function logAudit($userId, $accion, $tabla, $registroId, $datosAnteriores, $datosNuevos)
    {
        $sql = "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->execute($sql, [
            $userId,
            $accion,
            $tabla,
            $registroId,
            $datosAnteriores ? json_encode($datosAnteriores) : null,
            $datosNuevos ? json_encode($datosNuevos) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
