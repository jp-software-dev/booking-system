-- Confirmación por correo para cambio de contraseña desde "Administrar Cuenta".
-- Se guarda el hash de la nueva contraseña (calculado con password_hash() al momento de
-- la solicitud), nunca la contraseña en claro. El cambio solo se aplica al confirmar el
-- token vía el enlace enviado por correo.
CREATE TABLE `cambios_password_pendientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) NOT NULL,
  `nuevo_hash` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_cambios_password_paciente` (`id_paciente`),
  CONSTRAINT `fk_cambios_password_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
