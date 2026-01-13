-- ============================================================
-- SCRIPT DE SEMILLA: 10 Usuarios Operadores
-- ============================================================

USE facturacion_aduanera;

-- Insertar 10 usuarios con rol 'operador'
-- Las contraseñas son: usuario1, usuario2, ..., usuario10
-- Hashes generados con PASSWORD_BCRYPT

INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, activo, email_verificado) VALUES
('Agente 01', 'Pruebas', 'agente01.pruebas@facturacion.com', '$2y$10$4QfX.LUUoYoD4jC7BA5T4uOJxNMw1o.VLbfAmi73oTTYIH1cN.BPq', 'operador', 1, 1),
('Agente 02', 'Pruebas', 'agente02.pruebas@facturacion.com', '$2y$10$mOFz5m7zv1iNnRJzHHIcoO1nrNPj5BGjCGrCYaCO8H17T5YScjKS6', 'operador', 1, 1),
('Agente 03', 'Pruebas', 'agente03.pruebas@facturacion.com', '$2y$10$QHKZsXcmMP5WYe.5PQ1XRusGAkje8OHumYZo1UrH1.DqG9CnpHvua', 'operador', 1, 1),
('Agente 04', 'Pruebas', 'agente04.pruebas@facturacion.com', '$2y$10$qlwzZY83U/YnDETWUDngceoyArZFWoNKgF3vwOlOxXGuwTHBNpz/2', 'operador', 1, 1),
('Agente 05', 'Pruebas', 'agente05.pruebas@facturacion.com', '$2y$10$tQ9JQGg0pxBroTfd/E/ytutXjgW4pQpRLw5hiT9SeM.ot/ap35pu.', 'operador', 1, 1),
('Agente 06', 'Pruebas', 'agente06.pruebas@facturacion.com', '$2y$10$QjFbC1u.2NFFlsndKx.zXeXobpcag0o/bupgG9CO1vKCAE1FhL9XG', 'operador', 1, 1),
('Agente 07', 'Pruebas', 'agente07.pruebas@facturacion.com', '$2y$10$fBL8I6mIDLlyiluzJr3OaO1.FmtZ1dCW9RNYN27x/RR3/JC3ofNuK', 'operador', 1, 1),
('Agente 08', 'Pruebas', 'agente08.pruebas@facturacion.com', '$2y$10$D8.F0coC4dsjtKXtJrBl0OUumtHszLyG.Bf36LE5eaj1ZALCyEF1G', 'operador', 1, 1),
('Agente 09', 'Pruebas', 'agente09.pruebas@facturacion.com', '$2y$10$bqiE7fEk7QLle94PkkVnD.ODcY2i8.VNUTlcsx8QRShL8uqJb9mq6', 'operador', 1, 1),
('Agente 10', 'Pruebas', 'agente10.pruebas@facturacion.com', '$2y$10$C9ilpFTgfiD/GYmIEmiE0uU4nP6giDn8QYn6TLmh/Cn/fXFd6CTdm', 'operador', 1, 1);

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
