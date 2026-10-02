-- ============================================================
-- BATTLE QUIZ - ACTUALIZACIÓN PVP / EMPAREJAMIENTO
-- Ejecutar UNA VEZ sobre la base battle_quiz que ya tienes.
-- No elimina usuarios, preguntas, partidas ni historial existente.
-- MySQL/MariaDB | Puerto esperado: 8000
-- ============================================================

USE battle_quiz;
SET NAMES utf8mb4;

-- Permitir registrar partidas PvP y empates en el historial existente.
ALTER TABLE partidas
    MODIFY modo ENUM('normal','adaptativo','pvp') NOT NULL DEFAULT 'normal';

ALTER TABLE partidas
    MODIFY resultado ENUM('victoria','derrota','empate','abandono') NOT NULL DEFAULT 'abandono';

CREATE TABLE IF NOT EXISTS presencia_usuarios (
    usuario_id INT UNSIGNED NOT NULL PRIMARY KEY,
    session_key VARCHAR(128) NOT NULL,
    estado ENUM('online','queue','battle','offline') NOT NULL DEFAULT 'online',
    ultimo_ping DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presencia_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_presencia_estado_ping (estado, ultimo_ping)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS duelos_pvp (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jugador1_id INT UNSIGNED NOT NULL,
    jugador2_id INT UNSIGNED NOT NULL,
    estado ENUM('playing','finished','cancelled') NOT NULL DEFAULT 'playing',
    jugador1_hp INT NOT NULL DEFAULT 100,
    jugador2_hp INT NOT NULL DEFAULT 100,
    jugador1_puntos INT NOT NULL DEFAULT 0,
    jugador2_puntos INT NOT NULL DEFAULT 0,
    ganador_id INT UNSIGNED NULL,
    empate TINYINT(1) NOT NULL DEFAULT 0,
    stats_applied TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    CONSTRAINT fk_duelo_jugador1
        FOREIGN KEY (jugador1_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_duelo_jugador2
        FOREIGN KEY (jugador2_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_duelo_ganador
        FOREIGN KEY (ganador_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_duelo_estado (estado),
    INDEX idx_duelo_jugadores (jugador1_id, jugador2_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cola_emparejamiento (
    usuario_id INT UNSIGNED NOT NULL PRIMARY KEY,
    session_key VARCHAR(128) NOT NULL,
    estado ENUM('waiting','matched') NOT NULL DEFAULT 'waiting',
    duelo_id BIGINT UNSIGNED NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cola_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cola_duelo
        FOREIGN KEY (duelo_id) REFERENCES duelos_pvp(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_cola_estado_fecha (estado, joined_at),
    INDEX idx_cola_updated (updated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS duelo_preguntas (
    duelo_id BIGINT UNSIGNED NOT NULL,
    posicion SMALLINT UNSIGNED NOT NULL,
    pregunta_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (duelo_id, posicion),
    UNIQUE KEY uq_duelo_pregunta (duelo_id, pregunta_id),
    CONSTRAINT fk_duelo_preguntas_duelo
        FOREIGN KEY (duelo_id) REFERENCES duelos_pvp(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_duelo_preguntas_pregunta
        FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS duelo_respuestas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    duelo_id BIGINT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    pregunta_id INT UNSIGNED NOT NULL,
    opcion_id INT UNSIGNED NOT NULL,
    correcta TINYINT(1) NOT NULL DEFAULT 0,
    puntos INT NOT NULL DEFAULT 0,
    dano INT NOT NULL DEFAULT 0,
    tiempo_ms INT UNSIGNED NOT NULL DEFAULT 0,
    answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_duelo_respuesta_duelo
        FOREIGN KEY (duelo_id) REFERENCES duelos_pvp(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_duelo_respuesta_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_duelo_respuesta_pregunta
        FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_duelo_respuesta_opcion
        FOREIGN KEY (opcion_id) REFERENCES opciones(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_duelo_usuario_pregunta (duelo_id, usuario_id, pregunta_id),
    INDEX idx_duelo_respuesta_usuario (duelo_id, usuario_id)
) ENGINE=InnoDB;

-- Limpia restos de búsquedas viejas si vuelves a ejecutar la actualización.
DELETE FROM cola_emparejamiento WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

SELECT 'Matchmaking PvP instalado correctamente' AS resultado;
