-- Migración: bitácora de auditoría (Parte 9.1)

CREATE TABLE `bitacora` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `rol` varchar(20) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(50) NOT NULL,
  `id_entidad` int(11) DEFAULT NULL,
  `detalle` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bitacora_fecha` (`fecha`),
  KEY `idx_bitacora_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
