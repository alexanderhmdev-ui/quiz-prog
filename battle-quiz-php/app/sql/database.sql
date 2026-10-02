-- Battle-Quiz - Base de datos MySQL/MariaDB

-- Configuración esperada:

-- Host: 127.0.0.1

-- Puerto: 8000

-- Usuario: root

-- Contraseña: 1-5

--

-- IMPORTANTE:

-- Este archivo crea la base y sus tablas.

-- Las credenciales se usan en la conexión PHP, no dentro de la base.

CREATE DATABASE IF NOT EXISTS battle_quiz

  CHARACTER SET utf8mb4

  COLLATE utf8mb4_unicode_ci;

USE battle_quiz;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS vista_preguntas;

DROP TABLE IF EXISTS respuestas_partida;

DROP TABLE IF EXISTS partidas;

DROP TABLE IF EXISTS opciones;

DROP TABLE IF EXISTS preguntas;

DROP TABLE IF EXISTS categorias;

DROP TABLE IF EXISTS usuarios;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nickname VARCHAR(50) NOT NULL UNIQUE,

    puntos_totales INT NOT NULL DEFAULT 0,

    partidas_jugadas INT NOT NULL DEFAULT 0,

    victorias INT NOT NULL DEFAULT 0,

    derrotas INT NOT NULL DEFAULT 0,

    mejor_racha INT NOT NULL DEFAULT 0,

    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;

CREATE TABLE categorias (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(50) NOT NULL UNIQUE,

    descripcion VARCHAR(255) NULL

) ENGINE=InnoDB;

CREATE TABLE preguntas (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    categoria_id INT UNSIGNED NOT NULL,

    enunciado VARCHAR(500) NOT NULL,

    dificultad ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil',

    puntos INT NOT NULL DEFAULT 100,

    explicacion VARCHAR(600) NULL,

    activa TINYINT(1) NOT NULL DEFAULT 1,

    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pregunta_categoria

        FOREIGN KEY (categoria_id) REFERENCES categorias(id)

        ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB;

CREATE TABLE opciones (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    pregunta_id INT UNSIGNED NOT NULL,

    texto VARCHAR(300) NOT NULL,

    es_correcta TINYINT(1) NOT NULL DEFAULT 0,

    orden_opcion TINYINT UNSIGNED NOT NULL,

    CONSTRAINT fk_opcion_pregunta

        FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)

        ON UPDATE CASCADE ON DELETE CASCADE,

    UNIQUE KEY uq_pregunta_orden (pregunta_id, orden_opcion)

) ENGINE=InnoDB;

CREATE TABLE partidas (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT UNSIGNED NOT NULL,

    modo ENUM('normal','adaptativo') NOT NULL DEFAULT 'normal',

    dificultad_final ENUM('facil','medio','dificil') NOT NULL DEFAULT 'facil',

    puntos INT NOT NULL DEFAULT 0,

    respuestas_correctas INT NOT NULL DEFAULT 0,

    respuestas_incorrectas INT NOT NULL DEFAULT 0,

    precision_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    mejor_racha INT NOT NULL DEFAULT 0,

    resultado ENUM('victoria','derrota','abandono') NOT NULL DEFAULT 'abandono',

    duracion_segundos INT NOT NULL DEFAULT 0,

    jugada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_partida_usuario

        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)

        ON UPDATE CASCADE ON DELETE CASCADE

) ENGINE=InnoDB;

CREATE TABLE respuestas_partida (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    partida_id BIGINT UNSIGNED NOT NULL,

    pregunta_id INT UNSIGNED NOT NULL,

    opcion_id INT UNSIGNED NULL,

    fue_correcta TINYINT(1) NOT NULL DEFAULT 0,

    tiempo_respuesta_ms INT UNSIGNED NOT NULL DEFAULT 0,

    puntos_obtenidos INT NOT NULL DEFAULT 0,

    respondida_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_respuesta_partida

        FOREIGN KEY (partida_id) REFERENCES partidas(id)

        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_respuesta_pregunta

        FOREIGN KEY (pregunta_id) REFERENCES preguntas(id)

        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_respuesta_opcion

        FOREIGN KEY (opcion_id) REFERENCES opciones(id)

        ON UPDATE CASCADE ON DELETE SET NULL

) ENGINE=InnoDB;

INSERT INTO categorias  (nombre, descripcion) VALUES

('HTML', 'Estructura y semántica de páginas web'),

('CSS', 'Diseño y estilos visuales'),

('JavaScript', 'Lógica del navegador y programación web'),

('PHP', 'Backend y desarrollo web con PHP'),

('Java', 'Programación orientada a objetos y Spring'),

('MySQL', 'Bases de datos relacionales'),

('Git', 'Control de versiones'),

('POO', 'Programación orientada a objetos');

-- HTML

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué etiqueta representa el contenido principal visible de un documento HTML?','facil',100,

'La etiqueta body contiene el contenido visible de la página.' FROM categorias WHERE nombre='HTML';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'<body>',1,1),(@q,'<head>',0,2),(@q,'<meta>',0,3),(@q,'<title>',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué etiqueta semántica se usa normalmente para la navegación principal?','facil',100,

'nav representa una sección destinada a enlaces de navegación.' FROM categorias WHERE nombre='HTML';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'<nav>',1,1),(@q,'<aside>',0,2),(@q,'<footer>',0,3),(@q,'<section>',0,4);

