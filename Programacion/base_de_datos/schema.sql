-- 1. Crear base de datos
CREATE DATABASE IF NOT EXISTS sistema_torneos;
USE sistema_torneos;

-- 2. Tabla ROLES
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL,
    permisos VARCHAR(255)
);

-- 3. Tabla USUARIOS
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'activo',
    id_rol INT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 4. Tabla PARTICIPANTES
CREATE TABLE participantes (
    id_participante INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    ci VARCHAR(20) UNIQUE,
    telefono VARCHAR(20),
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 5. Tabla EQUIPOS
CREATE TABLE equipos (
    id_equipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_equipo VARCHAR(100) NOT NULL,
    id_creador INT NOT NULL,
    FOREIGN KEY (id_creador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 6. Tabla INTEGRANTES_EQUIPO (Tabla de relación N:M entre EQUIPOS y PARTICIPANTES)
CREATE TABLE integrantes_equipo (
    id_equipo INT NOT NULL,
    id_participante INT NOT NULL,
    PRIMARY KEY (id_equipo, id_participante),
    FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_participante) REFERENCES participantes(id_participante) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 7. Tabla MODULOS_COMPETENCIA
CREATE TABLE modulos_competencia (
    id_modulo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_modulo VARCHAR(100) NOT NULL,
    descripcion TEXT
);

-- 8. Tabla TORNEOS
CREATE TABLE torneos (
    id_torneo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_torneo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    id_modulo INT NOT NULL,
    id_organizador INT NOT NULL,
    lugar VARCHAR(100),
    fecha_inicio DATE,
    hora_inicio TIME,
    fecha_fin DATE,
    estado VARCHAR(20) DEFAULT 'pendiente',
    privacidad VARCHAR(20) DEFAULT 'publico',
    FOREIGN KEY (id_modulo) REFERENCES modulos_competencia(id_modulo) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_organizador) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 9. Tabla CONFIGURACION_TORNEO
CREATE TABLE configuracion_torneo (
    id_configuracion INT AUTO_INCREMENT PRIMARY KEY,
    id_torneo INT NOT NULL,
    puntos_victoria INT DEFAULT 3,
    puntos_empate INT DEFAULT 1,
    puntos_derrota INT DEFAULT 0,
    max_participantes INT,
    formato VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_torneo) REFERENCES torneos(id_torneo) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 10. Tabla INSCRIPCIONES_TORNEO
CREATE TABLE inscripciones_torneo (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_torneo INT NOT NULL,
    id_equipo INT,
    id_participante INT,
    estado_inscripcion VARCHAR(20) DEFAULT 'pendiente',
    FOREIGN KEY (id_torneo) REFERENCES torneos(id_torneo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_equipo) REFERENCES equipos(id_equipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_participante) REFERENCES participantes(id_participante) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 11. Tabla RONDAS
CREATE TABLE rondas (
    id_ronda INT AUTO_INCREMENT PRIMARY KEY,
    id_torneo INT NOT NULL,
    numero_ronda INT NOT NULL,
    nombre_ronda VARCHAR(50),
    estado_ronda VARCHAR(20) DEFAULT 'pendiente',
    FOREIGN KEY (id_torneo) REFERENCES torneos(id_torneo) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 12. Tabla ENFRENTAMIENTOS
CREATE TABLE enfrentamientos (
    id_enfrentamiento INT AUTO_INCREMENT PRIMARY KEY,
    id_ronda INT NOT NULL,
    id_local INT NOT NULL,
    id_visitante INT NOT NULL,
    estado_enfrentamiento VARCHAR(20) DEFAULT 'pendiente',
    FOREIGN KEY (id_ronda) REFERENCES rondas(id_ronda) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_local) REFERENCES equipos(id_equipo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_visitante) REFERENCES equipos(id_equipo) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 13. Tabla RESULTADOS
CREATE TABLE resultados (
    id_resultado INT AUTO_INCREMENT PRIMARY KEY,
    id_enfrentamiento INT NOT NULL,
    puntuacion_local INT DEFAULT 0,
    puntuacion_visitante INT DEFAULT 0,
    id_ganador INT,
    id_usuario_registro INT NOT NULL,
    FOREIGN KEY (id_enfrentamiento) REFERENCES enfrentamientos(id_enfrentamiento) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_ganador) REFERENCES equipos(id_equipo) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 14. Tabla AUDITORIA
CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50) NOT NULL,
    id_registro INT NOT NULL,
    id_usuario INT NOT NULL,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
);
INSERT INTO usuarios (username, email, password_hash, id_rol) 
VALUES 
    ('administrador', 'admin@gmail.com', '123456', 1),
    ('organizador', 'orga@gmail.com', '123456', 2),
    ('usuario', 'user@gmail.com', '123456', 3);