-- CSS

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué propiedad CSS cambia el color del texto?','facil',100,

'La propiedad color controla el color del texto.' FROM categorias WHERE nombre='CSS';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'color',1,1),(@q,'background-color',0,2),(@q,'font-style',0,3),(@q,'border-color',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué valor de display permite trabajar con un contenedor flexible?','medio',150,

'display:flex activa Flexbox.' FROM categorias WHERE nombre='CSS';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'flex',1,1),(@q,'block',0,2),(@q,'inline',0,3),(@q,'absolute',0,4);

-- JavaScript

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué operador compara valor y tipo en JavaScript?','medio',150,

'El operador === realiza una comparación estricta.' FROM categorias WHERE nombre='JavaScript';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'===',1,1),(@q,'==',0,2),(@q,'=',0,3),(@q,'!=',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué método convierte un texto JSON en un objeto JavaScript?','medio',150,

'JSON.parse interpreta una cadena JSON y devuelve su representación en JavaScript.' FROM categorias WHERE nombre='JavaScript';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'JSON.parse()',1,1),(@q,'JSON.stringify()',0,2),(@q,'JSON.convert()',0,3),(@q,'JSON.object()',0,4);

-- PHP

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué símbolo inicia una variable en PHP?','facil',100,

'Las variables PHP comienzan con el símbolo $.' FROM categorias WHERE nombre='PHP';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'$',1,1),(@q,'#',0,2),(@q,'@',0,3),(@q,'%',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué clase de PHP se usa habitualmente para conectarse a MySQL de forma segura y portable?','medio',150,

'PDO permite conectarse a distintas bases de datos y usar sentencias preparadas.' FROM categorias WHERE nombre='PHP';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'PDO',1,1),(@q,'HTML',0,2),(@q,'JSON',0,3),(@q,'DOM',0,4);

-- Java

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué palabra clave se utiliza para heredar de una clase en Java?','facil',100,

'extends permite que una clase herede de otra.' FROM categorias WHERE nombre='Java';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'extends',1,1),(@q,'implements',0,2),(@q,'inherits',0,3),(@q,'superclass',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué anotación de Spring Boot se usa comúnmente para crear un controlador REST?','medio',150,

'@RestController combina el comportamiento de controlador y respuesta serializada.' FROM categorias WHERE nombre='Java';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'@RestController',1,1),(@q,'@Entity',0,2),(@q,'@Bean',0,3),(@q,'@ServiceOnly',0,4);

-- MySQL

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué sentencia se utiliza para consultar datos de una tabla?','facil',100,

'SELECT permite obtener registros de una o más tablas.' FROM categorias WHERE nombre='MySQL';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'SELECT',1,1),(@q,'UPDATE',0,2),(@q,'DELETE',0,3),(@q,'DROP',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué cláusula ordena el resultado de una consulta?','facil',100,

'ORDER BY ordena las filas por una o más columnas.' FROM categorias WHERE nombre='MySQL';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'ORDER BY',1,1),(@q,'GROUP BY',0,2),(@q,'WHERE',0,3),(@q,'HAVING',0,4);

-- Git

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué comando crea un repositorio Git nuevo en la carpeta actual?','facil',100,

'git init inicializa un repositorio local.' FROM categorias WHERE nombre='Git';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'git init',1,1),(@q,'git start',0,2),(@q,'git create',0,3),(@q,'git new',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué comando envía commits locales a un repositorio remoto?','facil',100,

'git push publica los commits locales en el remoto configurado.' FROM categorias WHERE nombre='Git';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'git push',1,1),(@q,'git pull',0,2),(@q,'git status',0,3),(@q,'git add',0,4);

-- POO

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué concepto de POO oculta el estado interno y controla su acceso?','medio',150,

'La encapsulación protege los datos y expone operaciones controladas.' FROM categorias WHERE nombre='POO';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'Encapsulación',1,1),(@q,'Iteración',0,2),(@q,'Compilación',0,3),(@q,'Recursividad',0,4);

INSERT INTO preguntas (categoria_id,enunciado,dificultad,puntos,explicacion)

SELECT id,'¿Qué principio permite que objetos distintos respondan de forma diferente al mismo método?','dificil',200,

'El polimorfismo permite usar una misma interfaz con comportamientos distintos.' FROM categorias WHERE nombre='POO';

SET @q = LAST_INSERT_ID();

INSERT INTO opciones (pregunta_id,texto,es_correcta,orden_opcion) VALUES

(@q,'Polimorfismo',1,1),(@q,'Encapsulación',0,2),(@q,'Agregación',0,3),(@q,'Serialización',0,4);

-- Usuario de demostración

INSERT INTO usuarios (nickname) VALUES ('Jugador1');

-- Vista rápida para consultar preguntas con categoría

CREATE OR REPLACE VIEW vista_preguntas AS

SELECT

    p.id,

    c.nombre AS categoria,

    p.enunciado,

    p.dificultad,

    p.puntos,

    p.explicacion,

    p.activa

FROM preguntas p

INNER JOIN categorias c ON c.id = p.categoria_id;

-- ============================================================
-- EXTENSIÓN PVP / EMPAREJAMIENTO
-- ============================================================

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
